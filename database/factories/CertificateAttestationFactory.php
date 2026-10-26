<?php

namespace Database\Factories;

use App\Models\CertificateAttestation;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateAttestation> */
class CertificateAttestationFactory extends Factory
{
    public function definition(): array
    {
        return ['certificate_enrollment_id' => CertificateEnrollment::factory(), 'certificate_requirement_id' => CertificateRequirement::factory()->attestation(), 'cycle' => 1, 'satisfied' => true, 'satisfied_at' => now()];
    }
}
