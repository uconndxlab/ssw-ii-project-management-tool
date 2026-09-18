<?php

namespace App\Support\Authorization;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\Program;
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

    private ?Collection $duties = null;
    private ?array $coachProgramIds = null;
    private ?array $managerProgramIds = null;

    public function __construct(private User $user)
    {
    }

    public static function for(User $user): self
    {
        self::$cache ??= new \WeakMap();

        return self::$cache[$user] ??= new self($user);
    }

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

    /**
     * @return list<int>
     */
    public function coachProgramIds(): array
    {
        return $this->coachProgramIds ??= $this->programIdsForDuty(CertificationDuty::Coach);
    }

    /**
     * @return list<int>
     */
    public function managerProgramIds(): array
    {
        return $this->managerProgramIds ??= $this->programIdsForDuty(CertificationDuty::Manager);
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
     * @return list<int>
     */
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
