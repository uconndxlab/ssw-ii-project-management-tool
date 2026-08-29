<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Authorization\UserAccess;
use Illuminate\Database\Eloquent\Builder;

trait VisibleToUser
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $access = UserAccess::for($user);
        $model = $query->getModel();

        return match ($model::class) {
            \App\Models\Project::class => $access->applyProjectVisibility($query),
            \App\Models\Program::class => $access->applyProgramVisibility($query),
            \App\Models\Team::class => $access->applyTeamVisibility($query),
            \App\Models\Agreement::class => $access->applyAgreementVisibility($query),
            \App\Models\Organization::class => $access->applyOrganizationVisibility($query),
            \App\Models\State::class => $access->applyStateVisibility($query),
            \App\Models\Activity::class => $access->applyActivityVisibility($query),
            \App\Models\User::class => $access->applyUserIndexVisibility($query),
            \App\Models\ContactFamily::class,
            \App\Models\LoggingField::class,
            \App\Models\ActivityType::class => $access->applyScopedEntityVisibility($query),
            default => $query,
        };
    }

    public function isLinkable(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->can('view', $this);
    }
}
