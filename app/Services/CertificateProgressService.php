<?php

namespace App\Services;

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Enums\CertificateTagOutcome;
use App\Enums\ProgramScopeMode;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationToolSubmission;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CertificateProgressService
{
    /**
     * Doc 4 supplies the previous award date when opening a later cycle. Missing boundaries
     * must never allow old work to earn renewal credit.
     *
     * @return array{requirements: Collection<int, array<string, mixed>>, groups: Collection<int, array{group: CertificateRequirementGroup, met: int, target: int, satisfied: bool}>, met: int, total: int, satisfied: bool, cycle_blocked: bool}
     */
    public function evaluate(CertificateEnrollment $enrollment, ?CarbonInterface $previousAwardDate = null): array
    {
        $enrollment->load(['certificate.programs', 'certificate.requirements.dimensionRules.dimension.options', 'certificate.requirements.certificationTool', 'certificate.requirements.activityType', 'certificate.requirementGroups', 'attestations.satisfiedBy']);
        $certificate = $enrollment->certificate;
        if ($certificate === null) {
            throw new \LogicException('Enrollment certificate is missing.');
        }
        $phase = $enrollment->phase === CertificateRequirementPhase::Renewal && $certificate->renewal_matches_initial
            ? CertificateRequirementPhase::Initial : $enrollment->phase;
        $requirements = $certificate->requirements->where('phase', $phase);
        $tags = ActivityCertificationCandidate::query()
            ->where('certification_candidate_id', $enrollment->certification_candidate_id)
            ->with(['activity.programs', 'activity.activityType', 'submissions.dimensionValues'])
            ->get();
        $blocked = $enrollment->cycle > 1 && $previousAwardDate === null;
        $results = $requirements->mapWithKeys(function (CertificateRequirement $requirement) use ($enrollment, $tags, $previousAwardDate, $blocked): array {
            return [$requirement->id => $this->requirement($enrollment, $requirement, $tags, $previousAwardDate, $blocked)];
        });
        $groups = $certificate->requirementGroups->where('phase', $phase)->map(function (CertificateRequirementGroup $group) use ($results): array {
            $members = $results->filter(fn ($row) => $row['requirement']->certificate_requirement_group_id === $group->id);
            $met = $members->where('satisfied', true)->count();
            $target = match ($group->satisfy_mode) {
                CertificateGroupSatisfyMode::All => $members->count(),
                CertificateGroupSatisfyMode::Any => 1,
                CertificateGroupSatisfyMode::NOf => (int) $group->required_count,
            };

            return ['group' => $group, 'met' => $met, 'target' => $target, 'satisfied' => $members->isNotEmpty() && $met >= $target];
        });
        $ungrouped = $results->filter(fn ($row) => $row['requirement']->certificate_requirement_group_id === null);

        return [
            'requirements' => $results, 'groups' => $groups,
            'met' => $results->where('satisfied', true)->count(), 'total' => $results->count(),
            'satisfied' => ! $blocked && $results->isNotEmpty() && $ungrouped->every(fn ($row) => $row['satisfied']) && $groups->every(fn ($row) => $row['satisfied']),
            'cycle_blocked' => $blocked,
        ];
    }

    /**
     * @param  Collection<int, ActivityCertificationCandidate>  $tags
     * @return array<string, mixed>
     */
    private function requirement(CertificateEnrollment $enrollment, CertificateRequirement $requirement, Collection $tags, ?CarbonInterface $previousAwardDate, bool $blocked): array
    {
        $certificate = $enrollment->certificate;
        if ($certificate === null) {
            throw new \LogicException('Enrollment certificate is missing.');
        }
        $months = $requirement->window_months ?? $certificate->default_window_months;
        $windowStart = $months !== null ? now()->startOfDay()->subMonthsNoOverflow($months) : null;
        $result = ['requirement' => $requirement, 'count' => 0, 'target' => $requirement->target_count, 'satisfied' => false, 'evidence' => collect(), 'rules' => collect(), 'attestation' => null, 'window_start' => $windowStart];
        if ($requirement->kind === CertificateRequirementKind::Attestation) {
            $attestation = $enrollment->attestations->first(fn ($row) => $row->certificate_requirement_id === $requirement->id && $row->cycle === $enrollment->cycle);
            $result['attestation'] = $attestation;
            $result['count'] = $attestation?->satisfied ? 1 : 0;
            $result['satisfied'] = ! $blocked && (bool) $attestation?->satisfied;

            return $result;
        }
        $pool = collect();
        foreach ($tags as $tag) {
            $activity = $tag->activity;
            if (($requirement->activity_type_id !== null && $activity->activity_type_id !== $requirement->activity_type_id)
                || ($requirement->contact_family_id !== null && $activity->activityType?->contact_family_id !== $requirement->contact_family_id)) {
                continue;
            }
            $reason = match (true) {
                $blocked => 'The previous award date is needed for this cycle.',
                $activity->cancelled => 'Activity is cancelled.',
                $activity->not_yet_complete => 'Activity is not yet complete.',
                $requirement->certification_role_id !== null && $tag->certification_role_id !== $requirement->certification_role_id => 'Activity role does not match.',
                $certificate->program_scope_mode !== ProgramScopeMode::All && ($certificate->program_scope_mode === ProgramScopeMode::None || array_intersect($activity->programs->modelKeys(), $certificate->programs->modelKeys()) === []) => 'Activity programs do not overlap this certificate.',
                $windowStart !== null && $activity->engagement_date->lt($windowStart) => 'Before the rolling window.',
                $previousAwardDate !== null && $enrollment->cycle > 1 && $activity->engagement_date->toDateString() <= $previousAwardDate->toDateString() => 'On or before the previous award date.',
                default => null,
            };
            if ($requirement->kind === CertificateRequirementKind::ActivityCount) {
                $result['evidence']->push(['tag' => $tag, 'submission' => null, 'reason' => $reason]);
                if ($reason === null) {
                    $result['count'] += $tag->completion_units;
                }

                continue;
            }
            foreach ($tag->submissions->where('certification_tool_id', $requirement->certification_tool_id) as $submission) {
                $submissionReason = $reason ?? ($requirement->requires_passing && $submission->outcome !== CertificateTagOutcome::Passed ? 'This requirement needs passing work.' : null);
                $result['evidence']->push(['tag' => $tag, 'submission' => $submission, 'reason' => $submissionReason]);
                if ($submissionReason === null) {
                    $pool->push($submission);
                }
            }
        }
        if ($requirement->kind === CertificateRequirementKind::ToolSubmission) {
            $result['count'] = $pool->count();
            $result['rules'] = $requirement->dimensionRules->map(function ($rule) use ($pool): array {
                $options = array_map('intval', $rule->option_ids ?? []);
                $values = $pool->map(fn (CertificationToolSubmission $submission) => $submission->dimensionValues->firstWhere('certification_tool_dimension_id', $rule->certification_tool_dimension_id)?->certification_tool_dimension_option_id)->filter();
                $count = $rule->mode === CertificateDimensionRuleMode::Coverage
                    ? count(array_intersect($options, $values->unique()->all()))
                    : $values->filter(fn ($id) => in_array((int) $id, $options, true))->count();
                $target = $rule->mode === CertificateDimensionRuleMode::Coverage ? count($options) : (int) $rule->min_count;

                return ['rule' => $rule, 'count' => $count, 'target' => $target, 'satisfied' => $target > 0 && $count >= $target];
            });
        }
        $result['satisfied'] = ! $blocked && $result['count'] >= $result['target'] && $result['rules']->every(fn ($row) => $row['satisfied']);

        return $result;
    }
}
