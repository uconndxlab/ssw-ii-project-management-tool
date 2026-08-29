<?php

namespace App\Support\Authorization;

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\Agreement;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
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

    private ?array $adminProjectIds = null;
    private ?array $adminProgramIds = null;
    private ?array $viewProjectIds = null;
    private ?array $viewProgramIds = null;
    private ?array $directReportIds = null;

    private ?Collection $privileges = null;

    public function __construct(private User $user)
    {
    }

    public static function for(User $user): self
    {
        self::$cache ??= new \WeakMap();

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

    public function canAccessAdminSetup(): bool
    {
        return $this->hasAdmin();
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

        return $this->adminProjectIds;
    }

    /**
     * @return list<int>
     */
    public function adminProgramIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->adminProgramIds;
    }

    /**
     * @return list<int>
     */
    public function viewProjectIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->viewProjectIds;
    }

    /**
     * @return list<int>
     */
    public function viewProgramIds(): array
    {
        $this->hydratePrivilegeIds();

        return $this->viewProgramIds;
    }

    /**
     * @return list<int>
     */
    // get report ids for supervisor, cache
    public function directReportIds(): array
    {
        if ($this->directReportIds !== null) {
            return $this->directReportIds;
        }

        if (! $this->isSupervisor()) {
            return $this->directReportIds = [];
        }

        return $this->directReportIds = User::query()
            ->where('supervisor_id', $this->user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    public function viewsProgram(int $programId): bool
    {
        if ($this->hasSystemView()) {
            return true;
        }

        return in_array($programId, $this->viewProgramIds(), true);
    }

    public function viewsProject(int $projectId): bool
    {
        if ($this->hasSystemView()) {
            return true;
        }

        return in_array($projectId, $this->viewProjectIds(), true);
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

        if ($scopeType === PrivilegeScopeType::Program) {
            return $scopeId !== null && $this->adminsProgram($scopeId);
        }

        return false;
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
     * View-privilege overlap on assigned programs/projects. All-mode is system-wide, not every viewer.
     *
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     */
    public function scopedRecordOverlapsView(?ProgramScopeMode $mode, array $programIds, array $projectIds = []): bool
    {
        if ($this->isInput()) {
            return false;
        }

        if ($this->hasSystemView()) {
            return true;
        }

        if ($this->hasView()) {
            if ($mode === ProgramScopeMode::Specific && array_intersect($programIds, $this->viewProgramIds()) !== []) {
                return true;
            }

            if ($projectIds !== [] && array_intersect($projectIds, $this->viewProjectIds()) !== []) {
                return true;
            }
        }

        return false;
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

            return $projectIds !== [];
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
            if ($this->hasView()) {
                $programIds = $this->viewProgramIds();

                if ($programIds !== []) {
                    $builder->orWhereHas($programsRelation, fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
                }
            }

            $this->orMembershipForScopedEntity($builder, $model);
        });
    }

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

    // Agreements you belong to (self/team) or that list a program in your view/admin privilege.
    // or agreements supervisees belong to
    public function applyAgreementVisibility(Builder $query): Builder
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
                $builder->orWhereHas('users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds))
                    ->orWhereHas('teams.users', fn (Builder $relation) => $relation->whereIn('users.id', $memberIds));
            }
        });
    }

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

    public function applyActivityVisibility(Builder $query): Builder
    {
        if ($this->hasSystemView()) {
            return $query;
        }

        // visible if referenced or agreement visible
        return $query->where(function (Builder $builder) {
            $this->orActivityReferencedBy($builder, $this->activityReferenceUserIds());

            if ($this->hasView() && ! $this->isInput()) {
                $builder->orWhereHas('agreements', function (Builder $agreementQuery) {
                    $this->applyAgreementVisibility($agreementQuery);
                });
            }
        });
    }

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

    public function applySuperviseesVisibility(Builder $query): Builder
    {
        if (! $this->isSupervisor()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('supervisor_id', $this->user->id);
    }

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

        return $this->applyUsersInViewMembership(User::query()->whereKey($target->id))->exists();
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

    // Members/Input: logger or Delivered By (or a supervisee). Viewers: that, or the activity sits on an agreement they can view.
    public function canViewActivity(Activity $activity): bool
    {
        if ($this->isInput() || $this->isMember()) {
            return $this->isReferencedOnActivity($activity);
        }

        if ($this->isSupervisor() && $this->isReferencedOnActivity($activity)) {
            return true;
        }

        return $this->applyActivityVisibility(Activity::query()->whereKey($activity->id))->exists();
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

    public function canViewRecord(Model $record): bool
    {
        return match (true) {
            $record instanceof Project => $this->applyProjectVisibility(Project::query()->whereKey($record->id))->exists(),
            $record instanceof Program => $this->applyProgramVisibility(Program::query()->whereKey($record->id))->exists(),
            $record instanceof Team => $this->applyTeamVisibility(Team::query()->whereKey($record->id))->exists(),
            $record instanceof Agreement => $this->applyAgreementVisibility(Agreement::query()->whereKey($record->id))->exists(),
            $record instanceof Organization => $this->applyOrganizationVisibility(Organization::query()->whereKey($record->id))->exists(),
            $record instanceof State => $this->applyStateVisibility(State::query()->whereKey($record->id))->exists(),
            $record instanceof ContactFamily => $this->hasAdmin() && $this->applyScopedEntityVisibility(ContactFamily::query()->whereKey($record->id))->exists(),
            $record instanceof LoggingField => $this->hasAdmin() && $this->applyScopedEntityVisibility(LoggingField::query()->whereKey($record->id))->exists(),
            $record instanceof \App\Models\ActivityType => $this->hasAdmin() && $this->applyScopedEntityVisibility(\App\Models\ActivityType::query()->whereKey($record->id))->exists(),
            $record instanceof Activity => $this->canViewActivity($record),
            $record instanceof User => $this->canViewUser($record),
            default => false,
        };
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

            return [null, [], $record->projects->pluck('id')->map(fn ($id) => (int) $id)->all()];
        }

        if ($record instanceof Project) {
            return [null, [], [(int) $record->id]];
        }

        $mode = $record->program_scope_mode ?? null;
        $record->loadMissing('programs');
        $programIds = $record->programs->pluck('id')->map(fn ($id) => (int) $id)->all();

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

        return Program::query()
            ->whereHas('projects', fn (Builder $query) => $query->whereIn('projects.id', $projectIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

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

    // Privilege overlap on assigned programs only. "All programs" is not an overlap for every viewer.
    private function orPrivilegeScopedOverlap(Builder $builder): void
    {
        if (! $this->hasView()) {
            return;
        }

        $programIds = $this->viewProgramIds();

        if ($programIds !== []) {
            $builder->orWhereHas('programs', fn (Builder $relation) => $relation->whereIn('programs.id', $programIds));
        }
    }

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

    private function orMembershipProgramOverlap(Builder $builder): void
    {
        $memberIds = $this->membershipVisibilityUserIds();
        if ($memberIds === []) {
            return;
        }

        $this->constrainProgramsByMembership($builder, $memberIds, or: true);
    }

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

    // get all activities that reference list of users
    private function orActivityReferencedBy(Builder $builder, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $builder->orWhereIn('activities.user_id', $userIds)
            ->orWhereHas('participants', fn (Builder $relation) => $relation->whereIn('users.id', $userIds));
    }
}
