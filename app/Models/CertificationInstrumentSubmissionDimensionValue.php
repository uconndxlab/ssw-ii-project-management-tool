<?php

namespace App\Models;

use App\Enums\CertificationSectionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One chosen option. Sections lists hold one row per ticked section; scored sections carry a result.
 *
 * @property CertificationSectionResult|null $result
 */
class CertificationInstrumentSubmissionDimensionValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['certification_instrument_submission_id', 'certification_instrument_dimension_id', 'certification_instrument_dimension_option_id', 'result'];

    protected function casts(): array
    {
        return ['result' => CertificationSectionResult::class];
    }

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
