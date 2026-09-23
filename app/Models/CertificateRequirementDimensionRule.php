<?php

namespace App\Models;

use App\Enums\CertificateDimensionRuleMode;
use Database\Factories\CertificateRequirementDimensionRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CertificateDimensionRuleMode $mode
 */
class CertificateRequirementDimensionRule extends Model
{
    /** @use HasFactory<CertificateRequirementDimensionRuleFactory> */
    use HasFactory;

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

    /** @return BelongsTo<CertificateRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(CertificateRequirement::class, 'certificate_requirement_id');
    }

    /** @return BelongsTo<CertificationToolDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationToolDimension::class, 'certification_tool_dimension_id');
    }
}
