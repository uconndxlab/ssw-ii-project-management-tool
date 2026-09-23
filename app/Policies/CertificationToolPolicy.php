<?php

namespace App\Policies;

use App\Models\CertificationTool;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class CertificationToolPolicy
{
    use AuthorizesScopedEntity;

    // Index: admins only (not enhanced viewers).
    public function viewAny(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    // View: admin, and a program on the tool is in your view/admin privilege.
    public function view(User $user, CertificationTool $certificationTool): bool
    {
        return $user->access()->hasAdmin() && $user->access()->canViewRecord($certificationTool);
    }
}
