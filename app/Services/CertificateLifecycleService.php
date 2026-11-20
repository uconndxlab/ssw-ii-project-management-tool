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
use Illuminate\Support\Facades\Validator;
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
                && array_intersect($actor->access()->activityReferenceUserIds(), array_filter([$pending->endorsed_by_user_id, $enrollment->supervising_coach_user_id])) !== [];
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

    public function historyStateBlocker(CertificateEnrollment $enrollment): ?string
    {
        if ($enrollment->status === CertificateEnrollmentStatus::Revoked) {
            return 'This enrollment was revoked. Award history cannot be changed.';
        }
        if ($enrollment->awards()->where(fn ($query) => $query->where('manual_award', false)->orWhereNull('awarded_at'))->exists()) {
            return 'Award history is final once an in-app endorsement or award exists.';
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function saveHistory(User $actor, CertificateEnrollment $enrollment, array $data): void
    {
        DB::transaction(function () use ($actor, $enrollment, $data): void {
            $enrollment = $this->lock($enrollment, (int) ($data['cycle'] ?? $enrollment->cycle));
            if (! $this->access->canRecordHistory($actor, $enrollment)) {
                throw new AuthorizationException;
            }
            if ($blocker = $this->historyStateBlocker($enrollment)) {
                throw new CertificateLifecycleConflict($blocker);
            }
            if (is_array($data['rows'] ?? null)) {
                $data['rows'] = array_values($data['rows']);
            }
            // The form always posts the current date; only a changed one is checked and saved.
            if (($data['enrolled_at'] ?? null) === $enrollment->enrolled_at?->toDateString()) {
                unset($data['enrolled_at']);
            }
            $existing = $enrollment->awards()->reorder('cycle')->get();
            $rules = [
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.id' => ['nullable', 'integer', 'distinct'],
                'rows.*.awarded_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'rows.*.expiry_overridden' => ['required', 'boolean'],
                'rows.*.expires_at' => ['nullable', 'date_format:Y-m-d'],
                'rows.*.originally_awarded_by' => ['nullable', 'string', 'max:255'],
                'reason' => ['required', 'string', 'max:10000'],
                'enrolled_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            ];
            $validator = Validator::make($data, $rules, [
                'reason.required' => 'Explain why you are recording or editing this award history.',
                'rows.*.awarded_at.required' => 'Enter an award date.',
                'rows.*.awarded_at.before_or_equal' => 'Award dates cannot be in the future.',
            ]);
            $validator->after(function ($validator) use ($data, $existing, $enrollment): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $rows = $data['rows'];
                if ($enrollment->certificate?->validity_months === null && count($rows) !== 1) {
                    $validator->errors()->add('rows', 'A certificate that never expires can have only one award.');
                }
                if (count($rows) < $existing->count()) {
                    $validator->errors()->add('rows', 'Saved awards cannot be removed.');
                }
                foreach ($rows as $index => $row) {
                    $expectedId = $existing->get($index)?->id;
                    if (($expectedId !== null && (int) ($row['id'] ?? 0) !== $expectedId) || ($expectedId === null && filled($row['id'] ?? null))) {
                        $validator->errors()->add("rows.$index.id", 'Keep every saved award in its original order. Add new awards at the end.');
                    }
                    if ($index > 0 && $row['awarded_at'] <= $rows[$index - 1]['awarded_at']) {
                        $validator->errors()->add("rows.$index.awarded_at", 'Each award date must be after the previous award date.');
                    }
                    if ($row['expiry_overridden'] && (blank($row['expires_at'] ?? null) || $row['expires_at'] <= $row['awarded_at'] || $enrollment->certificate?->validity_months === null)) {
                        $validator->errors()->add("rows.$index.expires_at", 'Enter an expiry after this award date for a certificate that expires.');
                    }
                }
                if (filled($data['enrolled_at'] ?? null) && $data['enrolled_at'] > $rows[0]['awarded_at']) {
                    $validator->errors()->add('enrolled_at', 'Enrollment must be on or before the first award date.');
                }
            });
            $validated = $validator->validate();
            $before = $existing->map(fn (CertificateAward $award) => $this->historyRow($award))->all();
            $oldEnrollmentDate = $enrollment->enrolled_at?->toDateString();
            $previous = null;
            $saved = [];
            foreach ($validated['rows'] as $index => $row) {
                $date = Carbon::parse($row['awarded_at'])->startOfDay();
                $expiry = $row['expiry_overridden'] ? Carbon::parse($row['expires_at']) : $this->expiryFromPrevious($enrollment, $date, $previous);
                $award = $existing->get($index) ?? new CertificateAward;
                $award->fill([
                    'certificate_enrollment_id' => $enrollment->id, 'cycle' => $index + 1,
                    'phase' => $index === 0 ? CertificateRequirementPhase::Initial : CertificateRequirementPhase::Renewal,
                    'awarded_at' => $date, 'endorsed_at' => $date, 'endorsed_by_user_id' => null,
                    'manual_award' => true, 'expires_at' => $expiry, 'expiry_overridden' => (bool) $row['expiry_overridden'],
                    'originally_awarded_by' => $row['originally_awarded_by'] ?? null,
                ]);
                if (! $award->exists) {
                    $award->awarded_by_user_id = $actor->id;
                    $award->requirements_snapshot = [
                        'manual' => true, 'evidence' => [],
                        'certificate' => $enrollment->certificate?->only(['id', 'name', 'validity_months', 'prerequisite_mode']),
                    ];
                }
                $award->save();
                $saved[] = $this->historyRow($award);
                $previous = $expiry;
            }
            $renewal = $enrollment->certificate?->validity_months !== null;
            $enrollment->fill([
                'cycle' => $renewal ? count($saved) + 1 : 1,
                'phase' => $renewal ? CertificateRequirementPhase::Renewal : CertificateRequirementPhase::Initial,
                'status' => $renewal ? CertificateEnrollmentStatus::InProgress : CertificateEnrollmentStatus::Awarded,
            ]);
            if (filled($validated['enrolled_at'] ?? null)) {
                $enrollment->enrolled_at = $validated['enrolled_at'];
            }
            $enrollment->save();
            $changes = [];
            foreach ($before as $index => $old) {
                foreach ($old as $field => $value) {
                    if ($value !== $saved[$index][$field]) {
                        $changes[$index + 1][$field] = ['from' => $value, 'to' => $saved[$index][$field]];
                    }
                }
            }
            $context = ['rows' => $saved, 'changes' => $changes];
            if ($oldEnrollmentDate !== $enrollment->enrolled_at?->toDateString()) {
                $context['enrolled_at'] = ['from' => $oldEnrollmentDate, 'to' => $enrollment->enrolled_at?->toDateString()];
            }
            $this->log($enrollment, $actor, $existing->isEmpty() ? CertificateAction::HistoryRecorded : CertificateAction::HistoryEdited, null, $validated['reason'], $context);
        });
    }

    /** @return array{cycle: int, awarded_at: ?string, expires_at: ?string, overridden: bool, originally_awarded_by: ?string} */
    private function historyRow(CertificateAward $award): array
    {
        return ['cycle' => $award->cycle, 'awarded_at' => $award->awarded_at?->toDateString(), 'expires_at' => $award->expires_at?->toDateString(), 'overridden' => $award->expiry_overridden, 'originally_awarded_by' => $award->originally_awarded_by];
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
            if (! $this->canChangeCoach($actor, $enrollment)) {
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

    /**
     * Certification managers, or anyone who could change this coach from the agreement form.
     */
    public function canChangeCoach(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $this->access->canManage($actor, $enrollment)
            || ($this->access->canView($actor, $enrollment) && $this->assignments->canManageEnrollment($actor, $enrollment));
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
        $previous = $enrollment->cycle > 1 ? $enrollment->awards()->issued()->where('cycle', $enrollment->cycle - 1)->first()?->expires_at : null;

        return $this->expiryFromPrevious($enrollment, $awardedAt, $previous);
    }

    private function expiryFromPrevious(CertificateEnrollment $enrollment, CarbonInterface $awardedAt, ?CarbonInterface $previous): ?Carbon
    {
        $months = $enrollment->certificate?->validity_months;
        if ($months === null) {
            return null;
        }
        $base = $previous !== null && $previous->isAfter($awardedAt->copy()->startOfDay()) ? $previous : Carbon::parse($awardedAt)->startOfDay();

        return Carbon::parse($base)->addMonthsNoOverflow($months);
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
                    'id' => $requirement->id, 'label' => $requirement->label ?: ($requirement->certificationInstrument->name ?? ($requirement->activityType ? $requirement->activityType->name.($requirement->subActivity ? ' › '.$requirement->subActivity->name : '') : $requirement->kind->label())),
                    'kind' => $requirement->kind->value, 'group_id' => $requirement->certificate_requirement_group_id,
                    'target' => $row['target'], 'count' => $row['count'], 'satisfied' => $row['satisfied'], 'requires_passing' => $requirement->requires_passing,
                    'window_months' => $requirement->window_months, 'window_start' => $row['window_start']?->toDateString(),
                    'threshold_note' => $requirement->threshold_note, 'notes' => $requirement->notes,
                    'filters' => ['tool' => $requirement->certificationInstrument?->name, 'activity_type' => $requirement->activityType?->name, 'sub_activity' => $requirement->subActivity?->name, 'contact_family' => $requirement->contactFamily?->name, 'role' => $requirement->certificationRole?->name],
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
                            'activity_type' => $tag->activity->activityType?->name, 'sub_activity' => $tag->activity->subActivity?->name, 'units' => $tag->completion_units, 'tag_notes' => $tag->notes,
                            'submission' => $submission ? [
                                'id' => $submission->id, 'outcome' => $submission->outcome->value, 'notes' => $submission->notes, 'recorded_by' => $submission->recordedBy?->name,
                                'options' => $submission->dimensionValues->map(fn ($value) => $value->dimension->name.': '.$value->option->label.($value->result ? ' · '.$value->result->label() : ''))->values()->all(),
                                'scores' => $submission->scores->map(fn ($score) => ['field' => $score->field->name, 'value' => (string) $score->value, 'unit' => $score->field->unit->value, 'max_points' => $score->field->max_points])->values()->all(),
                            ] : null,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
