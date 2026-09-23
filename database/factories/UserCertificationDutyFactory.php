<?php

namespace Database\Factories;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Models\UserCertificationDuty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserCertificationDuty> */
class UserCertificationDutyFactory extends Factory
{
    protected $model = UserCertificationDuty::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'duty' => CertificationDuty::Coach,
            'scope_type' => PrivilegeScopeType::Program,
            'scope_id' => Program::factory(),
            'granted_by_user_id' => User::factory(),
        ];
    }

    public function coach(): static
    {
        return $this->state(fn (array $attributes) => [
            'duty' => CertificationDuty::Coach,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'duty' => CertificationDuty::Manager,
        ]);
    }

    public function atProgram(Program $program): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => PrivilegeScopeType::Program,
            'scope_id' => $program->id,
        ]);
    }

    public function atProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => PrivilegeScopeType::Project,
            'scope_id' => $project->id,
        ]);
    }

    public function atSystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => PrivilegeScopeType::System,
            'scope_id' => null,
        ]);
    }

    public function revoked(?User $revokedBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'revoked_by_user_id' => $revokedBy?->id ?? User::factory(),
        ]);
    }
}
