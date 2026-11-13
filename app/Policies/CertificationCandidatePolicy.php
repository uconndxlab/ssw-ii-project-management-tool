<?php

namespace App\Policies;

use App\Models\CertificationCandidate;
use App\Models\User;
use App\Services\CertificationAssignmentSyncService;

class CertificationCandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->access()->isInput();
    }

    public function view(User $user, CertificationCandidate $candidate): bool
    {
        return $user->access()->canViewRecord($candidate);
    }

    public function update(User $user, CertificationCandidate $candidate): bool
    {
        return app(CertificationAssignmentSyncService::class)->canEditCandidate($user, $candidate);
    }
}
