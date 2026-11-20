<?php

namespace App\Support\Authorization;

use App\Enums\ProgramScopeMode;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\Program;
use App\Models\User;

class CertificateProgressAccess
{
    public function canView(User $actor, CertificateEnrollment $enrollment): bool
    {
        return Certificate::query()->visibleTo($actor)->whereKey($enrollment->certificate_id)->exists()
            && Agreement::query()->visibleTo($actor)
                ->whereHas('certificationCandidates', fn ($query) => $query->where('certification_candidate_id', $enrollment->certification_candidate_id))
                ->exists();
    }

    /** @return array<int> */
    public function programIds(Certificate $certificate): array
    {
        return $certificate->program_scope_mode === ProgramScopeMode::All
            ? Program::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : ($certificate->program_scope_mode === ProgramScopeMode::None ? [] : $certificate->programs->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
    }

    public function canViewDetails(User $actor, CertificateEnrollment $enrollment): bool
    {
        if (! $this->canView($actor, $enrollment)) {
            return false;
        }
        $certificate = $enrollment->certificate;
        if ($certificate === null) {
            return false;
        }
        $ids = $this->programIds($certificate);

        return $actor->isSystemAdmin()
            || array_intersect($ids, $actor->access()->adminProgramIds()) !== []
            || array_intersect($ids, $actor->certification()->coachProgramIds()) !== []
            || array_intersect($ids, $actor->certification()->managerProgramIds()) !== []
            || array_intersect($ids, $actor->certification()->coachProgramIdsViaSupervisee($enrollment)) !== [];
    }

    public function canConfirm(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $enrollment->certificate !== null && $this->canView($actor, $enrollment)
            && array_intersect($this->programIds($enrollment->certificate), array_merge($actor->certification()->coachProgramIds(), $actor->certification()->coachProgramIdsViaSupervisee($enrollment))) !== [];
    }

    /**
     * Lifecycle duties are checked here rather than in a policy so the system-admin Gate::before
     * bypass never grants coach judgment or manager authority. Manual history is an admin-only exception.
     */
    public function canEndorse(User $actor, CertificateEnrollment $enrollment): bool
    {
        // A supervisor endorses for their direct reports: documented client decision, see docs/access-rules.md.
        return in_array($enrollment->supervising_coach_user_id, $actor->access()->activityReferenceUserIds(), true)
            && $this->canConfirm($actor, $enrollment);
    }

    public function canRecordHistory(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $this->historyBlocker($actor, $enrollment) === null;
    }

    public function historyBlocker(User $actor, CertificateEnrollment $enrollment): ?string
    {
        if ($actor->access()->isInput()) {
            return 'Input users cannot record award history.';
        }
        if ($actor->isSystemAdmin()) {
            return null;
        }
        $enrollment->loadMissing(['candidate.rosterLinks.agreement.programs', 'certificate.programs', 'sponsoringAgreement.programs']);
        $agreements = $enrollment->candidate?->rosterLinks->pluck('agreement')->filter()->unique('id') ?? collect();
        if ($agreements->isEmpty()) {
            return 'This person is not on an agreement you administer.';
        }
        $blocking = $agreements->reject(fn (Agreement $agreement) => $actor->access()->canDeleteScopedRecord($agreement));
        if ($blocking->isNotEmpty()) {
            $names = $blocking->map(fn (Agreement $agreement) => $actor->can('view', $agreement) ? '“'.$agreement->name.'”' : 'another agreement')->unique()->join(', ', ' and ');

            return 'This person is also on '.$names.', which you don’t fully administer. Ask an admin for every program on every agreement.';
        }
        $certificate = $enrollment->certificate;
        if ($certificate === null || ($certificate->program_scope_mode !== ProgramScopeMode::All && ! $actor->can('update', $certificate))) {
            return 'You can’t edit the '.($certificate->name ?? 'assigned').' certificate.';
        }
        if ($enrollment->sponsoringAgreement !== null && ! $actor->access()->canDeleteScopedRecord($enrollment->sponsoringAgreement)) {
            return 'The sponsoring agreement is not fully within your admin scope.';
        }

        return null;
    }

    public function canManage(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $enrollment->certificate !== null && $this->canView($actor, $enrollment)
            && array_intersect($this->programIds($enrollment->certificate), $actor->certification()->managerProgramIds()) !== [];
    }
}
