<?php

namespace App\Policies;

use App\Models\CertificationRole;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

/**
 * A role's scope is its certificate's scope (null certificate_id = global/standard role).
 */
class CertificationRolePolicy
{
    use AuthorizesScopedEntity;

    public function viewAny(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    public function view(User $user, CertificationRole $certificationRole): bool
    {
        if (! $user->access()->hasAdmin()) {
            return false;
        }

        if ($certificationRole->certificate_id === null) {
            return true;
        }

        return $user->access()->canViewRecord($certificationRole->certificate);
    }

    public function update(User $user, CertificationRole $certificationRole): bool
    {
        if ($certificationRole->certificate_id === null) {
            return $user->access()->isSystemAdmin();
        }

        return $user->access()->canUpdateScopedRecord($certificationRole->certificate);
    }

    public function delete(User $user, CertificationRole $certificationRole): bool
    {
        if ($certificationRole->certificate_id === null) {
            return $user->access()->isSystemAdmin();
        }

        return $user->access()->canDeleteScopedRecord($certificationRole->certificate);
    }
}
