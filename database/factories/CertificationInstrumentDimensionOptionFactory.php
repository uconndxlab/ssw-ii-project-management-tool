<?php

namespace Database\Factories;

use App\Models\CertificationInstrumentDimension;
use App\Models\CertificationInstrumentDimensionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationInstrumentDimensionOption> */
class CertificationInstrumentDimensionOptionFactory extends Factory
{
    protected $model = CertificationInstrumentDimensionOption::class;

    public function definition(): array
    {
        return [
            'certification_instrument_dimension_id' => CertificationInstrumentDimension::factory(),
            'label' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
