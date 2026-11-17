<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationInstrumentSubmissionDimensionValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['certification_instrument_submission_id', 'certification_instrument_dimension_id', 'certification_instrument_dimension_option_id'];

    /** @return BelongsTo<CertificationInstrumentDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrumentDimension::class, 'certification_instrument_dimension_id');
    }

    /** @return BelongsTo<CertificationInstrumentDimensionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrumentDimensionOption::class, 'certification_instrument_dimension_option_id');
    }
}
