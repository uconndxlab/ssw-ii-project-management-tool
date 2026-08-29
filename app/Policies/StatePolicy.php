<?php

namespace App\Policies;

use App\Models\State;
use App\Models\User;

class StatePolicy
{
    // Index: anyone who is not an Input user.
    public function viewAny(User $user): bool
    {
        return $user->access()->canViewPrimaryNav();
    }

    // View: you can view an agreement in this state (membership or privilege on that agreement).
    public function view(User $user, State $state): bool
    {
        return $user->access()->canViewRecord($state);
    }

    // Create/edit/delete: system admin only.
    public function create(User $user): bool
    {
        return $user->access()->isSystemAdmin();
    }

    public function update(User $user, State $state): bool
    {
        return $user->access()->isSystemAdmin();
    }

    public function delete(User $user, State $state): bool
    {
        return $user->access()->isSystemAdmin();
    }
}
