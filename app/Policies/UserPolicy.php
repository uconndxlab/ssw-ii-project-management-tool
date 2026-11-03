<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Index: enhanced viewers/admins, or supervisors (supervisees list).
    // Members cannot browse other users.
    public function viewAny(User $user): bool
    {
        return $user->access()->hasView() || $user->access()->isSupervisor();
    }

    // View: yourself is profile; others if you are a viewer/admin in overlapping membership, or they report to you.
    public function view(User $user, User $model): bool
    {
        return $user->access()->canViewUser($model);
    }

    // Create: any admin.
    public function create(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    // Edit: yourself only as a system admin (permissions stay locked). Their admin privileges must sit inside yours. Non-sysadmin can only edit people in overlapping membership.
    public function update(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return $user->access()->isSystemAdmin();
        }

        $access = $user->access();

        if (! $access->hasAdmin()) {
            return false;
        }

        if (! $access->targetAdminFullyWithin($model)) {
            return false;
        }

        if ($access->isSystemAdmin()) {
            return true;
        }

        return $access->userIsInViewMembership($model);
    }

    // Delete: same as edit, never yourself, and you cannot remove the last system admin.
    public function delete(User $user, User $model): bool
    {
        if ($user->is($model) || ! $this->update($user, $model)) {
            return false;
        }

        return ! $user->access()->lastActiveSystemAdminWouldBeRemoved($model);
    }

    // Supervisees index: supervisor flag, not Input.
    public function viewSupervisees(User $user): bool
    {
        return $user->access()->isSupervisor();
    }
}
