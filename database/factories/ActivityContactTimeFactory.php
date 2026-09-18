<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityContactTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityContactTime> */
class ActivityContactTimeFactory extends Factory
{
    protected $model = ActivityContactTime::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'activity_hours' => fake()->randomFloat(2, 0.5, 8),
            'prep_hours' => 0,
            'follow_up_hours' => 0,
        ];
    }
}
