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
            || array_intersect($ids, $actor->certification()->managerProgramIds()) !== [];
    }

    public function canConfirm(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $enrollment->certificate !== null && $this->canView($actor, $enrollment)
            && array_intersect($this->programIds($enrollment->certificate), $actor->certification()->coachProgramIds()) !== [];
    }

    /**
     * Lifecycle duties are checked here rather than in a policy so the system-admin Gate::before
     * bypass never grants coach judgment or manager authority.
     */
    public function canEndorse(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $enrollment->supervising_coach_user_id === $actor->id && $this->canConfirm($actor, $enrollment);
    }

    public function canManage(User $actor, CertificateEnrollment $enrollment): bool
    {
        return $enrollment->certificate !== null && $this->canView($actor, $enrollment)
            && array_intersect($this->programIds($enrollment->certificate), $actor->certification()->managerProgramIds()) !== [];
    }
}
