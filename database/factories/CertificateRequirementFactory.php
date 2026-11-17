<?php

namespace Database\Factories;

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificationInstrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateRequirement> */
class CertificateRequirementFactory extends Factory
{
    protected $model = CertificateRequirement::class;

    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'phase' => CertificateRequirementPhase::Initial,
            'kind' => CertificateRequirementKind::ActivityCount,
            'target_count' => 1,
            'requires_passing' => true,
            'sort_order' => 0,
        ];
    }

    public function activityCount(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => CertificateRequirementKind::ActivityCount,
        ]);
    }

    public function toolSubmission(?CertificationInstrument $tool = null): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => CertificateRequirementKind::ToolSubmission,
            'certification_instrument_id' => $tool?->id ?? CertificationInstrument::factory(),
        ]);
    }

    public function attestation(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => CertificateRequirementKind::Attestation,
            'contact_family_id' => null,
            'activity_type_id' => null,
            'certification_instrument_id' => null,
        ]);
    }

    public function renewal(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => CertificateRequirementPhase::Renewal,
        ]);
    }
}
