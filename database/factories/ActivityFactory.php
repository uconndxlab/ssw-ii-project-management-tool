<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'engagement_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'activity_type_id' => ActivityType::factory(),
            'completion_count' => 1,
            'allotted_duration_hours' => null,
            'allotted_duration_days' => null,
            'internal_only' => false,
            'cancelled' => false,
            'not_yet_complete' => false,
        ];
    }

    public function internalOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'internal_only' => true,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled' => true,
        ]);
    }

    public function notYetComplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'not_yet_complete' => true,
        ]);
    }

    public function onDate(CarbonInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_date' => $date,
        ]);
    }

    public function thisYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'engagement_date' => fake()->dateTimeBetween(
                now()->startOfYear(),
                now()
            ),
        ]);
    }
}
