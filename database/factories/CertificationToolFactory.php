<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\CertificationTool;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationTool> */
class CertificationToolFactory extends Factory
{
    protected $model = CertificationTool::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'active' => true,
            'sort_order' => 0,
            'program_scope_mode' => ProgramScopeMode::Specific,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
            'retired_at' => now(),
        ]);
    }

    public function forProgram(Program $program): static
    {
        return $this->afterCreating(function (CertificationTool $tool) use ($program) {
            $tool->programs()->sync([$program->id]);
        });
    }
}
