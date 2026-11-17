<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationInstrumentSubmissionScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['certification_instrument_submission_id', 'certification_instrument_score_field_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }

    /** @return BelongsTo<CertificationInstrumentScoreField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrumentScoreField::class, 'certification_instrument_score_field_id');
    }
}
