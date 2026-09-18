<?php

namespace Database\Factories;

use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationToolDimension> */
class CertificationToolDimensionFactory extends Factory
{
    protected $model = CertificationToolDimension::class;

    public function definition(): array
    {
        return [
            'certification_tool_id' => CertificationTool::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
