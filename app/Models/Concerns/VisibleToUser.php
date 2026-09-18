<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificationTool;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\Team;
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
            Project::class => $access->applyProjectVisibility($query),
            Program::class => $access->applyProgramVisibility($query),
            Team::class => $access->applyTeamVisibility($query),
            Agreement::class => $access->applyAgreementVisibility($query),
            Organization::class => $access->applyOrganizationVisibility($query),
            State::class => $access->applyStateVisibility($query),
            Activity::class => $access->applyActivityVisibility($query),
            User::class => $access->applyUserIndexVisibility($query),
            ContactFamily::class,
            LoggingField::class,
            ActivityType::class,
            CertificationTool::class,
            Certificate::class => $access->applyScopedEntityVisibility($query),
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
