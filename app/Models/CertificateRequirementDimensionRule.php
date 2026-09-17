<?php

namespace App\Models;

use App\Enums\CertificateDimensionRuleMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateRequirementDimensionRule extends Model
{
    protected $fillable = [
        'certificate_requirement_id',
        'certification_tool_dimension_id',
        'mode',
        'option_ids',
        'min_count',
    ];

    protected function casts(): array
    {
        return [
            'mode' => CertificateDimensionRuleMode::class,
            'option_ids' => 'array',
            'min_count' => 'integer',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(CertificateRequirement::class, 'certificate_requirement_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationToolDimension::class, 'certification_tool_dimension_id');
    }
}
