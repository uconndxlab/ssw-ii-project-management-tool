<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementDeliverable>
 *
 * Cross-field validation lives in AgreementRequest, not the model.
 * Use named states rather than randomizing metric_type, time_basis,
 * contribution_basis, and user_grouping_mode independently.
 */
class AgreementDeliverableFactory extends Factory
{
    protected $model = AgreementDeliverable::class;

    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'activity_type_id' => null,
            'contact_family_id' => null,
            'program_id' => null,
            'metric_type' => 'completion',
            'time_basis' => 'observed',
            'allotted_time_unit' => null,
            'contribution_basis' => 'contact',
            'user_grouping_mode' => null,
            'include_additional_time' => false,
            'target_quantity' => fake()->optional()->randomFloat(2, 1, 10),
            'suggested_due_date' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'sort_order' => 0,
            'notes' => fake()->optional()->sentence(),
            'retired_at' => null,
        ];
    }

    public function completionByContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => 'completion',
            'time_basis' => 'observed',
            'contribution_basis' => 'contact',
            'user_grouping_mode' => null,
            'allotted_time_unit' => null,
        ]);
    }

    public function completionByUser(string $groupingMode = 'individual'): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => 'completion',
            'time_basis' => 'observed',
            'contribution_basis' => 'user',
            'user_grouping_mode' => $groupingMode,
            'allotted_time_unit' => null,
        ]);
    }

    public function observedTimeByContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => 'time',
            'time_basis' => 'observed',
            'contribution_basis' => 'contact',
            'user_grouping_mode' => null,
            'allotted_time_unit' => null,
        ]);
    }

    public function allottedTimeByUser(string $unit = 'hours'): static
    {
        return $this->state(fn (array $attributes) => [
            'metric_type' => 'time',
            'time_basis' => 'allotted',
            'contribution_basis' => 'user',
            'user_grouping_mode' => 'individual',
            'allotted_time_unit' => $unit,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'retired_at' => now(),
        ]);
    }
}
