<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\ActivityActionLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ActivityActionLogService
{
    public function record(
        Activity $activity,
        ActivityAction $action,
        ?Activity $related = null,
        ?User $user = null,
    ): ActivityActionLog {
        return ActivityActionLog::query()->create([
            'activity_id' => $activity->id,
            'user_id' => ($user ?? Auth::user())?->id,
            'action' => $action,
            'related_activity_id' => $related?->id,
        ]);
    }
}
