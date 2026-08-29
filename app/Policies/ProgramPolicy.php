<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    // Index: anyone who is not an Input user.
    public function viewAny(User $user): bool
    {
        return $user->access()->canViewPrimaryNav();
    }

    // View: you belong (you or your team assigned) or you have view/admin privilege on this program or its parent project.
    public function view(User $user, Program $program): bool
    {
        return $user->access()->canViewRecord($program);
    }

    // Create: system admin, or you admin at least one project (programs sit under projects).
    public function create(User $user): bool
    {
        $access = $user->access();

        return $access->isSystemAdmin() || $access->adminProjectIds() !== [];
    }

    // Edit: you admin this program or a parent project.
    public function update(User $user, Program $program): bool
    {
        return $user->access()->canUpdateScopedRecord($program);
    }

    // Delete: every parent project is in your admin scope.
    public function delete(User $user, Program $program): bool
    {
        return $user->access()->canDeleteScopedRecord($program);
    }
}
