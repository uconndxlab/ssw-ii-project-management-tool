<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class CertificatePolicy
{
    use AuthorizesScopedEntity;

    public function viewAny(User $user): bool
    {
        return ! $user->access()->isInput() && ($user->access()->hasView() || $user->certification()->isCoach() || $user->certification()->isManager());
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return ! $user->access()->isInput()
            && (($user->access()->hasView() && $user->access()->canViewRecord($certificate))
                || $user->certification()->canViewCertificate($certificate));
    }
}
