<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgreementCertificationCandidate> */
class AgreementCertificationCandidateFactory extends Factory
{
    protected $model = AgreementCertificationCandidate::class;

    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'certification_candidate_id' => CertificationCandidate::factory(),
            'program_id' => null,
            'notes' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
