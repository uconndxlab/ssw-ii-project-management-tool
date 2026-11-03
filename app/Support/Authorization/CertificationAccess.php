<?php

namespace App\Support\Authorization;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Models\UserCertificationDuty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Coach/manager duties, kept deliberately separate from UserAccess/PrivilegeCapability - see the
 * certification spine's "duties live outside the privilege hierarchy" rule. A duty never grants
 * implicit visibility; visibility still comes from UserAccess.
 */
class CertificationAccess
{
    /** @var \WeakMap<User, self>|null */
    private static ?\WeakMap $cache = null;

    /** @var Collection<int, UserCertificationDuty>|null */
    private ?Collection $duties = null;

    /** @var array<int>|null */
    private ?array $coachProgramIds = null;

    /** @var array<int>|null */
    private ?array $managerProgramIds = null;

    public function __construct(private User $user) {}

    public static function for(User $user): self
    {
        self::$cache ??= new \WeakMap;

        return self::$cache[$user] ??= new self($user);
    }

    /** @return Collection<int, UserCertificationDuty> */
    public function duties(): Collection
    {
        if ($this->duties !== null) {
            return $this->duties;
        }

        return $this->duties = $this->user->relationLoaded('certificationDuties')
            ? $this->user->certificationDuties->whereNull('revoked_at')->values()
            : $this->user->certificationDuties()->whereNull('revoked_at')->get();
    }

    public function hasDuty(CertificationDuty $duty): bool
    {
        return $this->duties()->contains(fn (UserCertificationDuty $row) => $row->duty === $duty);
    }

    public function isCoach(): bool
    {
        return $this->hasDuty(CertificationDuty::Coach);
    }

    public function isManager(): bool
    {
        return $this->hasDuty(CertificationDuty::Manager);
    }

    /** @return array<int> */
    public function coachProgramIds(): array
    {
        return $this->coachProgramIds ??= $this->programIdsForDuty(CertificationDuty::Coach);
    }

    /** @return array<int> */
    public function managerProgramIds(): array
    {
        return $this->managerProgramIds ??= $this->programIdsForDuty(CertificationDuty::Manager);
    }

    /**
     * Coach programs a supervisor may act in for one enrollment, and nothing wider: only when the
     * enrollment's assigned supervising coach is one of their direct reports, and only in the
     * programs that report coaches. This never marks the supervisor as a coach (assignment
     * eligibility and duty administration keep using coachProgramIds()).
     *
     * @return array<int>
     */
    public function coachProgramIdsViaSupervisee(CertificateEnrollment $enrollment): array
    {
        $coachId = $enrollment->supervising_coach_user_id;
        if ($coachId === null || ! in_array($coachId, $this->user->access()->directReportIds(), true)) {
            return [];
        }
        $enrollment->loadMissing('supervisingCoach');

        return $enrollment->supervisingCoach?->certification()->coachProgramIds() ?? [];
    }

    /**
     * The same, across every enrollment of one candidate.
     *
     * @return array<int>
     */
    public function coachProgramIdsViaSuperviseesFor(CertificationCandidate $candidate): array
    {
        $candidate->loadMissing('enrollments.supervisingCoach');

        return array_values(array_unique(array_merge([], ...$candidate->enrollments->map(fn (CertificateEnrollment $enrollment) => $this->coachProgramIdsViaSupervisee($enrollment))->all())));
    }

    public function coachesProgram(int $programId): bool
    {
        return in_array($programId, $this->coachProgramIds(), true);
    }

    public function managesProgram(int $programId): bool
    {
        return in_array($programId, $this->managerProgramIds(), true);
    }

    public function canGrantDuty(User $target, CertificationDuty $duty, PrivilegeScopeType $scopeType, ?int $scopeId): bool
    {
        $access = $this->user->access();

        return match ($scopeType) {
            PrivilegeScopeType::System => $access->isSystemAdmin(),
            PrivilegeScopeType::Project => $scopeId !== null && $access->adminsProject($scopeId),
            PrivilegeScopeType::Program => $scopeId !== null && $access->adminsProgram($scopeId),
        };
    }

    /**
     * Revocation requires the same authority the actor would need to grant a duty at that row's
     * own scope (self-grants included), so revoking is bounded to the actor's own admin scope.
     */
    public function canRevokeDuty(UserCertificationDuty $row): bool
    {
        $target = $row->user;

        if ($target === null) {
            return false;
        }

        return $this->canGrantDuty($target, $row->duty, $row->scope_type, $row->scope_id !== null ? (int) $row->scope_id : null);
    }

    /**
     * Active (non-revoked) coach-duty user ids reaching the given programs: system-scope duties
     * always qualify; program/project-scope duties qualify when their scope overlaps the given
     * program ids. A duty never grants implicit visibility — callers must separately confirm the
     * actor may see each candidate coach.
     *
     * @param  array<int>  $programIds
     * @return array<int>
     */
    public static function eligibleCoachUserIds(array $programIds): array
    {
        $projectIds = $programIds !== []
            ? Project::query()
                ->whereHas('programs', fn (Builder $relation) => $relation->whereIn('programs.id', $programIds))
                ->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        return UserCertificationDuty::query()
            ->where('duty', CertificationDuty::Coach)
            ->whereNull('revoked_at')
            ->where(function (Builder $query) use ($programIds, $projectIds) {
                $query->where('scope_type', PrivilegeScopeType::System);

                if ($programIds !== []) {
                    $query->orWhere(fn (Builder $q) => $q->where('scope_type', PrivilegeScopeType::Program)->whereIn('scope_id', $programIds));
                }

                if ($projectIds !== []) {
                    $query->orWhere(fn (Builder $q) => $q->where('scope_type', PrivilegeScopeType::Project)->whereIn('scope_id', $projectIds));
                }
            })
            ->pluck('user_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int> */
    private function programIdsForDuty(CertificationDuty $duty): array
    {
        $rows = $this->duties()->filter(fn (UserCertificationDuty $row) => $row->duty === $duty);

        if ($rows->contains(fn (UserCertificationDuty $row) => $row->scope_type === PrivilegeScopeType::System)) {
            return Program::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $programIds = $rows->where('scope_type', PrivilegeScopeType::Program)
            ->pluck('scope_id')->map(fn ($id) => (int) $id)->all();

        $projectIds = $rows->where('scope_type', PrivilegeScopeType::Project)
            ->pluck('scope_id')->map(fn ($id) => (int) $id)->all();

        if ($projectIds !== []) {
            $impliedProgramIds = Program::query()
                ->whereHas('projects', fn (Builder $query) => $query->whereIn('projects.id', $projectIds))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $programIds = array_merge($programIds, $impliedProgramIds);
        }

        return array_values(array_unique($programIds));
    }
}
