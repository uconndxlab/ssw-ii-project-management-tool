<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Team> */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'active' => true,
            'program_scope_mode' => ProgramScopeMode::Specific,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function scopedToAllPrograms(): static
    {
        return $this->state(fn (array $attributes) => [
            'program_scope_mode' => ProgramScopeMode::All,
        ]);
    }
}
