<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    // Index: everyone who can sign in (Input included).
    public function viewAny(User $user): bool
    {
        return true;
    }

    // View: you logged it or are Delivered By (or a supervisee of yours is). Viewers also see logs on agreements they can view.
    public function view(User $user, Activity $activity): bool
    {
        return $user->access()->canViewActivity($activity);
    }

    // Create: everyone who can sign in can log an activity.
    public function create(User $user): bool
    {
        return true;
    }

    // Edit: you logged it, you are a participant, or the activity itself is on a program/project you admin.
    public function update(User $user, Activity $activity): bool
    {
        return $user->access()->canUpdateActivity($activity);
    }

    // Delete: you logged it, or the activity itself is on a program/project you admin.
    public function delete(User $user, Activity $activity): bool
    {
        return $user->access()->canDeleteActivity($activity);
    }

    // Duplicate: same as edit, plus you can create activities.
    public function duplicate(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity) && $this->create($user);
    }

    // Action log: owner, supervisor of owner, or admin/enhanced viewer on a program in view scope.
    public function viewActionLog(User $user, Activity $activity): bool
    {
        return $user->access()->canViewActivityActionLog($activity);
    }
}
