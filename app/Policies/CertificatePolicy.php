<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class CertificatePolicy
{
    use AuthorizesScopedEntity;

    // Index: admins only (not enhanced viewers).
    public function viewAny(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    // View: admin, and a program on the certificate is in your view/admin privilege.
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->access()->hasAdmin() && $user->access()->canViewRecord($certificate);
    }
}
