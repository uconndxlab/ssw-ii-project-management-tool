<?php

namespace App\Services;

use App\Enums\CertificateRequirementPhase;
use App\Enums\CertificateTagOutcome;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\ActivityReviewRequest;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\CertificationCandidate;
use App\Models\CertificationTool;
use App\Models\CertificationToolSubmission;
use App\Models\User;
use App\Notifications\ActivityReviewRequested;
use App\Support\Authorization\CertificateProgressAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificationEvidenceService
{
    /** @param array<int> $programIds */
    public function canRecord(User $actor, array $programIds, ?CertificationTool $tool = null, ?CertificationCandidate $candidate = null): bool
    {
        $coachProgramIds = $actor->certification()->coachProgramIds();
        if ($candidate !== null) {
            $coachProgramIds = array_merge($coachProgramIds, $actor->certification()->coachProgramIdsViaSuperviseesFor($candidate));
        }
        $ids = array_intersect($programIds, $coachProgramIds);
        if ($tool !== null && $tool->program_scope_mode !== ProgramScopeMode::All) {
            $ids = $tool->program_scope_mode === ProgramScopeMode::None ? [] : array_intersect($ids, $tool->programs->modelKeys());
        }

        if ($candidate !== null) {
            $candidate->loadMissing('enrollments.certificate.programs');

            return $ids !== [] && $candidate->enrollments->contains(fn ($enrollment) => $enrollment->certificate !== null && $this->overlaps($enrollment->certificate, array_values($ids)));
        }

        return $ids !== [];
    }

    /**
     * Coach standing on this activity: a coach duty in its programs, or being the supervisor of the coach
     * assigned to any tagged candidate.
     *
     * @param  array<int>  $programIds
     */
    public function canRecordAny(User $actor, Activity $activity, array $programIds): bool
    {
        return $this->canRecord($actor, $programIds)
            || $activity->certificationCandidates()->with('candidate.enrollments.supervisingCoach')->get()
                ->contains(fn (ActivityCertificationCandidate $tag) => $this->canRecord($actor, $programIds, null, $tag->candidate));
    }

    /** @param array<int> $programIds */
    public function overlaps(Certificate|CertificationTool $catalog, array $programIds): bool
    {
        return $catalog->program_scope_mode === ProgramScopeMode::All
            || ($catalog->program_scope_mode === ProgramScopeMode::Specific && array_intersect($catalog->programs->modelKeys(), $programIds) !== []);
    }

    /** @param array<int> $programIds
     * @return Collection<int, CertificateRequirement>
     */
    public function matchingRequirements(CertificationCandidate $candidate, int $typeId, int $familyId, array $programIds): Collection
    {
        return $candidate->enrollments->flatMap(function (CertificateEnrollment $enrollment) use ($typeId, $familyId, $programIds) {
            $certificate = $enrollment->certificate;
            if ($certificate === null) {
                throw new \LogicException('Enrollment certificate is missing.');
            }
            $phase = $enrollment->phase === CertificateRequirementPhase::Renewal && $certificate->renewal_matches_initial ? CertificateRequirementPhase::Initial : $enrollment->phase;

            return $this->overlaps($certificate, $programIds)
                ? $certificate->requirements->filter(fn (CertificateRequirement $requirement) => $requirement->phase === $phase
                    && ($requirement->activity_type_id === null || $requirement->activity_type_id === $typeId)
                    && ($requirement->contact_family_id === null || $requirement->contact_family_id === $familyId))
                : collect();
        });
    }

    /**
     * Called inside the activity transaction, after the activity's effective programs are saved.
     * Omitted rows are retained. Only explicit removal markers delete evidence.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(Activity $activity, User $actor, array $input): void
    {
        if (isset($input['review_coach_user_ids']) && is_array($input['review_coach_user_ids'])) {
            // The form always posts an empty entry so removing every coach still reaches the server.
            $input['review_coach_user_ids'] = array_values(array_filter($input['review_coach_user_ids'], fn ($id) => $id !== '' && $id !== null));
        }
        $data = Validator::make($input, [
            'certification_candidates' => ['sometimes', 'array', 'max:250'],
            'certification_candidates.*' => ['array:id,certification_candidate_id,agreement_id,certification_role_id,completion_units,notes,_delete,submissions'],
            'certification_candidates.*.id' => ['nullable', 'integer', 'distinct'],
            'certification_candidates.*.certification_candidate_id' => ['required', 'integer', 'distinct'],
            'certification_candidates.*.agreement_id' => ['nullable', 'integer'],
            'certification_candidates.*.certification_role_id' => ['nullable', 'integer'],
            'certification_candidates.*.completion_units' => ['required', 'integer', 'min:1', 'max:999'],
            'certification_candidates.*.notes' => ['nullable', 'string', 'max:10000'],
            'certification_candidates.*._delete' => ['nullable', 'boolean'],
            'certification_candidates.*.submissions' => ['sometimes', 'array', 'max:100'],
            'certification_candidates.*.submissions.*' => ['array:id,certification_tool_id,outcome,notes,_delete,dimensions,scores'],
            'certification_candidates.*.submissions.*.id' => ['nullable', 'integer', 'distinct'],
            'certification_candidates.*.submissions.*.certification_tool_id' => ['required', 'integer'],
            'certification_candidates.*.submissions.*.outcome' => ['nullable', Rule::enum(CertificateTagOutcome::class)],
            'certification_candidates.*.submissions.*.notes' => ['nullable', 'string', 'max:10000'],
            'certification_candidates.*.submissions.*._delete' => ['nullable', 'boolean'],
            'certification_candidates.*.submissions.*.dimensions' => ['sometimes', 'array'],
            'certification_candidates.*.submissions.*.dimensions.*' => ['nullable', 'integer'],
            'certification_candidates.*.submissions.*.scores' => ['sometimes', 'array'],
            'certification_candidates.*.submissions.*.scores.*' => ['nullable', 'numeric', 'between:-99999999,99999999'],
            'needs_certification_review' => ['sometimes', 'boolean'],
            'review_coach_user_ids' => ['sometimes', 'array', 'max:50'],
            'review_coach_user_ids.*' => ['integer', 'distinct'],
        ])->validate();
        $activity->load(['programs', 'activityType']);
        $programIds = $activity->programs->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $existing = $activity->certificationCandidates()->with(['submissions.tool.programs', 'submissions.dimensionValues', 'submissions.scores'])->get()->keyBy('id');
        foreach ($data['certification_candidates'] ?? [] as $index => $row) {
            $key = "certification_candidates.{$index}";
            $tag = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            if (! empty($row['id']) && $tag === null) {
                $this->invalid($key.'.id', 'Select a candidate tag belonging to this activity.');
            }
            if ($tag !== null && ((int) $row['certification_candidate_id'] !== $tag->certification_candidate_id || (int) ($row['agreement_id'] ?? 0) !== (int) $tag->agreement_id)) {
                $this->invalid($key, 'A saved tag keeps its candidate and source agreement. Remove it and add a new row.');
            }
            if ($tag === null && $existing->contains('certification_candidate_id', (int) $row['certification_candidate_id'])) {
                $this->invalid($key.'.id', 'This candidate is already tagged. Edit the saved row instead.');
            }
            if (! empty($row['_delete'])) {
                if ($tag !== null) {
                    foreach ($tag->submissions as $submission) {
                        if (! $this->canRecord($actor, $programIds, $submission->tool, $tag->candidate)) {
                            $this->invalid($key.'._delete', 'A coach in scope must remove a candidate with recorded submissions.');
                        }
                    }
                    $tag->delete();
                }

                continue;
            }
            $candidate = CertificationCandidate::query()->with(['enrollments.certificate.programs', 'enrollments.certificate.requirements'])->whereKey($row['certification_candidate_id'])->first();
            if ($candidate === null) {
                $this->invalid($key.'.certification_candidate_id', 'Select a candidate from a selected agreement roster.');
            }
            if ($tag === null && (! $candidate->active || ! AgreementCertificationCandidate::query()
                ->where('certification_candidate_id', $candidate->id)->where('agreement_id', $row['agreement_id'] ?? null)
                ->whereIn('agreement_id', $activity->agreements()->pluck('agreements.id'))->exists())) {
                $this->invalid($key.'.certification_candidate_id', 'Select an active candidate from a selected agreement roster.');
            }
            $roleId = ! empty($row['certification_role_id']) ? (int) $row['certification_role_id'] : null;
            $roleIds = $this->matchingRequirements($candidate, $activity->activity_type_id, (int) $activity->activityType?->contact_family_id, $programIds)
                ->pluck('certification_role_id')->filter()->unique()->all();
            if ($roleId !== null && $roleId !== $tag?->certification_role_id && ! in_array($roleId, $roleIds, true)) {
                $this->invalid($key.'.certification_role_id', 'Choose a role used by this candidate’s certificate requirements for this activity.');
            }
            $tag ??= new ActivityCertificationCandidate(['activity_id' => $activity->id, 'certification_candidate_id' => $candidate->id, 'agreement_id' => $row['agreement_id'] ?? null]);
            $tag->fill(['certification_role_id' => $roleId, 'completion_units' => $row['completion_units'], 'notes' => $row['notes'] ?? null])->save();
            $savedSubmissions = $tag->submissions()->with(['dimensionValues', 'scores'])->get()->keyBy('id');
            foreach ($row['submissions'] ?? [] as $submissionIndex => $submissionRow) {
                $this->saveSubmission($tag, $actor, $programIds, $submissionRow, $savedSubmissions, $key.'.submissions.'.$submissionIndex);
            }
        }
        $coachIds = array_key_exists('review_coach_user_ids', $data) ? array_map('intval', $data['review_coach_user_ids']) : null;
        $flagged = $activity->certification_review_requested_at !== null;
        if (array_key_exists('needs_certification_review', $data) || $coachIds !== null) {
            $requested = (bool) ($data['needs_certification_review'] ?? $flagged) || ($coachIds ?? []) !== [];
            if (! $requested && $flagged && ! $this->canRecordAny($actor, $activity, $programIds)) {
                $this->invalid('needs_certification_review', 'A coach in an activity program must clear certification review.');
            }
            if ($requested !== $flagged) {
                $activity->update(['certification_review_requested_at' => $requested ? now() : null, 'certification_review_requested_by_user_id' => $requested ? $actor->id : null]);
            }
            if (! $requested) {
                $activity->reviewRequests()->whereNull('cleared_at')->update(['cleared_at' => now()]);
            } elseif ($coachIds !== null) {
                $this->syncReviewRequests($activity, $actor, $programIds, $coachIds);
            }
        }
    }

    /**
     * Replaces the activity's asked reviewers. Only coaches newly asked are emailed, so re-saving
     * the activity never re-notifies anyone.
     *
     * @param  array<int>  $programIds
     * @param  array<int>  $coachIds
     */
    private function syncReviewRequests(Activity $activity, User $actor, array $programIds, array $coachIds): void
    {
        $rows = $activity->reviewRequests()->get()->keyBy('coach_user_id');
        $allowed = array_merge($rows->keys()->map(fn ($id) => (int) $id)->all(), $this->reviewCoachOptions($activity, $programIds)->keys()->map(fn ($id) => (int) $id)->all());
        if (array_diff($coachIds, $allowed) !== []) {
            $this->invalid('review_coach_user_ids', 'Choose coaches who supervise a tagged candidate or coach on the selected agreements.');
        }
        $activity->reviewRequests()->whereNull('cleared_at')->whereNotIn('coach_user_id', $coachIds)->delete();
        $added = [];
        foreach ($coachIds as $coachId) {
            $row = $rows->get($coachId);
            if ($row === null) {
                $activity->reviewRequests()->create(['coach_user_id' => $coachId, 'requested_by_user_id' => $actor->id]);
                $added[] = $coachId;
            } elseif ($row->cleared_at !== null) {
                // Asked again after a cleared review: shown as requested again, but not re-emailed.
                $row->update(['cleared_at' => null]);
            }
        }
        User::query()->whereKey(array_diff($added, [$actor->id]))->where('active', true)->get()
            ->each(fn (User $coach) => $coach->notify(new ActivityReviewRequested($activity, $actor)));
    }

    /**
     * Coaches an editor may ask: supervising coaches of the tagged candidates, then coaches on the
     * agreements' staff (direct or through a team) in a program of the activity.
     *
     * @param  array<int>  $programIds
     * @return Collection<int, User>
     */
    private function reviewCoachOptions(Activity $activity, array $programIds): Collection
    {
        $supervisingIds = $activity->certificationCandidates()->with('candidate.enrollments')->get()
            ->flatMap(fn ($tag) => $tag->candidate->enrollments->pluck('supervising_coach_user_id'))->filter()->unique()->all();
        $staff = $activity->agreements()->with(['users.certificationDuties', 'teams.users.certificationDuties'])->get()
            ->flatMap(fn (Agreement $agreement) => $agreement->allUsers())
            ->filter(fn (User $user) => array_intersect($user->certification()->coachProgramIds(), $programIds) !== []);

        return $staff->merge(User::query()->whereKey($supervisingIds)->get())->where('active', true)->unique('id')->keyBy('id');
    }

    /** @param array<int> $programIds
     * @param  array<string, mixed>  $row
     * @param  Collection<int, CertificationToolSubmission>  $existing
     */
    private function saveSubmission(ActivityCertificationCandidate $tag, User $actor, array $programIds, array $row, Collection $existing, string $key): void
    {
        $submission = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
        if (! empty($row['id']) && $submission === null) {
            $this->invalid($key.'.id', 'Select a submission belonging to this candidate tag.');
        }
        $tool = CertificationTool::query()->with(['programs', 'dimensions.options', 'scoreFields'])->whereKey($row['certification_tool_id'])->first();
        if ($tool === null || ! $this->canRecord($actor, $programIds, $tool, $tag->candidate)) {
            $this->invalid($key, 'A certification coach in a program shared by the activity, tool, and candidate’s certificate must record submissions.');
        }
        if ($submission !== null && $submission->certification_tool_id !== $tool->id) {
            $this->invalid($key.'.certification_tool_id', 'A saved submission keeps its tool. Remove it and add a new submission.');
        }
        if (! empty($row['_delete'])) {
            $submission?->delete();

            return;
        }
        if ($submission === null && (! $tool->active || $tool->retired_at !== null || ! $this->overlaps($tool, $programIds))) {
            $this->invalid($key.'.certification_tool_id', 'Choose an active tool available for the activity programs.');
        }
        if (empty($row['outcome'])) {
            $this->invalid($key.'.outcome', 'Choose the coach-judged outcome. Scores never decide it.');
        }
        $dimensions = $row['dimensions'] ?? [];
        if (array_diff(array_keys($dimensions), $tool->dimensions->modelKeys()) !== []) {
            $this->invalid($key.'.dimensions', 'Choose dimensions belonging to this tool.');
        }
        foreach ($tool->dimensions as $dimension) {
            if (! in_array((int) ($dimensions[$dimension->id] ?? 0), $dimension->options->modelKeys(), true)) {
                $this->invalid($key.'.dimensions.'.$dimension->id, 'Choose '.$dimension->name.' for this submission.');
            }
        }
        if (array_diff(array_keys($row['scores'] ?? []), $tool->scoreFields->modelKeys()) !== []) {
            $this->invalid($key.'.scores', 'Use score fields belonging to this tool.');
        }
        $submission ??= new CertificationToolSubmission(['activity_certification_candidate_id' => $tag->id, 'certification_tool_id' => $tool->id]);
        $submission->fill(['outcome' => $row['outcome'], 'notes' => $row['notes'] ?? null]);
        $scores = array_filter($row['scores'] ?? [], fn ($value) => $value !== null && $value !== '');
        if ($submission->exists && ! $submission->isDirty()
            && $submission->dimensionValues->pluck('certification_tool_dimension_option_id', 'certification_tool_dimension_id')->all() == $dimensions
            && $submission->scores->pluck('value', 'certification_tool_score_field_id')->all() == $scores) {
            return;
        }
        $submission->recorded_by_user_id = $actor->id;
        $submission->save();
        $submission->dimensionValues()->delete();
        foreach ($dimensions as $dimensionId => $optionId) {
            $submission->dimensionValues()->create(['certification_tool_dimension_id' => $dimensionId, 'certification_tool_dimension_option_id' => $optionId]);
        }
        $submission->scores()->delete();
        foreach ($row['scores'] ?? [] as $fieldId => $value) {
            if ($value !== null && $value !== '') {
                $submission->scores()->create(['certification_tool_score_field_id' => $fieldId, 'value' => $value]);
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Agreement>  $agreements
     * @return array<string, mixed>
     */
    public function formData(User $actor, Collection $agreements, ?Activity $activity = null): array
    {
        $agreements->loadMissing(['certificationCandidates.candidate.enrollments.certificate.programs', 'certificationCandidates.candidate.enrollments.certificate.requirements.certificationRole', 'certificationCandidates.candidate.enrollments.supervisingCoach', 'users.certificationDuties', 'teams.users.certificationDuties']);
        $tags = $activity?->certificationCandidates()->with(['candidate.enrollments.supervisingCoach', 'candidate.enrollments.certificate.programs', 'candidate.enrollments.certificate.requirements.certificationRole', 'role', 'submissions.tool.programs', 'submissions.dimensionValues', 'submissions.scores'])->get() ?? new \Illuminate\Database\Eloquent\Collection;
        $people = $agreements->flatMap(fn ($agreement) => $agreement->certificationCandidates->pluck('candidate'))->merge($tags->pluck('candidate'))->filter()->unique('id');
        if ($people->isEmpty()) {
            return ['candidates' => [], 'saved' => [], 'tools' => [], 'agreement_coaches' => [], 'reviewers' => [], 'coach_program_ids' => $actor->certification()->coachProgramIds()];
        }
        $visibleCertificateIds = Certificate::query()->visibleTo($actor)->pluck('id')->all();
        $access = app(CertificateProgressAccess::class);
        $candidates = $people->map(function (CertificationCandidate $candidate) use ($agreements, $actor, $access, $visibleCertificateIds): array {
            return [
                'id' => $candidate->id, 'name' => $candidate->name, 'email' => $candidate->email, 'active' => $candidate->active,
                'coach_program_ids' => array_values(array_unique(array_merge($actor->certification()->coachProgramIds(), $actor->certification()->coachProgramIdsViaSuperviseesFor($candidate)))),
                'supervising_coaches' => $candidate->enrollments->pluck('supervisingCoach')->filter()->where('active', true)->unique('id')->map(fn (User $coach) => ['id' => $coach->id, 'name' => $coach->name])->values()->all(),
                'agreements' => $agreements->filter(fn ($agreement) => $agreement->certificationCandidates->contains('certification_candidate_id', $candidate->id))->map(fn ($agreement) => ['id' => $agreement->id, 'name' => $agreement->name])->values()->all(),
                'enrollments' => $candidate->enrollments->filter(fn ($enrollment) => in_array($enrollment->certificate_id, $visibleCertificateIds, true))->map(function (CertificateEnrollment $enrollment) use ($access, $actor): array {
                    $certificate = $enrollment->certificate;
                    if ($certificate === null) {
                        throw new \LogicException('Enrollment certificate is missing.');
                    }
                    $phase = $enrollment->phase === CertificateRequirementPhase::Renewal && $certificate->renewal_matches_initial ? CertificateRequirementPhase::Initial : $enrollment->phase;
                    $details = $access->canViewDetails($actor, $enrollment);

                    return [
                        'id' => $enrollment->id, 'name' => $certificate->name, 'mode' => $certificate->program_scope_mode->value,
                        'program_ids' => $certificate->programs->modelKeys(),
                        'url' => $access->canView($actor, $enrollment) ? route('certificate-enrollments.show', $enrollment) : null,
                        'requirements' => $certificate->requirements->where('phase', $phase)->map(fn ($requirement) => [
                            'type_id' => $requirement->activity_type_id, 'family_id' => $requirement->contact_family_id,
                            'role_id' => $requirement->certification_role_id, 'role_name' => $requirement->certificationRole?->name,
                            'tool_id' => $requirement->certification_tool_id, 'kind' => $requirement->kind->value,
                            'label' => $details ? $requirement->label : null,
                            'guidance' => $details ? $requirement->threshold_note : null,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
        $programIds = $activity?->programs->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [];
        $detailedProgramIds = array_values(array_unique(array_merge($actor->access()->adminProgramIds(), $actor->certification()->coachProgramIds(), $actor->certification()->managerProgramIds())));
        $saved = $tags->map(function ($tag) use ($actor, $programIds, $detailedProgramIds): array {
            $detailedProgramIds = array_merge($detailedProgramIds, $actor->certification()->coachProgramIdsViaSuperviseesFor($tag->candidate));
            $details = $actor->isSystemAdmin() || array_intersect($programIds, $detailedProgramIds) !== [];

            return [
                'id' => $tag->id, 'certification_candidate_id' => $tag->certification_candidate_id,
                'agreement_id' => $tag->agreement_id, 'certification_role_id' => $tag->certification_role_id,
                'role_name' => $tag->role?->name, 'completion_units' => $tag->completion_units, 'notes' => $tag->notes,
                'submission_count' => $tag->submissions->count(),
                'submissions' => $details ? $tag->submissions->filter(fn ($submission) => $actor->isSystemAdmin() || $this->overlaps($submission->tool, array_values(array_intersect($programIds, $detailedProgramIds))))->map(fn ($submission) => [
                    'id' => $submission->id, 'certification_tool_id' => $submission->certification_tool_id,
                    'outcome' => $submission->outcome->value, 'notes' => $submission->notes,
                    'editable' => $this->canRecord($actor, $programIds, $submission->tool, $tag->candidate),
                    'dimensions' => $submission->dimensionValues->pluck('certification_tool_dimension_option_id', 'certification_tool_dimension_id')->all(),
                    'scores' => $submission->scores->pluck('value', 'certification_tool_score_field_id')->all(),
                ])->values()->all() : [],
            ];
        })->values()->all();
        $tools = CertificationTool::query()->visibleTo($actor)->with(['programs', 'dimensions.options', 'scoreFields'])->get()
            ->merge($tags->flatMap(fn ($tag) => $tag->submissions->pluck('tool')))->unique('id');
        $tools->loadMissing(['dimensions.options', 'scoreFields']);

        return [
            'candidates' => $candidates, 'saved' => $saved,
            'coach_program_ids' => $actor->certification()->coachProgramIds(),
            'agreement_coaches' => $agreements->flatMap(fn (Agreement $agreement) => $agreement->allUsers()->where('active', true)->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'program_ids' => $user->certification()->coachProgramIds(), 'agreement_id' => $agreement->id]))
                ->filter(fn (array $coach) => $coach['program_ids'] !== [])->unique(fn (array $coach) => $coach['id'].'-'.$coach['agreement_id'])->values()->all(),
            'reviewers' => $activity?->reviewRequests()->whereNull('cleared_at')->with('coach:id,name')->get()->map(fn (ActivityReviewRequest $request) => ['id' => $request->coach_user_id, 'name' => $request->coach->name])->values()->all() ?? [],
            'tools' => $tools->map(fn ($tool) => [
                'id' => $tool->id, 'name' => $tool->name, 'description' => $tool->description,
                'active' => $tool->active && $tool->retired_at === null, 'mode' => $tool->program_scope_mode->value,
                'program_ids' => $tool->programs->modelKeys(),
                'dimensions' => $tool->dimensions->map(fn ($dimension) => ['id' => $dimension->id, 'name' => $dimension->name, 'options' => $dimension->options->map(fn ($option) => ['id' => $option->id, 'label' => $option->label])->all()])->all(),
                'score_fields' => $tool->scoreFields->map(fn ($field) => ['id' => $field->id, 'name' => $field->name, 'unit' => $field->unit->value])->all(),
            ])->values()->all(),
        ];
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
