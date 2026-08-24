<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;

class AgreementPolicy
{
    public function view(User $user, Agreement $agreement): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $agreement->active && $user->hasAccessToAgreement($agreement);
    }
}
