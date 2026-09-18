<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\Certificate;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Certificate> */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'active' => true,
            'sort_order' => 0,
            'program_scope_mode' => ProgramScopeMode::Specific,
            'prerequisite_mode' => 'all',
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
        return $this->afterCreating(function (Certificate $certificate) use ($program) {
            $certificate->programs()->sync([$program->id]);
        });
    }

    public function prerequisiteModeAny(): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisite_mode' => 'any',
        ]);
    }
}
