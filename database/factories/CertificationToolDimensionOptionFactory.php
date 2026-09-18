<?php

namespace Database\Factories;

use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationToolDimensionOption> */
class CertificationToolDimensionOptionFactory extends Factory
{
    protected $model = CertificationToolDimensionOption::class;

    public function definition(): array
    {
        return [
            'certification_tool_dimension_id' => CertificationToolDimension::factory(),
            'label' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
