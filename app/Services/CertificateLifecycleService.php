<?php

namespace App\Services;

use App\Enums\CertificateAction;
use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Exceptions\CertificateLifecycleConflict;
use App\Models\Certificate;
use App\Models\CertificateActionLog;
use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\User;
use App\Support\Authorization\CertificateProgressAccess;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coach endorsement → manager award → renewal/revocation. Every mutation locks the enrollment,
 * re-checks the state the form was opened against, writes, and records an action log row.
 */
class CertificateLifecycleService
{
    public function __construct(
        private CertificateProgressService $progress,
        private CertificateProgressAccess $access,
        private CertificationAssignmentSyncService $assignments,
    ) {}

    public function previousAwardDate(CertificateEnrollment $enrollment): ?CarbonInterface
    {
        return $enrollment->cycle > 1
            ? $enrollment->awards()->issued()->where('cycle', $enrollment->cycle - 1)->first()?->awarded_at
            : null;
    }

    /** @return array<string, mixed> */
    public function evaluate(CertificateEnrollment $enrollment): array
    {
        return $this->progress->evaluate($enrollment, $this->previousAwardDate($enrollment));
    }

    /** The current cycle's endorsement that is still waiting for a manager. */
    public function pending(CertificateEnrollment $enrollment): ?CertificateAward
    {
        return $enrollment->awards()->where('cycle', $enrollment->cycle)->whereNull('awarded_at')->with('endorsedBy')->first();
    }

    /**
     * Prerequisites are held through real, live awards (issued, unrevoked, unexpired).
     *
     * @return array{satisfied: bool, mode: string, items: list<array{certificate: Certificate, award: ?CertificateAward}>}
     */
    public function prerequisites(CertificateEnrollment $enrollment): array
    {
        $certificate = $enrollment->certificate;
        if ($certificate === null) {
            throw new \LogicException('Enrollment certificate is missing.');
        }
        $required = $certificate->prerequisites()->orderBy('name')->get();
        $awards = CertificateAward::query()->issued()
            ->whereHas('enrollment', fn ($query) => $query->where('certification_candidate_id', $enrollment->certification_candidate_id)->whereIn('certificate_id', $required->modelKeys()))
            ->with('enrollment')->orderByDesc('cycle')->get();
        $items = array_values($required->map(fn (Certificate $prerequisite) => [
            'certificate' => $prerequisite,
            'award' => $awards->first(fn (CertificateAward $award) => $award->enrollment?->certificate_id === $prerequisite->id && $award->isLive()),
        ])->all());
        $held = collect($items)->whereNotNull('award')->count();
        $mode = $certificate->prerequisite_mode === 'any' ? 'any' : 'all';

        return ['satisfied' => $items === [] || ($mode === 'any' ? $held > 0 : $held === count($items)), 'mode' => $mode, 'items' => $items];
    }

    public function endorse(User $actor, CertificateEnrollment $enrollment, int $cycle, ?string $note): CertificateAward
    {
        return DB::transaction(function () use ($actor, $enrollment, $cycle, $note): CertificateAward {
            $enrollment = $this->lock($enrollment, $cycle);
            if (! $this->access->canEndorse($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            if ($pending = $this->pending($enrollment)) {
                throw new CertificateLifecycleConflict('Already endorsed by '.($pending->endorsedBy->name ?? 'a former staff member').' on '.$pending->endorsed_at->format('M j, Y').'. Nothing else was changed.');
            }
            $result = $this->evaluate($enrollment);
            if (! $result['satisfied']) {
                throw new CertificateLifecycleConflict('Requirements are not all met right now, so nothing was endorsed.');
            }
            $award = $enrollment->awards()->create([
                'cycle' => $enrollment->cycle, 'phase' => $enrollment->phase, 'endorsed_at' => now(),
                'endorsed_by_user_id' => $actor->id, 'endorsement_note' => $note,
                'requirements_snapshot' => $this->snapshot($enrollment, $result),
            ]);
            $enrollment->update(['status' => CertificateEnrollmentStatus::Endorsed]);
            $this->log($enrollment, $actor, CertificateAction::Endorsed, $award, $note, ['met' => $result['met'], 'total' => $result['total']]);

            return $award;
        });
    }

    /** A coach withdraws (reason optional) or a manager returns the endorsement to the coach (reason required). */
    public function withdraw(User $actor, CertificateEnrollment $enrollment, int $cycle, int $awardId, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $enrollment, $cycle, $awardId, $reason): void {
            $enrollment = $this->lock($enrollment, $cycle);
            $pending = $this->pendingOpenedAs($enrollment, $awardId);
            $asCoach = $this->access->canConfirm($actor, $enrollment)
                && in_array($actor->id, [$pending->endorsed_by_user_id, $enrollment->supervising_coach_user_id], true);
            if (! $asCoach && ! $this->access->canManage($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            if (! $asCoach && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Explain why this endorsement is being returned to the coach.']);
            }
            $context = ['endorsed_by' => $pending->endorsedBy?->name, 'endorsed_at' => $pending->endorsed_at->toIso8601String()];
            $pending->delete();
            $enrollment->update(['status' => CertificateEnrollmentStatus::InProgress]);
            $this->log($enrollment, $actor, $asCoach ? CertificateAction::EndorsementWithdrawn : CertificateAction::EndorsementReturned, null, $reason, $context);
        });
    }

    public function award(User $actor, CertificateEnrollment $enrollment, int $cycle, int $awardId, ?string $note): CertificateAward
    {
        return DB::transaction(function () use ($actor, $enrollment, $cycle, $awardId, $note): CertificateAward {
            $issued = $enrollment->awards()->issued()->whereKey($awardId)->with('awardedBy')->first();
            if ($issued !== null) {
                throw new CertificateLifecycleConflict("Already awarded for cycle {$issued->cycle} by ".($issued->awardedBy->name ?? 'a former staff member').' on '.$issued->awarded_at?->format('M j, Y g:i A').'. Nothing else was changed.');
            }
            $enrollment = $this->lock($enrollment, $cycle);
            if (! $this->access->canManage($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            $pending = $this->pendingOpenedAs($enrollment, $awardId);
            if ($pending->endorsed_by_user_id === $actor->id) {
                throw new CertificateLifecycleConflict('You endorsed this cycle, so another certification manager must award it.');
            }
            $prerequisites = $this->prerequisites($enrollment);
            if (! $prerequisites['satisfied']) {
                throw new CertificateLifecycleConflict('Award needs a valid '.collect($prerequisites['items'])->pluck('certificate.name')->join(', ', $prerequisites['mode'] === 'any' ? ' or ' : ' and ').' award.');
            }
            $awardedAt = now();
            $snapshot = $pending->requirements_snapshot;
            $snapshot['prerequisites'] = [];
            foreach ($prerequisites['items'] as ['certificate' => $required, 'award' => $held]) {
                if ($held !== null) {
                    $snapshot['prerequisites'][] = ['certificate' => $required->name, 'award_id' => $held->id, 'cycle' => $held->cycle, 'expires_at' => $held->expires_at?->toDateString()];
                }
            }
            $pending->update([
                'awarded_at' => $awardedAt, 'awarded_by_user_id' => $actor->id, 'award_note' => $note,
                'expires_at' => $this->expiresAt($enrollment, $awardedAt), 'requirements_snapshot' => $snapshot,
            ]);
            $this->log($enrollment, $actor, CertificateAction::Awarded, $pending, $note, ['expires_at' => $pending->expires_at?->toDateString()]);
            if ($enrollment->certificate?->validity_months !== null) {
                $enrollment->update(['cycle' => $enrollment->cycle + 1, 'phase' => CertificateRequirementPhase::Renewal, 'status' => CertificateEnrollmentStatus::InProgress]);
                $this->log($enrollment, null, CertificateAction::RenewalOpened, $pending, null, ['boundary' => $awardedAt->toDateString()]);
            } else {
                $enrollment->update(['status' => CertificateEnrollmentStatus::Awarded]);
            }

            return $pending;
        });
    }

    public function revoke(User $actor, CertificateEnrollment $enrollment, CertificateAward $award, string $reason): void
    {
        DB::transaction(function () use ($actor, $enrollment, $award, $reason): void {
            $enrollment = CertificateEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $award = $award->fresh();
            if (! $this->access->canManage($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            if (! $award?->isLive() || $enrollment->awards()->issued()->where('cycle', '>', $award->cycle)->exists()) {
                throw new CertificateLifecycleConflict('Only the current award can be revoked. This award is already revoked, expired or replaced by a renewal.');
            }
            $award->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id, 'revoke_reason' => $reason]);
            $enrollment->update(['status' => CertificateEnrollmentStatus::Revoked]);
            $this->log($enrollment, $actor, CertificateAction::Revoked, $award, $reason, ['cycle' => $award->cycle]);
        });
    }

    public function changeCoach(User $actor, CertificateEnrollment $enrollment, int $coachId, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $enrollment, $coachId, $reason): void {
            $enrollment = CertificateEnrollment::query()->lockForUpdate()->with('supervisingCoach')->findOrFail($enrollment->id);
            if (! $this->access->canManage($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            $coach = $this->eligibleCoaches($actor, $enrollment)->firstWhere('id', $coachId);
            if ($coach === null) {
                throw ValidationException::withMessages(['coach_id' => 'Choose a coach with coach duty for this certificate’s programs.']);
            }
            if ($coach->id === $enrollment->supervising_coach_user_id) {
                return;
            }
            $context = ['from' => $enrollment->supervisingCoach?->name, 'to' => $coach->name];
            $enrollment->update(['supervising_coach_user_id' => $coach->id]);
            $this->log($enrollment, $actor, CertificateAction::CoachChanged, null, $reason, $context);
        });
    }

    /** @return Collection<int, User> */
    public function eligibleCoaches(User $actor, CertificateEnrollment $enrollment): Collection
    {
        $certificate = $enrollment->certificate;

        return $certificate === null ? collect() : $this->assignments->eligibleCoaches(array_values($this->access->programIds($certificate)), $actor)
            ->filter(fn (User $coach) => $this->access->canConfirm($coach, $enrollment))->values();
    }

    /** On-time renewal extends the previous expiry; a first or late award runs from its own date. */
    public function expiresAt(CertificateEnrollment $enrollment, CarbonInterface $awardedAt): ?Carbon
    {
        $months = $enrollment->certificate?->validity_months;
        if ($months === null) {
            return null;
        }
        $previous = $enrollment->cycle > 1 ? $enrollment->awards()->issued()->where('cycle', $enrollment->cycle - 1)->first()?->expires_at : null;
        $base = $previous !== null && $previous->isAfter($awardedAt->copy()->startOfDay()) ? $previous : Carbon::parse($awardedAt)->startOfDay();

        return $base->copy()->addMonthsNoOverflow($months);
    }

    private function lock(CertificateEnrollment $enrollment, int $cycle): CertificateEnrollment
    {
        $locked = CertificateEnrollment::query()->lockForUpdate()->with('certificate.programs')->findOrFail($enrollment->id);
        if ($locked->cycle !== $cycle) {
            throw new CertificateLifecycleConflict("This enrollment moved to cycle {$locked->cycle} after you opened it. Nothing was saved; review the current status below.");
        }
        if ($locked->status === CertificateEnrollmentStatus::Revoked) {
            throw new CertificateLifecycleConflict('This enrollment’s award was revoked, so endorsement and award are closed.');
        }

        return $locked;
    }

    private function pendingOpenedAs(CertificateEnrollment $enrollment, int $awardId): CertificateAward
    {
        $pending = $this->pending($enrollment);
        if ($pending === null || $pending->id !== $awardId) {
            throw new CertificateLifecycleConflict('The endorsement changed after you opened this page. Nothing was saved; review the current status below.');
        }

        return $pending;
    }

    /** @param array<string, mixed> $context */
    private function log(CertificateEnrollment $enrollment, ?User $actor, CertificateAction $action, ?CertificateAward $award, ?string $reason, array $context = []): void
    {
        CertificateActionLog::query()->create([
            'certificate_enrollment_id' => $enrollment->id, 'certificate_award_id' => $award?->id, 'user_id' => $actor?->id,
            'action' => $action, 'cycle' => $enrollment->cycle, 'reason' => $reason, 'context' => $context,
        ]);
    }

    /**
     * Frozen at endorsement so the award explains itself without depending on mutable evidence or catalog rows.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function snapshot(CertificateEnrollment $enrollment, array $result): array
    {
        $certificate = $enrollment->certificate;

        return [
            'certificate' => ['id' => $certificate?->id, 'name' => $certificate?->name, 'validity_months' => $certificate?->validity_months, 'prerequisite_mode' => $certificate?->prerequisite_mode],
            'cycle' => $enrollment->cycle, 'phase' => $enrollment->phase->value,
            'linked_renewal' => $enrollment->phase === CertificateRequirementPhase::Renewal && (bool) $certificate?->renewal_matches_initial,
            'previous_award_date' => $this->previousAwardDate($enrollment)?->toDateString(), 'evaluated_at' => now()->toIso8601String(),
            'met' => $result['met'], 'total' => $result['total'],
            'groups' => $result['groups']->map(fn (array $row) => [
                'id' => $row['group']->id, 'label' => $row['group']->label, 'mode' => $row['group']->satisfy_mode->value, 'met' => $row['met'], 'target' => $row['target'],
            ])->values()->all(),
            'requirements' => $result['requirements']->map(function (array $row): array {
                /** @var CertificateRequirement $requirement */
                $requirement = $row['requirement'];
                $requirement->loadMissing(['certificationRole', 'contactFamily']);

                return [
                    'id' => $requirement->id, 'label' => $requirement->label ?: ($requirement->certificationTool->name ?? $requirement->activityType->name ?? $requirement->kind->label()),
                    'kind' => $requirement->kind->value, 'group_id' => $requirement->certificate_requirement_group_id,
                    'target' => $row['target'], 'count' => $row['count'], 'satisfied' => $row['satisfied'], 'requires_passing' => $requirement->requires_passing,
                    'window_months' => $requirement->window_months, 'window_start' => $row['window_start']?->toDateString(),
                    'threshold_note' => $requirement->threshold_note, 'notes' => $requirement->notes,
                    'filters' => ['tool' => $requirement->certificationTool?->name, 'activity_type' => $requirement->activityType?->name, 'contact_family' => $requirement->contactFamily?->name, 'role' => $requirement->certificationRole?->name],
                    'rules' => $row['rules']->map(fn (array $rule) => [
                        'dimension' => $rule['rule']->dimension->name, 'mode' => $rule['rule']->mode->value,
                        'options' => $rule['rule']->dimension->options->whereIn('id', $rule['rule']->option_ids)->pluck('label')->values()->all(),
                        'count' => $rule['count'], 'target' => $rule['target'], 'satisfied' => $rule['satisfied'],
                    ])->values()->all(),
                    'attestation' => $requirement->kind === CertificateRequirementKind::Attestation && $row['attestation'] ? [
                        'satisfied' => $row['attestation']->satisfied, 'by' => $row['attestation']->satisfiedBy?->name,
                        'at' => $row['attestation']->satisfied_at?->toIso8601String(), 'note' => $row['attestation']->note,
                    ] : null,
                    'counted' => $row['evidence']->whereNull('reason')->map(function (array $evidence): array {
                        $tag = $evidence['tag'];
                        $submission = $evidence['submission']?->loadMissing(['dimensionValues.dimension', 'dimensionValues.option', 'scores.field', 'recordedBy']);

                        return [
                            'tag_id' => $tag->id, 'activity_id' => $tag->activity_id, 'date' => $tag->activity->engagement_date->toDateString(),
                            'activity_type' => $tag->activity->activityType?->name, 'units' => $tag->completion_units, 'tag_notes' => $tag->notes,
                            'submission' => $submission ? [
                                'id' => $submission->id, 'outcome' => $submission->outcome->value, 'notes' => $submission->notes, 'recorded_by' => $submission->recordedBy?->name,
                                'options' => $submission->dimensionValues->map(fn ($value) => $value->dimension->name.': '.$value->option->label)->values()->all(),
                                'scores' => $submission->scores->map(fn ($score) => ['field' => $score->field->name, 'value' => (string) $score->value, 'unit' => $score->field->unit->value])->values()->all(),
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
