<?php

namespace App\Support\Authorization;

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\ContactFamily;
use App\Models\Contracts\HasPrograms;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\SubActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class UserAccess
{
    /** @var \WeakMap<User, self>|null */
    private static ?\WeakMap $cache = null;

    /** @var list<int>|null */
    private ?array $adminProjectIds = null;

    /** @var list<int>|null */
    private ?array $adminProgramIds = null;

    /** @var list<int>|null */
    private ?array $viewProjectIds = null;

    /** @var list<int>|null */
    private ?array $viewProgramIds = null;

    /** @var array<int, int>|null */
    private ?array $directReportIds = null;

    /** @var array<int, bool> */
    private array $certificationReviewerActivityIds = [];

    /** @var array<int, bool> */
    private array $visibleActivityIds = [];

    /** @var array<int, bool> */
    private array $visibleUserIds = [];

    /** @var array<int, bool> */
    private array $activityActionLogScopeIds = [];

    /** @var Collection<int, UserPrivilege>|null */
    private ?Collection $privileges = null;

    /** @var array<class-string<Model>, array<int, bool>> */
    private array $recordVisibilityCache = [];

    /** @var array<int, bool> */
    private array $privilegeVisibleAgreementIds = [];

    public function __construct(private User $user) {}

    public static function for(User $user): self
    {
        self::$cache ??= new \WeakMap;

        return self::$cache[$user] ??= new self($user);
    }

    // get user profile, default to member
    public function profile(): AccessProfile
    {
        return $this->user->access_profile ?? AccessProfile::Member;
    }

    public function isInput(): bool
    {
        return $this->profile() === AccessProfile::Input;
    }

    public function isMember(): bool
    {
        return $this->profile() === AccessProfile::Member;
    }

    public function isAdminViewer(): bool
    {
        return $this->profile() === AccessProfile::AdminViewer;
    }

    public function isSupervisor(): bool
    {
        return ! $this->isInput() && (bool) $this->user->is_supervisor;
    }

    public function isSystemAdmin(): bool
    {
        return $this->isAdminViewer() && $this->hasPrivilege(PrivilegeCapability::Admin, PrivilegeScopeType::System);
    }

    public function hasSystemView(): bool
    {
        if (! $this->isAdminViewer()) {
            return false;
        }

        return $this->hasPrivilege(PrivilegeCapability::Admin, PrivilegeScopeType::System)
            || $this->hasPrivilege(PrivilegeCapability::View, PrivilegeScopeType::System);
    }

    public function hasAdmin(): bool
    {
        if (! $this->isAdminViewer()) {
            return false;
        }

        return $this->privileges()->contains(
            fn (UserPrivilege $privilege) => $privilege->capability === PrivilegeCapability::Admin,
        );
    }

    public function hasView(): bool
    {
        return $this->isAdminViewer() && $this->privileges()->isNotEmpty();
    }

    public function canViewPrimaryNav(): bool
    {
        return ! $this->isInput();
    }

    /**
     * @return list<int>
     */
    public function adminProjectIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->adminProjectIds ?? [];
    }

    /**
     * @return list<int>
     */
    public function adminProgramIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->adminProgramIds ?? [];
    }

    /**
     * @return list<int>
     */
    public function viewProjectIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->viewProjectIds ?? [];
    }

    /**
     * @return list<int>
     */
    public function viewProgramIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->viewProgramIds ?? [];
    }

    /**
     * Get report ids for supervisor, cache.
     *
     * @return array<int, int>
     */
    public function directReportIds(): array
    {
        if ($this->directReportIds !== null) {
            return $this->directReportIds;
        }

        if (! $this->isSupervisor()) {
            return $this->directReportIds = [];
        }

        return $this->directReportIds = array_values(User::query()
            ->where('supervisor_id', $this->user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * "I belong": current user (unless Input) plus direct reports if supervisor.
     *
     * @return list<int>
     */
    public function membershipVisibilityUserIds(): array
    {
        $ids = [];

        if (! $this->isInput()) {
            $ids[] = (int) $this->user->id;
        }

        if ($this->isSupervisor()) {
            $ids = array_merge($ids, $this->directReportIds());
        }

        return array_values(array_unique($ids));
    }

    public function adminsProgram(int $programId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return in_array($programId, $this->adminProgramIds(), true);
    }

    public function adminsProject(int $projectId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        return in_array($projectId, $this->adminProjectIds(), true);
    }

    public function canGrant(PrivilegeCapability $capability, PrivilegeScopeType $scopeType, ?int $scopeId): bool
    {
        if ($this->isInput() || ! $this->hasAdmin()) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($scopeType === PrivilegeScopeType::System) {
            return false;
        }

        if ($scopeType === PrivilegeScopeType::Project) {
            return $scopeId !== null && $this->adminsProject($scopeId);
        }

        return $scopeId !== null && $this->adminsProgram($scopeId);
    }

    /**
     * Target has no admin privilege outside the actor's admin scope.
     */
    public function targetAdminFullyWithin(User $target): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($target->is($this->user)) {
            return false;
        }

        $targetAccess = self::for($target);

        if ($targetAccess->isSystemAdmin()) {
            return false;
        }

        foreach ($targetAccess->privileges() as $privilege) {
            if ($privilege->capability !== PrivilegeCapability::Admin) {
                continue;
            }

            if ($privilege->scope_type === PrivilegeScopeType::System) {
                return false;
            }

            if ($privilege->scope_type === PrivilegeScopeType::Project
                && ! $this->adminsProject((int) $privilege->scope_id)) {
                return false;
            }

            if ($privilege->scope_type === PrivilegeScopeType::Program
                && ! $this->adminsProgram((int) $privilege->scope_id)) {
                return false;
            }
        }

        return true;
    }

    public function lastActiveSystemAdminWouldBeRemoved(User $target): bool
    {
        if (! self::for($target)->isSystemAdmin() || ! $target->isActive()) {
            return false;
        }

        return ! User::query()
            ->where('active', true)
            ->where('access_profile', AccessProfile::AdminViewer->value)
            ->whereKeyNot($target->id)
            ->whereHas('privileges', function (Builder $query) {
                $query->where('capability', PrivilegeCapability::Admin->value)
                    ->where('scope_type', PrivilegeScopeType::System->value);
            })
            ->exists();
    }

    /**
     * Edit overlap: you admin a program/project actually on the record. All-mode is system admin only.
     *
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     */
    public function scopedRecordOverlapsAdmin(?ProgramScopeMode $mode, array $programIds, array $projectIds = []): bool
    {
        if (! $this->hasAdmin()) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($mode === ProgramScopeMode::Specific && array_intersect($programIds, $this->adminProgramIds()) !== []) {
            return true;
        }

        if ($projectIds !== [] && array_intersect($projectIds, $this->adminProjectIds()) !== []) {
            return true;
        }

        return false;
    }

    /**
     * Delete: every program/project on the record is in your admin scope.
     * All-mode and no-scope (none / empty) are system admin only — empty is not "inside" a project admin.
     *
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     */
    public function scopedRecordFullyWithinAdmin(?ProgramScopeMode $mode, array $programIds, array $projectIds = []): bool
    {
        if (! $this->hasAdmin()) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($mode === ProgramScopeMode::All || $mode === ProgramScopeMode::None) {
            return false;
        }

        if ($projectIds !== []) {
            foreach ($projectIds as $projectId) {
                if (! $this->adminsProject((int) $projectId)) {
                    return false;
                }
            }

            return true;
        }

        if ($programIds === []) {
            return false;
        }

        foreach ($programIds as $programId) {
            if (! $this->adminsProgram((int) $programId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyProjectVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $this->orPrivilegeProjectOverlap($builder);
            $this->orMembershipProjectOverlap($builder);
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyProgramVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $this->orPrivilegeProgramOverlap($builder);
            $this->orMembershipProgramOverlap($builder);
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyScopedEntityVisibility(Builder $query, string $programsRelation = 'programs'): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        $model = $query->getModel();

        // scope visibility query on programs access, or membership
        return $query->where(function (Builder $builder) use ($programsRelation, $model) {
            // Certificates retain their original empty-group visibility for enrollment summaries and progress.
            if (! $model instanceof Certificate) {
                $builder->whereRaw('1 = 0');
            }
            if ($this->hasView()) {
                $programIds = $this->viewProgramIds();

                if ($programIds !== []) {
                    $builder->orWhereHas($programsRelation, fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
                }
            }

            $this->orMembershipForScopedEntity($builder, $model);
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyTeamVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $this->orPrivilegeScopedOverlap($builder);
            $memberIds = $this->membershipVisibilityUserIds();
            if ($memberIds !== []) {
                $builder->orWhereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds));
            }
        });
    }

    /**
     * Agreements you belong to (self/team) or that list a program in your view/admin privilege.
     * or agreements supervisees belong to
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyAgreementVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $builder->whereRaw('1 = 0');
            foreach ($this->agreementVisibilityPredicates() as $predicate) {
                $builder->orWhere($predicate);
            }
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyOrganizationVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $this->orPrivilegeScopedOverlap($builder);
            $memberIds = $this->membershipVisibilityUserIds();
            if ($memberIds !== []) {
                $builder->orWhereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds));
            }
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyStateVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $builder->orWhereHas('agreements', function (Builder $agreementQuery) {
                $this->applyAgreementVisibility($agreementQuery);
            });
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyActivityVisibility(Builder $query): Builder
    {
        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $builder->whereRaw('1 = 0');
            foreach ($this->activityVisibilityPredicates() as $predicate) {
                $builder->orWhere($predicate);
            }
        });
    }

    /**
     * Shared by list visibility and My role projections. Optional ids narrow a privilege to its named scope.
     *
     * @param  list<int>|null  $programIds
     * @return array<string, \Closure(Builder<covariant Model>): mixed>
     */
    public function activityVisibilityPredicates(?array $programIds = null): array
    {
        $self = [(int) $this->user->id];
        $reports = array_values($this->directReportIds());
        $programIds ??= $this->viewProgramIds();
        $predicates = [
            'Logged' => fn (Builder $query) => $this->activityLoggedBy($query, $self),
            'Delivered by' => fn (Builder $query) => $this->activityDeliveredBy($query, $self),
        ];
        if ($reports !== []) {
            $predicates['Supervisor'] = function (Builder $query) use ($reports) {
                $this->orActivityReferencedBy($query, $reports);
                $this->orCertificationReviewerBy($query, $reports);
            };
        }
        if (! $this->isInput()) {
            $predicates['Reviewer'] = fn (Builder $query) => $this->orCertificationReviewerBy($query, $self);
        }
        if ($this->hasView() && $programIds !== []) {
            $predicates['Privilege'] = function (Builder $query) use ($programIds) {
                $inScope = $this->programOverlapPredicate($programIds);
                $query->whereHas('programs', $inScope)->orWhereHas('agreements.programs', $inScope);
            };
        }

        return $predicates;
    }

    /**
     * @param  list<int>|null  $programIds
     * @return array<string, \Closure(Builder<covariant Model>): mixed>
     */
    public function agreementVisibilityPredicates(?array $programIds = null): array
    {
        if ($this->isInput()) {
            return [];
        }

        $members = $this->membershipVisibilityUserIds();
        $self = array_values(array_intersect($members, [(int) $this->user->id]));
        $reports = array_values(array_diff($members, $self));
        $predicates = [
            'Staff' => fn (Builder $query) => $this->agreementMembers($query, $self, 'users'),
            'Team' => fn (Builder $query) => $this->agreementMembers($query, $self, 'teams.users'),
        ];
        if ($reports !== []) {
            $predicates['Supervisor'] = function (Builder $query) use ($reports) {
                $query->where(fn (Builder $staff) => $this->agreementMembers($staff, $reports, 'users'))
                    ->orWhere(fn (Builder $team) => $this->agreementMembers($team, $reports, 'teams.users'));
            };
        }
        if ($this->hasView()) {
            $predicates['Privilege'] = fn (Builder $query) => $this->orPrivilegeScopedOverlap($query, $programIds);
        }

        return $predicates;
    }

    /** @param Builder<covariant Model> $query
     * @param  list<int>  $userIds
     */
    private function activityLoggedBy(Builder $query, array $userIds): void
    {
        $query->whereIn('activities.user_id', $userIds);
    }

    /** @param Builder<covariant Model> $query
     * @param  list<int>  $userIds
     */
    private function activityDeliveredBy(Builder $query, array $userIds): void
    {
        $query->whereHas('participants', fn (Builder $users) => $users->whereIn('users.id', $userIds));
    }

    /** @param Builder<covariant Model> $query
     * @param  list<int>  $userIds
     */
    private function agreementMembers(Builder $query, array $userIds, string $relation): void
    {
        $query->whereHas($relation, fn (Builder $users) => $users->whereIn('users.id', $userIds));
    }

    /**
     * Candidates on an agreement you can see, or with an enrollment supervised by you or a direct report.
     * Best guess pending the client: duties grant nothing extra here (see docs/access-rules.md).
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyCertificationCandidateVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isSystemAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) {
            $builder->whereHas('rosterLinks.agreement', fn (Builder $agreement) => $this->applyAgreementVisibility($agreement))
                ->orWhereHas('enrollments', fn (Builder $enrollment) => $enrollment->whereIn('supervising_coach_user_id', $this->activityReferenceUserIds()));
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyUserIndexVisibility(Builder $query): Builder
    {
        if ($this->isInput()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isSystemAdmin()) {
            return $query;
        }

        if (! $this->hasView()) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyUsersInViewMembership($query);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applySuperviseesVisibility(Builder $query): Builder
    {
        if (! $this->isSupervisor()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('supervisor_id', $this->user->id);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyUsersInViewMembership(Builder $query): Builder
    {
        if ($this->hasSystemView()) {
            return $query;
        }

        $programIds = $this->viewProgramIds();
        $projectIds = $this->viewProjectIds();

        return $query->where(function (Builder $builder) use ($programIds, $projectIds) {
            $builder->where('users.program_scope_mode', ProgramScopeMode::All->value);

            if ($programIds !== []) {
                $builder->orWhereHas('programs', fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
            }

            if ($projectIds !== []) {
                $builder->orWhereHas('programs.projects', fn (Builder $relation) => $relation->whereIn('projects.id', $projectIds));
            }

            $builder->orWhereHas('teams', function (Builder $teamQuery) use ($programIds, $projectIds) {
                $teamQuery->where(function (Builder $team) use ($programIds, $projectIds) {
                    $team->where('teams.program_scope_mode', ProgramScopeMode::All->value);

                    if ($programIds !== []) {
                        $team->orWhereHas('programs', fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
                    }

                    if ($projectIds !== []) {
                        $team->orWhereHas('programs.projects', fn (Builder $relation) => $relation->whereIn('projects.id', $projectIds));
                    }
                });
            });
        });
    }

    public function userIsInViewMembership(User $target): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if (! $this->hasView()) {
            return false;
        }

        if (isset($this->visibleUserIds[$target->id])) {
            return $this->visibleUserIds[$target->id];
        }

        $this->batchVisibility(User::class, [$target->id], $this->visibleUserIds, $this->applyUsersInViewMembership(User::query()));

        return $this->visibleUserIds[$target->id];
    }

    /**
     * @return list<int>
     */
    public function activityReferenceUserIds(): array
    {
        $ids = [(int) $this->user->id];

        if ($this->isSupervisor()) {
            $ids = array_merge($ids, $this->directReportIds());
        }

        return array_values(array_unique($ids));
    }

    // Members/Input: logger or Delivered By (or a supervisee). Viewers: that, or the activity or its agreement is in their program scope.
    public function canViewActivity(Activity $activity): bool
    {
        if (array_key_exists($activity->id, $this->visibleActivityIds)) {
            return $this->visibleActivityIds[$activity->id];
        }

        $this->primeActivityVisibility([$activity->id]);

        return $this->visibleActivityIds[$activity->id];
    }

    // Edit access, or standing as a certification reviewer. Reviewers may edit only the activity's
    // certification evidence, never the rest of the activity.
    public function canReviewActivityCertification(Activity $activity): bool
    {
        return $this->canUpdateActivity($activity) || $this->isCertificationReviewerOnActivity($activity);
    }

    // Supervising coach of a tagged candidate, or a coach asked to review the activity. A supervisor
    // inherits the standing of their direct reports (documented in docs/access-rules.md).
    public function isCertificationReviewerOnActivity(Activity $activity): bool
    {
        if ($this->isInput()) {
            return false;
        }

        if (! array_key_exists($activity->id, $this->certificationReviewerActivityIds)) {
            $this->primeCertificationReviewers([$activity->id]);
        }

        return $this->certificationReviewerActivityIds[$activity->id];
    }

    // A coach for this activity: a reviewer on it, or holding a coach duty in one of its programs.
    // Used to offer the focused certification editor; other editors get the whole form.
    public function isCertificationCoachOnActivity(Activity $activity): bool
    {
        if ($this->isInput()) {
            return false;
        }

        $activity->loadMissing('programs:id');

        return $this->isCertificationReviewerOnActivity($activity)
            || array_intersect($activity->programs->modelKeys(), $this->user->certification()->coachProgramIds()) !== [];
    }

    /**
     * Resolves reviewer standing for a whole page of activities in one query, so row menus that ask
     * about each activity do not run one query per row.
     *
     * @param  array<int>  $activityIds
     */
    public function primeCertificationReviewers(array $activityIds): void
    {
        $this->batchVisibility(Activity::class, $activityIds, $this->certificationReviewerActivityIds,
            Activity::query()->where(fn (Builder $builder) => $this->orCertificationReviewer($builder)));
    }

    /** @param array<int> $activityIds */
    public function primeActivityVisibility(array $activityIds): void
    {
        $this->batchVisibility(Activity::class, $activityIds, $this->visibleActivityIds,
            $this->applyActivityVisibility(Activity::query()));
        $this->batchVisibility(Activity::class, $activityIds, $this->activityActionLogScopeIds,
            Activity::query()->whereHas('programs', fn (Builder $query) => $this->applyProgramVisibility($query)));
    }

    // Owner, supervisor of owner, or enhanced viewer/admin whose view scope covers the activity
    // (program visibility — not merely Delivered By).
    public function canViewActivityActionLog(Activity $activity): bool
    {
        if (! $this->canViewActivity($activity)) {
            return false;
        }

        if ((int) $activity->user_id === (int) $this->user->id) {
            return true;
        }

        if ($this->isSupervisorOfActivityOwner($activity)) {
            return true;
        }

        if (! $this->hasView() || $this->isInput()) {
            return false;
        }

        if ($this->hasSystemView()) {
            return true;
        }

        if (array_key_exists($activity->id, $this->activityActionLogScopeIds)) {
            return $this->activityActionLogScopeIds[$activity->id];
        }

        $this->primeActivityVisibility([$activity->id]);

        return $this->activityActionLogScopeIds[$activity->id];
    }

    // Logged it, participant, or the activity's own programs/projects are in your admin scope.
    public function canUpdateActivity(Activity $activity): bool
    {
        if ($this->loggedActivity($activity) || $this->isParticipantOnActivity($activity)) {
            return true;
        }

        return $this->hasAdmin() && $this->activityOverlapsAdminProgramOrProject($activity);
    }

    // Logged it, or the activity's own programs/projects are in your admin scope.
    public function canDeleteActivity(Activity $activity): bool
    {
        if ($this->loggedActivity($activity)) {
            return true;
        }

        return $this->hasAdmin() && $this->activityOverlapsAdminProgramOrProject($activity);
    }

    public function isReferencedOnActivity(Activity $activity): bool
    {
        $ids = $this->activityReferenceUserIds();

        if (in_array((int) $activity->user_id, $ids, true)) {
            return true;
        }

        $activity->loadMissing('participants');

        return $activity->participants->contains(fn (User $participant) => in_array((int) $participant->id, $ids, true));
    }

    private function isSupervisorOfActivityOwner(Activity $activity): bool
    {
        if (! $this->isSupervisor()) {
            return false;
        }

        $activity->loadMissing('user');

        return $activity->user !== null
            && (int) $activity->user->supervisor_id === (int) $this->user->id;
    }

    private function loggedActivity(Activity $activity): bool
    {
        return (int) $activity->user_id === (int) $this->user->id;
    }

    private function isParticipantOnActivity(Activity $activity): bool
    {
        $activity->loadMissing('participants');

        return $activity->participants->contains(
            fn (User $participant) => (int) $participant->id === (int) $this->user->id,
        );
    }

    private function activityOverlapsAdminProgramOrProject(Activity $activity): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        $activity->loadMissing('programs.projects');

        $programIds = $activity->programs->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_intersect($programIds, $this->adminProgramIds()) !== []) {
            return true;
        }

        $projectIds = $activity->programs
            ->flatMap(fn ($program) => $program->projects)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_intersect($projectIds, $this->adminProjectIds()) !== [];
    }

    /** @param array<int, mixed> $organizationIds */
    public function primeOrganizationVisibility(array $organizationIds): void
    {
        $this->recordVisibilityCache[Organization::class] ??= [];
        $this->batchVisibility(Organization::class, array_map(intval(...), $organizationIds),
            $this->recordVisibilityCache[Organization::class], $this->applyOrganizationVisibility(Organization::query()));
    }

    /**
     * VisibleToUser collects hydrated ids (including eager loads) without querying access.
     * A cache miss checks all pending ids of that type, plus the requested ids, in chunks.
     * The query uses the same apply*Visibility filter as lists, so link and list rules
     * cannot drift; PHP gates and policy-specific exceptions remain at their callers.
     * Answers, including denials, live only in this UserAccess instance for the request.
     * Debug with the query log: one IN (...) per type/predicate/chunk, then cache hits.
     * Models loaded later require another batch; ids are never consumed across users.
     *
     * @param  class-string<Model>  $class
     * @param  array<int>  $ids
     * @param  array<int, bool>  $answers
     * @param  Builder<covariant Model>|null  $query  Null means the existing PHP gate denied access.
     */
    private function batchVisibility(string $class, array $ids, array &$answers, ?Builder $query): void
    {
        if ($ids === []) {
            return;
        }

        $pending = array_values(array_diff(array_unique([
            ...app(HydratedRecordIds::class)->forType($class),
            ...$ids,
        ]), array_keys($answers)));

        foreach (array_chunk($pending, 1000) as $chunk) {
            $visible = $query === null ? [] : (clone $query)->whereKey($chunk)->pluck('id')->all();
            $visible = array_fill_keys($visible, true);

            foreach ($chunk as $id) {
                $answers[$id] = isset($visible[$id]);
            }
        }
    }

    public function canViewInactiveAgreement(Agreement $agreement): bool
    {
        if ($this->hasSystemView()) {
            return true;
        }

        if (! $this->hasView()) {
            return false;
        }

        if (! array_key_exists($agreement->id, $this->privilegeVisibleAgreementIds)) {
            $this->batchVisibility(Agreement::class, [$agreement->id], $this->privilegeVisibleAgreementIds,
                Agreement::query()->where(function (Builder $query) {
                    $query->whereRaw('1 = 0');
                    $this->orPrivilegeScopedOverlap($query);
                }));
        }

        return $this->privilegeVisibleAgreementIds[$agreement->id];
    }

    /**
     * @param  Builder<Agreement>  $query
     * @return Builder<Agreement>
     */
    public function applyAgreementIndexVisibility(Builder $query): Builder
    {
        if ($this->hasSystemView()) {
            return $query;
        }

        return $query->where(function (Builder $query) {
            $this->applyActiveAgreement($query);
            $this->orPrivilegeScopedOverlap($query);
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyActiveAgreement(Builder $query): Builder
    {
        return $query->where('agreements.active', true);
    }

    public function canViewRecord(Model $record): bool
    {
        if ($record instanceof Activity) {
            return $this->canViewActivity($record);
        }

        if ($record instanceof User) {
            return $this->canViewUser($record);
        }

        $class = $record::class;
        $id = (int) $record->getKey();
        if (isset($this->recordVisibilityCache[$class][$id])) {
            return $this->recordVisibilityCache[$class][$id];
        }

        $query = match (true) {
            $record instanceof Project => $this->applyProjectVisibility(Project::query()),
            $record instanceof Program => $this->applyProgramVisibility(Program::query()),
            $record instanceof Team => $this->applyTeamVisibility(Team::query()),
            $record instanceof Agreement => $this->applyAgreementVisibility(Agreement::query()),
            $record instanceof Organization => $this->applyOrganizationVisibility(Organization::query()),
            $record instanceof State => $this->applyStateVisibility(State::query()),
            $record instanceof ContactFamily => $this->hasAdmin() ? $this->applyScopedEntityVisibility(ContactFamily::query()) : null,
            $record instanceof LoggingField => $this->hasAdmin() ? $this->applyScopedEntityVisibility(LoggingField::query()) : null,
            $record instanceof ActivityType => $this->hasAdmin() ? $this->applyScopedEntityVisibility(ActivityType::query()) : null,
            $record instanceof SubActivity => $this->hasAdmin() ? $this->applyScopedEntityVisibility(SubActivity::query()) : null,
            $record instanceof CertificationInstrument => $this->hasView() ? $this->applyScopedEntityVisibility(CertificationInstrument::query()) : null,
            $record instanceof Certificate => $this->hasView() ? $this->applyScopedEntityVisibility(Certificate::query()) : null,
            $record instanceof CertificationCandidate => $this->applyCertificationCandidateVisibility(CertificationCandidate::query()),
            default => null,
        };

        $this->recordVisibilityCache[$class] ??= [];
        $this->batchVisibility($class, [$id], $this->recordVisibilityCache[$class], $query);

        return $this->recordVisibilityCache[$class][$id];
    }

    public function canViewUser(User $target): bool
    {
        if ($this->isInput()) {
            return false;
        }

        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($this->isSupervisor() && (int) $target->supervisor_id === (int) $this->user->id) {
            return true;
        }

        if ($this->hasView()) {
            return $this->userIsInViewMembership($target);
        }

        return false;
    }

    public function canUpdateScopedRecord(Model $record): bool
    {
        if (! $this->hasAdmin()) {
            return false;
        }

        return $this->scopedRecordOverlapsAdmin(
            ...$this->scopePayload($record),
        );
    }

    public function canDeleteScopedRecord(Model $record): bool
    {
        if (! $this->hasAdmin()) {
            return false;
        }

        return $this->scopedRecordFullyWithinAdmin(
            ...$this->scopePayload($record),
        );
    }

    /**
     * @return array{0: ?ProgramScopeMode, 1: list<int>, 2: list<int>}
     */
    public function scopePayload(Model $record): array
    {
        if ($record instanceof Program) {
            $record->loadMissing('projects');

            return [null, [], array_values($record->projects->pluck('id')->map(fn ($id) => (int) $id)->all())];
        }

        if ($record instanceof Project) {
            return [null, [], [(int) $record->id]];
        }

        /** @var Model&HasPrograms $record */
        $mode = $record->program_scope_mode ?? null;
        $record->loadMissing('programs');
        $programIds = array_values($record->programs->pluck('id')->map(fn ($id) => (int) $id)->all());

        return [$mode, $programIds, []];
    }

    /**
     * @return Collection<int, UserPrivilege>
     */
    public function privileges(): Collection
    {
        if ($this->privileges !== null) {
            return $this->privileges;
        }

        if (! $this->isAdminViewer()) {
            return $this->privileges = collect();
        }

        if ($this->user->relationLoaded('privileges')) {
            return $this->privileges = $this->user->privileges;
        }

        return $this->privileges = $this->user->privileges()->get();
    }

    // check if user has requested privilege
    private function hasPrivilege(PrivilegeCapability $capability, PrivilegeScopeType $scopeType, ?int $scopeId = null): bool
    {
        return $this->privileges()->contains(function (UserPrivilege $privilege) use ($capability, $scopeType, $scopeId) {
            if ($privilege->capability !== $capability || $privilege->scope_type !== $scopeType) {
                return false;
            }

            if ($scopeType === PrivilegeScopeType::System) {
                return true;
            }

            return (int) $privilege->scope_id === (int) $scopeId;
        });
    }

    /**
     * @phpstan-assert list<int> $this->adminProjectIds
     * @phpstan-assert list<int> $this->adminProgramIds
     * @phpstan-assert list<int> $this->viewProjectIds
     * @phpstan-assert list<int> $this->viewProgramIds
     */
    private function hydratePrivilegeIds(): void
    {
        if ($this->adminProjectIds !== null) {
            return;
        }

        $adminProjectIds = [];
        $adminProgramIds = [];
        $viewProjectIds = [];
        $viewProgramIds = [];

        foreach ($this->privileges() as $privilege) {
            if ($privilege->scope_type === PrivilegeScopeType::System) {
                continue;
            }

            $id = (int) $privilege->scope_id;
            $isAdmin = $privilege->capability === PrivilegeCapability::Admin;

            if ($privilege->scope_type === PrivilegeScopeType::Project) {
                $viewProjectIds[] = $id;
                if ($isAdmin) {
                    $adminProjectIds[] = $id;
                }
            }

            if ($privilege->scope_type === PrivilegeScopeType::Program) {
                $viewProgramIds[] = $id;
                if ($isAdmin) {
                    $adminProgramIds[] = $id;
                }
            }
        }

        $impliedFromAdminProjects = $this->programIdsForProjects($adminProjectIds);
        $impliedFromViewProjects = $this->programIdsForProjects($viewProjectIds);

        $this->adminProjectIds = array_values(array_unique($adminProjectIds));
        $this->adminProgramIds = array_values(array_unique(array_merge($adminProgramIds, $impliedFromAdminProjects)));
        $this->viewProjectIds = array_values(array_unique(array_merge($viewProjectIds, $adminProjectIds)));
        $this->viewProgramIds = array_values(array_unique(array_merge(
            $viewProgramIds,
            $adminProgramIds,
            $impliedFromViewProjects,
            $impliedFromAdminProjects,
        )));
    }

    /**
     * @param  list<int>  $projectIds
     * @return list<int>
     */
    private function programIdsForProjects(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        return array_values(Program::query()
            ->whereHas('projects', fn (Builder $query) => $query->whereIn('projects.id', $projectIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function orPrivilegeProjectOverlap(Builder $builder): void
    {
        if (! $this->hasView()) {
            return;
        }

        $projectIds = $this->viewProjectIds();
        $programIds = $this->viewProgramIds();

        if ($projectIds !== []) {
            $builder->orWhereIn('projects.id', $projectIds);
        }

        if ($programIds !== []) {
            $builder->orWhereHas('programs', fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
        }
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function orPrivilegeProgramOverlap(Builder $builder): void
    {
        if (! $this->hasView()) {
            return;
        }

        $programIds = $this->viewProgramIds();
        $projectIds = $this->viewProjectIds();

        if ($programIds !== []) {
            $builder->orWhereIn('programs.id', $programIds);
        }

        if ($projectIds !== []) {
            $builder->orWhereHas('projects', fn (Builder $relation) => $relation->whereIn('projects.id', $projectIds));
        }
    }

    /**
     * Privilege overlap on assigned programs only. "All programs" is not an overlap for every viewer.
     *
     * @param  Builder<covariant Model>  $builder
     * @param  list<int>|null  $programIds
     */
    private function orPrivilegeScopedOverlap(Builder $builder, ?array $programIds = null): void
    {
        if (! $this->hasView()) {
            return;
        }

        $programIds ??= $this->viewProgramIds();

        if ($programIds !== []) {
            $builder->orWhereHas('programs', $this->programOverlapPredicate($programIds));
        }
    }

    /** @param list<int> $programIds
     * @return \Closure(Builder<covariant Model>): Builder<covariant Model>
     */
    private function programOverlapPredicate(array $programIds): \Closure
    {
        return fn (Builder $query) => $query->whereIn('programs.id', $programIds);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function orMembershipProjectOverlap(Builder $builder): void
    {
        $memberIds = $this->membershipVisibilityUserIds();
        if ($memberIds === []) {
            return;
        }

        $builder->orWhereHas('programs', function (Builder $programQuery) use ($memberIds) {
            $this->constrainProgramsByMembership($programQuery, $memberIds);
        });
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function orMembershipProgramOverlap(Builder $builder): void
    {
        $memberIds = $this->membershipVisibilityUserIds();
        if ($memberIds === []) {
            return;
        }

        $this->constrainProgramsByMembership($builder, $memberIds, or: true);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @param  list<int>  $memberIds
     */
    private function constrainProgramsByMembership(Builder $builder, array $memberIds, bool $or = false): void
    {
        $constraint = function (Builder $q) use ($memberIds) {
            $q->whereExists(function ($sub) use ($memberIds) {
                $sub->from('users')
                    ->whereIn('users.id', $memberIds)
                    ->where('users.program_scope_mode', ProgramScopeMode::All->value);
            })->orWhereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds))
                ->orWhereHas('teams', function (Builder $team) use ($memberIds) {
                    $team->whereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds));
                })->orWhereExists(function ($sub) use ($memberIds) {
                    $sub->from('teams')
                        ->join('team_user', 'team_user.team_id', '=', 'teams.id')
                        ->whereIn('team_user.user_id', $memberIds)
                        ->where('teams.program_scope_mode', ProgramScopeMode::All->value);
                });
        };

        if ($or) {
            $builder->orWhere($constraint);
        } else {
            $builder->where($constraint);
        }
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function orMembershipForScopedEntity(Builder $builder, Model $model): void
    {
        $memberIds = $this->membershipVisibilityUserIds();
        if ($memberIds === []) {
            return;
        }

        if ($model instanceof Team) {
            $builder->orWhereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds));
        }
    }

    /** @param  Builder<covariant Model>  $builder */
    private function orCertificationReviewer(Builder $builder): void
    {
        $this->orCertificationReviewerBy($builder, $this->activityReferenceUserIds());
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @param  list<int>  $userIds
     */
    private function orCertificationReviewerBy(Builder $builder, array $userIds): void
    {
        if ($this->isInput()) {
            $builder->orWhereRaw('1 = 0');

            return;
        }

        $builder->orWhereHas('reviewRequests', fn (Builder $relation) => $relation->whereIn('coach_user_id', $userIds))
            ->orWhereHas('certificationCandidates.candidate.enrollments', fn (Builder $relation) => $relation->whereIn('supervising_coach_user_id', $userIds));
    }

    /**
     * Get all activities that reference list of users.
     *
     * @param  Builder<covariant Model>  $builder
     * @param  list<int>  $userIds
     */
    private function orActivityReferencedBy(Builder $builder, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $builder->orWhere(fn (Builder $query) => $this->activityLoggedBy($query, $userIds))
            ->orWhere(fn (Builder $query) => $this->activityDeliveredBy($query, $userIds));
    }
}
