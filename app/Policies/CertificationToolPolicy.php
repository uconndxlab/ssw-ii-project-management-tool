<?php

namespace App\Policies;

use App\Models\CertificationTool;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class CertificationToolPolicy
{
    use AuthorizesScopedEntity;

    public function viewAny(User $user): bool
    {
        return $user->access()->hasView();
    }

    public function view(User $user, CertificationTool $certificationTool): bool
    {
        return $user->access()->hasView() && $user->access()->canViewRecord($certificationTool);
    }
}
