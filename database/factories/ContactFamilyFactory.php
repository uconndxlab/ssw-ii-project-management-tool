<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\ContactFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactFamily> */
class ContactFamilyFactory extends Factory
{
    protected $model = ContactFamily::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'helper_text' => fake()->optional()->sentence(),
            'active' => true,
            'track_additional_time' => false,
            'sort_order' => 0,
            'program_scope_mode' => ProgramScopeMode::All,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function tracksAdditionalTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'track_additional_time' => true,
        ]);
    }
}
