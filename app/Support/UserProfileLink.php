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

        if ($auth->isAdmin()) {
            return route('users.show', $user);
        }

        if ((int) $auth->id === (int) $user->id) {
            return route('profile');
        }

        return null;
    }
}
