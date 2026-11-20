<?php

namespace Database\Factories;

use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateAward> */
class CertificateAwardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'certificate_enrollment_id' => CertificateEnrollment::factory(), 'cycle' => 1, 'phase' => 'initial',
            'endorsed_at' => now()->subDay(), 'requirements_snapshot' => [], 'awarded_at' => now(), 'expires_at' => now()->addYear(),
        ];
    }

    public function manual(): static
    {
        return $this->state(['manual_award' => true, 'endorsed_by_user_id' => null, 'requirements_snapshot' => ['manual' => true, 'evidence' => []]]);
    }

    public function pending(): static
    {
        return $this->state(['awarded_at' => null, 'expires_at' => null]);
    }
}
