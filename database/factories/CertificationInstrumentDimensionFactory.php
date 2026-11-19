<?php

namespace Database\Factories;

use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationInstrumentDimension> */
class CertificationInstrumentDimensionFactory extends Factory
{
    protected $model = CertificationInstrumentDimension::class;

    public function definition(): array
    {
        return [
            'certification_instrument_id' => CertificationInstrument::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
