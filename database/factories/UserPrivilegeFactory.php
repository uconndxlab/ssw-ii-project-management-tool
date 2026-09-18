<?php

namespace Database\Factories;

use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserPrivilege> */
class UserPrivilegeFactory extends Factory
{
    protected $model = UserPrivilege::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'capability' => PrivilegeCapability::View,
            'scope_type' => PrivilegeScopeType::Program,
            'scope_id' => Program::factory(),
        ];
    }

    public function systemAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'capability' => PrivilegeCapability::Admin,
            'scope_type' => PrivilegeScopeType::System,
            'scope_id' => null,
        ]);
    }

    public function programAdmin(Program $program): static
    {
        return $this->state(fn (array $attributes) => [
            'capability' => PrivilegeCapability::Admin,
            'scope_type' => PrivilegeScopeType::Program,
            'scope_id' => $program->id,
        ]);
    }

    public function programViewer(Program $program): static
    {
        return $this->state(fn (array $attributes) => [
            'capability' => PrivilegeCapability::View,
            'scope_type' => PrivilegeScopeType::Program,
            'scope_id' => $program->id,
        ]);
    }

    public function projectAdmin(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'capability' => PrivilegeCapability::Admin,
            'scope_type' => PrivilegeScopeType::Project,
            'scope_id' => $project->id,
        ]);
    }
}
