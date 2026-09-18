<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationContact> */
class OrganizationContactFactory extends Factory
{
    protected $model = OrganizationContact::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->optional()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'title' => fake()->optional()->jobTitle(),
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'is_primary' => true,
        ]);
    }
}
