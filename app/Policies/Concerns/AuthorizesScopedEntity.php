<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesScopedEntity
{
    // Index: anyone who is not an Input user.
    public function viewAny(User $user): bool
    {
        return $user->access()->canViewPrimaryNav();
    }

    // View: belong to it (you or your team) or a program/project on it is in your view/admin privilege.
    // "All programs" does not count as overlapping every admin — that is system-wide.
    public function view(User $user, Model $record): bool
    {
        return $user->access()->canViewRecord($record);
    }

    // Create: any admin (project, program, or system).
    public function create(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    // Edit: you admin a program/project actually assigned on the record.
    public function update(User $user, Model $record): bool
    {
        return $user->access()->canUpdateScopedRecord($record);
    }

    // Delete: every program/project on the record is in your admin scope.
    // No programs/projects (and All) are system admin only — empty is not inside a project admin.
    public function delete(User $user, Model $record): bool
    {
        return $user->access()->canDeleteScopedRecord($record);
    }
}
