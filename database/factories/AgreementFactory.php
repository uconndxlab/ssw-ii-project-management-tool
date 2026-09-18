<?php

namespace Database\Factories;

use App\Enums\AgreementTimeTrackingRequirement;
use App\Enums\ProgramScopeMode;
use App\Models\Agreement;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agreement> */
class AgreementFactory extends Factory
{
    protected $model = Agreement::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(4),
            'active' => true,
            'abstract' => fake()->optional()->paragraph(),
            'start_date' => null,
            'end_date' => null,
            'extension_start_date' => null,
            'extension_end_date' => null,
            'time_tracking_mode' => null,
            'require_payor' => false,
            'require_payee' => false,
            'program_scope_mode' => ProgramScopeMode::Specific,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'extension_start_date' => null,
            'extension_end_date' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subYears(2),
            'end_date' => now()->subMonths(6),
            'extension_start_date' => null,
            'extension_end_date' => null,
        ]);
    }

    public function withExtension(): static
    {
        $endDate = now()->addMonths(6);

        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonths(12),
            'end_date' => $endDate,
            'extension_start_date' => $endDate->copy()->subMonths(3),
            'extension_end_date' => $endDate->copy()->addMonths(6),
        ]);
    }

    public function requiresPayor(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_payor' => true,
        ]);
    }

    public function requiresPayee(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_payee' => true,
        ]);
    }

    public function withTimeTrackingByContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_tracking_mode' => AgreementTimeTrackingRequirement::ByContact,
        ]);
    }

    public function withTimeTrackingByUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_tracking_mode' => AgreementTimeTrackingRequirement::ByUser,
        ]);
    }

    public function withPrograms(int $count = 1): static
    {
        return $this->afterCreating(function (Agreement $agreement) use ($count) {
            $programs = Program::factory()->count($count)->create();
            $agreement->programs()->attach($programs);
        });
    }
}
