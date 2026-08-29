<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class AgreementPolicy
{
    use AuthorizesScopedEntity;

    // Create: any admin.
    // Edit: you admin a program/project assigned on it. "All programs" is system admin only.
    // Delete: every assigned program is in your admin scope (a shared agreement stays if you only admin part of it).
    // No programs/projects: system admin only.

    // View: you belong to it (you or your team) or you have view/admin privilege on a program/project assigned on it.
    // Inactive agreements: members who belong can still open active ones; inactive requires view privilege.
    public function view(User $user, Agreement $agreement): bool
    {
        if (! $user->access()->canViewRecord($agreement)) {
            return false;
        }

        if ($agreement->active) {
            return true;
        }

        return $user->access()->hasView();
    }

    // Duplicate: same as edit, plus you can create agreements.
    public function duplicate(User $user, Agreement $agreement): bool
    {
        return $this->update($user, $agreement) && $this->create($user);
    }
}
