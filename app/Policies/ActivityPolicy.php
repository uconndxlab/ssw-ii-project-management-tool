<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\User;

class ActivityPolicy
{
    public function view(User $user, Activity $activity): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $activity->loadMissing('agreements');

        $hasAgreementAccess = $activity->agreements->contains(
            fn (Agreement $agreement) => $user->hasAccessToAgreement($agreement),
        );

        return $hasAgreementAccess && (int) $activity->user_id === (int) $user->id;
    }
}
