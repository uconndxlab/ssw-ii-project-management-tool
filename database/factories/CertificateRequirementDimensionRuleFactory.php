<?php

namespace Database\Factories;

use App\Enums\CertificateDimensionRuleMode;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificationToolDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateRequirementDimensionRule> */
class CertificateRequirementDimensionRuleFactory extends Factory
{
    protected $model = CertificateRequirementDimensionRule::class;

    public function definition(): array
    {
        return [
            'certificate_requirement_id' => CertificateRequirement::factory(),
            'certification_tool_dimension_id' => CertificationToolDimension::factory(),
            'mode' => CertificateDimensionRuleMode::Coverage,
            'option_ids' => [],
        ];
    }

    public function coverage(array $optionIds): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => CertificateDimensionRuleMode::Coverage,
            'option_ids' => $optionIds,
            'min_count' => null,
        ]);
    }

    public function quota(int $minCount, array $optionIds = []): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => CertificateDimensionRuleMode::Quota,
            'option_ids' => $optionIds,
            'min_count' => $minCount,
        ]);
    }
}
