<?php

namespace App\Services;

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Program;
use App\Models\User;
use App\Support\Authorization\CertificationAccess;
use App\Support\ProjectProgramScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Candidate/certificate assignment from the agreement form: existing-candidate selection or
 * new-candidate creation, roster linking, and global (candidate, certificate) enrollment. See
 * `docs/certifications/doc-2-people-and-enrollment.md` for the submitted row shape and the
 * identity-edit/removal rules this class enforces.
 */
class CertificationAssignmentSyncService
{
    /**
     * Certificates eligible for assignment on an agreement with the given effective program scope:
     * active, not retired, visible to the actor (view-level, not catalog-admin), and applicable to
     * the agreement's program scope via the same overlap rule used for other scoped pickers.
     *
     * @param  list<int>  $agreementProgramIds
     * @return Collection<int, Certificate>
     */
    public function eligibleCertificates(User $actor, ProgramScopeMode $agreementMode, array $agreementProgramIds): Collection
    {
        return Certificate::query()
            ->active()
            ->notRetired()
            ->visibleTo($actor)
            ->with('programs:id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Certificate $certificate) => ProjectProgramScope::scopedEntityVisibleToCertificatePrograms(
                $certificate->program_scope_mode,
                $certificate->programs->pluck('id'),
                $agreementMode,
                $agreementProgramIds,
            ))
            ->values();
    }

    /**
     * Active staff with an active coach duty reaching the agreement's program scope. A duty never
     * grants implicit visibility, so this is further intersected by the caller's own staff pool.
     *
     * @param  list<int>  $agreementProgramIds
     * @return Collection<int, User>
     */
    public function eligibleCoaches(array $agreementProgramIds, User $actor, ?Agreement $agreement = null): Collection
    {
        $userIds = CertificationAccess::eligibleCoachUserIds($agreementProgramIds);

        if ($userIds === []) {
            return new Collection;
        }

        return User::query()->active()->whereIn('id', $userIds)
            ->visibleTo($actor)
            ->with(['certificationDuties', 'privileges', 'programs'])
            ->orderBy('name')->get()
            ->filter(function (User $coach) use ($agreement, $agreementProgramIds) {
                return $coach->access()->hasSystemView()
                    || array_intersect($coach->access()->viewProgramIds(), $agreementProgramIds) !== []
                    || ($agreement !== null && ! $coach->access()->hasView() && $coach->can('view', $agreement));
            })->values();
    }

    /**
     * Candidates reachable through agreements the actor may view (plus all candidates for a
     * system administrator, who may also manage otherwise unlinked migrated/orphaned candidates).
     *
     * @return Collection<int, CertificationCandidate>
     */
    public function reachableCandidates(User $actor): Collection
    {
        $query = CertificationCandidate::query()->active()->orderBy('name');

        if (! $actor->access()->isSystemAdmin()) {
            $query->whereHas(
                'rosterLinks',
                fn ($relation) => $relation->whereHas('agreement', fn ($agreementQuery) => $agreementQuery->visibleTo($actor))
            );
        }

        return $query->with([
            'rosterLinks.agreement.programs', 'enrollments.certificate.programs',
            'enrollments.supervisingCoach', 'enrollments.sponsoringAgreement.programs',
        ])->get(['id', 'name', 'email', 'active']);
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  list<int>  $agreementProgramIds
     */
    public function validateRows(
        Validator $validator,
        User $actor,
        ?Agreement $agreement,
        array $rows,
        ProgramScopeMode $agreementMode,
        array $agreementProgramIds,
    ): void {
        if ($agreementMode === ProgramScopeMode::All) {
            $agreementProgramIds = array_values(Program::query()->where('active', true)->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        $eligibleCertificates = $this->eligibleCertificates($actor, $agreementMode, $agreementProgramIds)->keyBy('id');
        $eligibleCoaches = $this->eligibleCoaches($agreementProgramIds, $actor, $agreement)->keyBy('id');

        $existingRoster = $agreement ? $agreement->certificationCandidates()->get()->keyBy('id') : new Collection;
        $existingRosterIds = $existingRoster->modelKeys();
        $existingRosterCandidateToRowId = $existingRoster->pluck('id', 'certification_candidate_id')->all();
        $candidates = CertificationCandidate::query()
            ->whereKey(collect($rows)->filter(fn ($row) => is_array($row))->pluck('certification_candidate_id')->filter()->all())
            ->with(['rosterLinks.agreement.programs', 'enrollments.certificate.programs', 'enrollments.sponsoringAgreement.programs'])
            ->get()->keyBy('id');
        $reachableCandidateIds = $actor->access()->isSystemAdmin() ? $candidates->modelKeys()
            : AgreementCertificationCandidate::query()->whereIn('certification_candidate_id', $candidates->modelKeys())
                ->whereHas('agreement', fn ($query) => $query->visibleTo($actor))
                ->pluck('certification_candidate_id')->all();

        $seenNewEmails = [];
        $seenCandidateIds = [];
        $seenRosterIds = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($rowId !== null && (! in_array($rowId, $existingRosterIds, true) || isset($seenRosterIds[$rowId]))) {
                $validator->errors()->add("certification_candidates.{$index}.id", 'Select a unique roster row belonging to this agreement.');

                continue;
            }

            if ($rowId !== null) {
                $seenRosterIds[$rowId] = true;
            }

            if (filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $candidateId = isset($row['certification_candidate_id']) && $row['certification_candidate_id'] !== ''
                ? (int) $row['certification_candidate_id']
                : null;

            $persistedRoster = $rowId !== null ? $existingRoster->get($rowId) : null;
            if ($rowId !== null && ($persistedRoster === null || (int) $persistedRoster->certification_candidate_id !== $candidateId)) {
                $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'Remove this roster link and add a different candidate in a new row.');

                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $assignmentRows = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
            $hasAssignmentContent = collect($assignmentRows)->contains(fn ($a) => is_array($a) && filled($a['certificate_id'] ?? null) && ! filter_var($a['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN));

            if ($candidateId === null && $name === '' && blank($row['email'] ?? null) && ! $hasAssignmentContent) {
                continue;
            }

            $candidate = null;

            if ($candidateId !== null) {
                $candidate = $candidates->get($candidateId);

                if ($candidate === null || ! in_array($candidateId, $reachableCandidateIds, true)) {
                    $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'Select a candidate you have access to.');

                    continue;
                }

                if (isset($seenCandidateIds[$candidateId])) {
                    $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'This candidate is already on this agreement\'s roster.');

                    continue;
                }

                $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
                $existingLinkRowId = $existingRosterCandidateToRowId[$candidateId] ?? null;

                if (! $candidate->active && $existingLinkRowId === null) {
                    $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'Inactive candidates cannot receive new assignments.');

                    continue;
                }

                if ($existingLinkRowId !== null && $existingLinkRowId !== $rowId) {
                    $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'This candidate is already on this agreement\'s roster. Edit that row instead of adding a new one.');

                    continue;
                }

                $seenCandidateIds[$candidateId] = true;

                $name = array_key_exists('name', $row) ? $name : $candidate->name;
                $email = array_key_exists('email', $row) ? (string) $row['email'] : (string) $candidate->email;
                $this->validateIdentityEdit($validator, $actor, $candidate, $name, $email, $index);
            } else {
                $email = strtolower(trim((string) ($row['email'] ?? '')));

                if ($name === '') {
                    $validator->errors()->add("certification_candidates.{$index}.name", 'Enter a name for the new candidate.');
                }

                if ($email === '') {
                    $validator->errors()->add("certification_candidates.{$index}.email", 'Enter an email for the new candidate.');
                } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add("certification_candidates.{$index}.email", 'Enter a valid email address.');
                } elseif (CertificationCandidate::query()->where('email', $email)->exists()) {
                    $validator->errors()->add("certification_candidates.{$index}.email", 'That email is already in use by another candidate. Select the existing candidate instead.');
                }
            }

            $normalizedEmail = $this->normalizeEmail($email);
            if ($normalizedEmail !== null) {
                if (isset($seenNewEmails[$normalizedEmail])) {
                    $validator->errors()->add("certification_candidates.{$index}.email", 'Each candidate must have a different email address.');
                }

                $seenNewEmails[$normalizedEmail] = true;
            }

            $seenCertificateIds = [];

            foreach ($assignmentRows as $assignmentIndex => $assignmentRow) {
                if (! is_array($assignmentRow)) {
                    continue;
                }

                $certificateId = isset($assignmentRow['certificate_id']) && $assignmentRow['certificate_id'] !== ''
                    ? (int) $assignmentRow['certificate_id']
                    : null;

                if ($certificateId === null) {
                    continue;
                }

                $fieldPrefix = "certification_candidates.{$index}.assignments.{$assignmentIndex}";
                $markedForDeletion = filter_var($assignmentRow['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (isset($seenCertificateIds[$certificateId])) {
                    $validator->errors()->add("{$fieldPrefix}.certificate_id", 'This certificate is already listed for this candidate.');
                }
                $seenCertificateIds[$certificateId] = true;
                $existingEnrollment = $candidate?->enrollments->firstWhere('certificate_id', $certificateId);
                $existingEnrollment?->setRelation('candidate', $candidate);

                $enrollmentId = filled($assignmentRow['enrollment_id'] ?? null) ? (int) $assignmentRow['enrollment_id'] : null;
                if ($enrollmentId !== null && $enrollmentId !== $existingEnrollment?->id) {
                    $validator->errors()->add("{$fieldPrefix}.enrollment_id", 'That assignment does not belong to this candidate and certificate.');

                    continue;
                }

                if ($markedForDeletion) {
                    if ($existingEnrollment === null) {
                        continue;
                    }

                    if (! $existingEnrollment->isUnstarted()) {
                        $validator->errors()->add("{$fieldPrefix}.certificate_id", 'Only a not-started assignment can be removed.');
                    } elseif (! $this->canManageEnrollment($actor, $existingEnrollment)) {
                        $validator->errors()->add("{$fieldPrefix}.certificate_id", 'You must be able to manage this certificate and every agreement affected by its removal.');
                    }

                    continue;
                }

                if ($existingEnrollment === null && (! $eligibleCertificates->has($certificateId) || ($candidate !== null && ! $candidate->active))) {
                    $validator->errors()->add("{$fieldPrefix}.certificate_id", 'That certificate is not available for this agreement.');
                }

                $coachUserId = isset($assignmentRow['coach_user_id']) && $assignmentRow['coach_user_id'] !== ''
                    ? (int) $assignmentRow['coach_user_id']
                    : null;

                $coachChanged = $enrollmentId !== null
                    && array_key_exists('coach_user_id', $assignmentRow)
                    && $coachUserId !== $existingEnrollment->supervising_coach_user_id;

                if ($coachChanged) {
                    if (! $existingEnrollment->isUnstarted()) {
                        $validator->errors()->add("{$fieldPrefix}.coach_user_id", 'Only a not-started assignment can be corrected here.');
                    } elseif (! $this->canManageEnrollment($actor, $existingEnrollment)) {
                        $validator->errors()->add("{$fieldPrefix}.coach_user_id", 'You cannot change the coach for this shared assignment.');
                    }
                }

                if ($coachUserId !== null && ($existingEnrollment === null || $coachChanged)) {
                    $coach = $eligibleCoaches->get($coachUserId);
                    $certificate = $existingEnrollment !== null ? $existingEnrollment->certificate : $eligibleCertificates->get($certificateId);
                    $certificateProgramIds = $certificate?->program_scope_mode === ProgramScopeMode::All
                        ? $agreementProgramIds
                        : array_intersect($certificate?->programs->pluck('id')->all() ?? [], $agreementProgramIds);

                    if ($coach === null || array_intersect($coach->certification()->coachProgramIds(), $certificateProgramIds) === []) {
                        $validator->errors()->add("{$fieldPrefix}.coach_user_id", 'Select a coach who can view this agreement and has a coach duty in the certificate\'s programs.');
                    }
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    public function sync(Agreement $agreement, User $actor, array $rows): void
    {
        Agreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
        $candidates = CertificationCandidate::query()
            ->whereKey(collect($rows)->pluck('certification_candidate_id')->filter()->all())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $agreement->load('programs');
        $validator = ValidatorFacade::make([], []);
        $this->validateRows($validator, $actor, $agreement, $rows, $agreement->program_scope_mode, array_values(array_map('intval', $agreement->programs->modelKeys())));
        $validator->validate();

        $existingRoster = $agreement->certificationCandidates()->get()->keyBy('id');
        $sortOrder = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $rosterLink = $rowId !== null ? $existingRoster->get($rowId) : null;

            if (filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $rosterLink?->delete();

                continue;
            }

            $candidateId = isset($row['certification_candidate_id']) && $row['certification_candidate_id'] !== ''
                ? (int) $row['certification_candidate_id']
                : null;
            $name = trim((string) ($row['name'] ?? ''));
            $assignmentRows = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
            $hasAssignmentContent = collect($assignmentRows)->contains(fn ($a) => is_array($a) && filled($a['certificate_id'] ?? null) && ! filter_var($a['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN));

            if ($candidateId === null && $name === '' && ! $hasAssignmentContent) {
                continue;
            }

            $candidate = $candidateId !== null ? $candidates->get($candidateId) : null;

            if ($rosterLink === null && $candidate !== null) {
                // Defensive reuse: validation already rejects a second row for an already-linked
                // candidate, but fall back to the existing link rather than a raw unique-constraint
                // exception if one slips through (e.g. a concurrent request).
                $rosterLink = $existingRoster->first(fn (AgreementCertificationCandidate $link) => (int) $link->certification_candidate_id === $candidate->id);
            }

            try {
                $candidate = DB::transaction(function () use ($candidate, $name, $row) {
                    if ($candidate !== null) {
                        $this->applyIdentityEditIfChanged(
                            $candidate,
                            array_key_exists('name', $row) ? $name : $candidate->name,
                            array_key_exists('email', $row) ? (string) $row['email'] : (string) $candidate->email,
                        );

                        return $candidate;
                    }

                    return CertificationCandidate::create([
                        'name' => $name,
                        'email' => (string) ($row['email'] ?? ''),
                        'active' => true,
                    ]);
                });
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    "certification_candidates.{$index}.email" => 'That email is already in use by another candidate.',
                ]);
            }

            if ($rosterLink === null) {
                $rosterLink = $agreement->certificationCandidates()->firstOrCreate([
                    'certification_candidate_id' => $candidate->id,
                ], [
                    'program_id' => null,
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $sortOrder,
                ]);
            } else {
                $rosterLink->update([
                    'certification_candidate_id' => $candidate->id,
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $sortOrder,
                ]);
            }

            $sortOrder++;

            foreach ($assignmentRows as $assignmentRow) {
                if (! is_array($assignmentRow)) {
                    continue;
                }

                $certificateId = isset($assignmentRow['certificate_id']) && $assignmentRow['certificate_id'] !== ''
                    ? (int) $assignmentRow['certificate_id']
                    : null;

                if ($certificateId === null) {
                    continue;
                }

                $existingEnrollment = CertificateEnrollment::query()
                    ->where('certification_candidate_id', $candidate->id)
                    ->where('certificate_id', $certificateId)
                    ->first();

                if (filter_var($assignmentRow['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    if ($existingEnrollment !== null && $existingEnrollment->isUnstarted()) {
                        $existingEnrollment->delete();
                    }

                    continue;
                }

                if ($existingEnrollment !== null) {
                    $coachUserId = filled($assignmentRow['coach_user_id'] ?? null) ? (int) $assignmentRow['coach_user_id'] : null;
                    if (filled($assignmentRow['enrollment_id'] ?? null)
                        && array_key_exists('coach_user_id', $assignmentRow)
                        && $coachUserId !== $existingEnrollment->supervising_coach_user_id) {
                        $existingEnrollment->update([
                            'supervising_coach_user_id' => $coachUserId,
                        ]);
                    }

                    continue;
                }

                $coachUserId = isset($assignmentRow['coach_user_id']) && $assignmentRow['coach_user_id'] !== ''
                    ? (int) $assignmentRow['coach_user_id']
                    : null;

                CertificateEnrollment::query()->firstOrCreate([
                    'certification_candidate_id' => $candidate->id,
                    'certificate_id' => $certificateId,
                ], [
                    'sponsoring_agreement_id' => $agreement->id,
                    'supervising_coach_user_id' => $coachUserId,
                    'status' => CertificateEnrollmentStatus::NotStarted,
                    'phase' => CertificateRequirementPhase::Initial,
                    'cycle' => 1,
                    'enrolled_at' => now(),
                ]);
            }
        }
    }

    private function validateIdentityEdit(Validator $validator, User $actor, CertificationCandidate $candidate, string $name, string $emailRaw, int|string $index): void
    {
        $normalizedEmail = $this->normalizeEmail($emailRaw);
        $changed = $name !== $candidate->name || $normalizedEmail !== $candidate->email;

        if (! $changed) {
            return;
        }

        if (! $this->canEditCandidate($actor, $candidate)) {
            $validator->errors()->add("certification_candidates.{$index}.certification_candidate_id", 'You do not have authority to edit this candidate\'s shared identity.');

            return;
        }

        if ($name === '') {
            $validator->errors()->add("certification_candidates.{$index}.name", 'Enter a name to update this candidate.');
        }

        if ($normalizedEmail === null) {
            $validator->errors()->add("certification_candidates.{$index}.email", 'Enter an email to update this candidate.');

            return;
        }

        if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $validator->errors()->add("certification_candidates.{$index}.email", 'Enter a valid email address.');

            return;
        }

        $conflict = CertificationCandidate::query()
            ->where('email', $normalizedEmail)
            ->whereKeyNot($candidate->id)
            ->exists();

        if ($conflict) {
            $validator->errors()->add("certification_candidates.{$index}.email", 'That email is already in use by another candidate.');
        }
    }

    private function applyIdentityEditIfChanged(CertificationCandidate $candidate, string $name, string $emailRaw): void
    {
        $normalizedEmail = $this->normalizeEmail($emailRaw);
        $changed = ($name !== '' && $name !== $candidate->name) || $normalizedEmail !== $candidate->email;

        if (! $changed) {
            return;
        }

        $candidate->update([
            'name' => $name !== '' ? $name : $candidate->name,
            'email' => $normalizedEmail,
        ]);
    }

    private function normalizeEmail(string $raw): ?string
    {
        $trimmed = strtolower(trim($raw));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Shared identity edits affect every linked agreement, so they require authority over every
     * agreement the candidate is already linked to (or system admin).
     */
    public function canEditCandidate(User $actor, CertificationCandidate $candidate): bool
    {
        if ($actor->access()->isSystemAdmin()) {
            return true;
        }

        $candidate->loadMissing('rosterLinks.agreement.programs');
        $agreements = $candidate->rosterLinks->pluck('agreement')->filter()->unique('id');

        if ($agreements->isEmpty()) {
            return false;
        }

        return $agreements->every(fn (Agreement $agreement) => $actor->can('update', $agreement));
    }

    public function canManageEnrollment(User $actor, CertificateEnrollment $enrollment): bool
    {
        if ($actor->access()->isSystemAdmin()) {
            return true;
        }

        $candidate = $enrollment->candidate;
        if ($candidate === null || ! $this->canEditCandidate($actor, $candidate)) {
            return false;
        }

        $certificate = $enrollment->certificate;
        if ($certificate === null || ($certificate->program_scope_mode !== ProgramScopeMode::All && ! $actor->can('update', $certificate))) {
            return false;
        }

        $sponsor = $enrollment->sponsoringAgreement;

        return $sponsor === null || $actor->can('update', $sponsor);
    }
}
