<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificationRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationRole> */
class CertificationRoleFactory extends Factory
{
    protected $model = CertificationRole::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'certificate_id' => Certificate::factory(),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_id' => null,
        ]);
    }
}
