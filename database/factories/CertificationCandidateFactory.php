<?php

namespace Database\Factories;

use App\Models\CertificationCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationCandidate> */
class CertificationCandidateFactory extends Factory
{
    protected $model = CertificationCandidate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'start_date' => null,
            'notes' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }

    /**
     * A migrated legacy row that has never had its email collected.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
