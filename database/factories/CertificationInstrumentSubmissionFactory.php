<?php

namespace Database\Factories;

use App\Enums\CertificateTagOutcome;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationInstrumentSubmission> */
class CertificationInstrumentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return ['activity_certification_candidate_id' => ActivityCertificationCandidate::factory(), 'certification_instrument_id' => CertificationInstrument::factory(), 'outcome' => CertificateTagOutcome::Passed];
    }
}
