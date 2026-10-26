<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationToolSubmissionScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['certification_tool_submission_id', 'certification_tool_score_field_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:4'];
    }

    /** @return BelongsTo<CertificationToolScoreField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(CertificationToolScoreField::class, 'certification_tool_score_field_id');
    }
}
