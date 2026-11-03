<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationToolSubmissionDimensionValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['certification_tool_submission_id', 'certification_tool_dimension_id', 'certification_tool_dimension_option_id'];

    /** @return BelongsTo<CertificationToolDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationToolDimension::class, 'certification_tool_dimension_id');
    }

    /** @return BelongsTo<CertificationToolDimensionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CertificationToolDimensionOption::class, 'certification_tool_dimension_option_id');
    }
}
