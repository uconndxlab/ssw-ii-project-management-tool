<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityType> */
class ActivityTypeFactory extends Factory
{
    protected $model = ActivityType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'helper_text' => fake()->optional()->sentence(),
            'contact_family_id' => ContactFamily::factory(),
            'active' => true,
            'sort_order' => 0,
            'duration_days' => 0,
            'duration_hours' => 0,
            'program_scope_mode' => ProgramScopeMode::All,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function withDayDuration(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_days' => $days,
        ]);
    }

    public function withHourDuration(float $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_hours' => $hours,
        ]);
    }
}
