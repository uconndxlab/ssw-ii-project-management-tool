<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgreementCertificationCandidate> */
class AgreementCertificationCandidateFactory extends Factory
{
    protected $model = AgreementCertificationCandidate::class;

    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'name' => fake()->name(),
            'program_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
