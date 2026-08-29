<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    // Index: anyone who is not an Input user.
    public function viewAny(User $user): bool
    {
        return $user->access()->canViewPrimaryNav();
    }

    // View: you belong via membership (you/team on a child program) or you have view/admin privilege on this project.
    public function view(User $user, Project $project): bool
    {
        return $user->access()->canViewRecord($project);
    }

    // Create/edit/delete: system admin only. Project admins manage programs under the project, not the project row.
    public function create(User $user): bool
    {
        return $user->access()->isSystemAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->access()->isSystemAdmin();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->access()->isSystemAdmin();
    }
}
