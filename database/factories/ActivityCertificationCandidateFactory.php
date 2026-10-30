<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityCertificationCandidate> */
class ActivityCertificationCandidateFactory extends Factory
{
    public function definition(): array
    {
        return ['activity_id' => Activity::factory(), 'certification_candidate_id' => CertificationCandidate::factory(), 'completion_units' => 1];
    }
}
