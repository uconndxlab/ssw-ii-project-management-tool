<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityParticipantTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityParticipantTime> */
class ActivityParticipantTimeFactory extends Factory
{
    protected $model = ActivityParticipantTime::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'participant_name' => null,
            'hours' => fake()->randomFloat(2, 0.5, 8),
            'prep_hours' => 0,
            'follow_up_hours' => 0,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'participant_name' => fake()->name(),
        ]);
    }
}
