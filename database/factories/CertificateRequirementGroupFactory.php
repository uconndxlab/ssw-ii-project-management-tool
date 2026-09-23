<?php

namespace Database\Factories;

use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateRequirementGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificateRequirementGroup> */
class CertificateRequirementGroupFactory extends Factory
{
    protected $model = CertificateRequirementGroup::class;

    public function definition(): array
    {
        return [
            'certificate_id' => Certificate::factory(),
            'phase' => CertificateRequirementPhase::Initial,
            'label' => fake()->words(2, true),
            'satisfy_mode' => CertificateGroupSatisfyMode::All,
            'sort_order' => 0,
        ];
    }

    public function all(): static
    {
        return $this->state(fn (array $attributes) => [
            'satisfy_mode' => CertificateGroupSatisfyMode::All,
            'required_count' => null,
        ]);
    }

    public function any(): static
    {
        return $this->state(fn (array $attributes) => [
            'satisfy_mode' => CertificateGroupSatisfyMode::Any,
            'required_count' => null,
        ]);
    }

    public function nOf(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'satisfy_mode' => CertificateGroupSatisfyMode::NOf,
            'required_count' => $count,
        ]);
    }
}
