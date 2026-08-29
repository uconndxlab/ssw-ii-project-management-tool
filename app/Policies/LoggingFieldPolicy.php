<?php

namespace App\Policies;

use App\Models\LoggingField;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class LoggingFieldPolicy
{
    use AuthorizesScopedEntity;

    // Create: any admin.
    // Edit: you admin a program assigned on it.
    // Delete: every assigned program is in your admin scope. No programs: system admin only.

    // Index: admins only (not enhanced viewers).
    public function viewAny(User $user): bool
    {
        return $user->access()->hasAdmin();
    }

    // View: admin, and a program on the field is in your view/admin privilege.
    public function view(User $user, LoggingField $loggingField): bool
    {
        return $user->access()->hasAdmin() && $user->access()->canViewRecord($loggingField);
    }
}
