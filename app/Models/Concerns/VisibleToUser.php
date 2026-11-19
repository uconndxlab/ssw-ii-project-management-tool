<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\SubActivity;
use App\Models\Team;
use App\Models\User;
use App\Support\Authorization\HydratedRecordIds;
use App\Support\Authorization\UserAccess;
use Illuminate\Database\Eloquent\Builder;

trait VisibleToUser
{
    /**
     * Collects the id of every model of this type Eloquent loads, so UserAccess can answer all of a page's
     * isLinkable()/view checks for one type in a single query. Collecting is free of access logic; see
     * UserAccess::batchVisibility() and "How link checks are batched" in docs/access-rules.md.
     */
    public static function bootVisibleToUser(): void
    {
        static::retrieved(function (self $record): void {
            if ($record->getKey() !== null) {
                app(HydratedRecordIds::class)->add($record::class, (int) $record->getKey());
            }
        });
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
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
            CertificationCandidate::class => $access->applyCertificationCandidateVisibility($query),
            ContactFamily::class,
            LoggingField::class,
            ActivityType::class,
            SubActivity::class,
            CertificationInstrument::class,
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
