<?php

namespace App\Support;

use App\Models\User;

class UserProfileLink
{
    public static function route(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $auth = auth()->user();

        if (!$auth) {
            return null;
        }

        return $auth->can('view', $user) ? route('users.show', $user) : null;
    }
}
