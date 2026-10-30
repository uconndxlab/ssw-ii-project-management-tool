<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityReviewRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityReviewRequest> */
class ActivityReviewRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['activity_id' => Activity::factory(), 'coach_user_id' => User::factory(), 'requested_by_user_id' => User::factory()];
    }
}
