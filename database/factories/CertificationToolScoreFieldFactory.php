<?php

namespace Database\Factories;

use App\Enums\CertificationToolScoreUnit;
use App\Models\CertificationTool;
use App\Models\CertificationToolScoreField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationToolScoreField> */
class CertificationToolScoreFieldFactory extends Factory
{
    protected $model = CertificationToolScoreField::class;

    public function definition(): array
    {
        return [
            'certification_tool_id' => CertificationTool::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => CertificationToolScoreUnit::Percent,
            'sort_order' => 0,
        ];
    }
}
