<?php

namespace Database\Factories;

use App\Enums\CertificationInstrumentScoreUnit;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentScoreField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CertificationInstrumentScoreField> */
class CertificationInstrumentScoreFieldFactory extends Factory
{
    protected $model = CertificationInstrumentScoreField::class;

    public function definition(): array
    {
        return [
            'certification_instrument_id' => CertificationInstrument::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => CertificationInstrumentScoreUnit::Percent,
            'sort_order' => 0,
        ];
    }
}
