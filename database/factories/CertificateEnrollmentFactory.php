<?php

namespace Database\Factories;

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateEnrollment> */
class CertificateEnrollmentFactory extends Factory
{
    protected $model = CertificateEnrollment::class;

    public function definition(): array
    {
        return [
            'certification_candidate_id' => CertificationCandidate::factory(),
            'certificate_id' => Certificate::factory(),
            'sponsoring_agreement_id' => null,
            'supervising_coach_user_id' => null,
            'status' => CertificateEnrollmentStatus::NotStarted,
            'phase' => CertificateRequirementPhase::Initial,
            'cycle' => 1,
            'enrolled_at' => now(),
            'notes' => null,
        ];
    }
}
