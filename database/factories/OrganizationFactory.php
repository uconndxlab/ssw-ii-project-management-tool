<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'active' => true,
            'po_number' => null,
            'program_scope_mode' => ProgramScopeMode::Specific,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function withPoNumber(?string $poNumber = null): static
    {
        return $this->state(fn (array $attributes) => [
            'po_number' => $poNumber ?? fake()->unique()->numerify('PO-######'),
        ]);
    }
}
