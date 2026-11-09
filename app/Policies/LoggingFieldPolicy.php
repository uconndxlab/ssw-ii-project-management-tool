<?php

namespace App\Policies;

use App\Models\LoggingField;
use App\Models\User;
use App\Policies\Concerns\AuthorizesScopedEntity;

class LoggingFieldPolicy
{
    use AuthorizesScopedEntity {
        delete as private deleteScope;
    }

    // Create: any admin.
    // View: overlap is enough. Edit/delete: every assigned program is in your admin scope
    // (No programs or All: system admin only), plus edit access to every attached record.
    public function update(User $user, LoggingField $loggingField): bool
    {
        return $this->delete($user, $loggingField);
    }

    public function delete(User $user, LoggingField $loggingField): bool
    {
        return $this->deleteScope($user, $loggingField) && $this->updateAttachments($user, $loggingField);
    }

    // Includes inactive agreements: they still use this field.
    public function updateAttachments(User $user, LoggingField $loggingField): bool
    {
        $loggingField->loadMissing(['agreements.programs.projects', 'contactFamilies.programs.projects', 'activityTypes.programs.projects']);

        foreach ([$loggingField->agreements, $loggingField->contactFamilies, $loggingField->activityTypes] as $records) {
            foreach ($records as $record) {
                if (! $user->can('update', $record)) {
                    return false;
                }
            }
        }

        return true;
    }

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
