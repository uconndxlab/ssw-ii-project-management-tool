<?php

namespace App\Models;

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use Database\Factories\CertificateRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CertificateRequirementPhase $phase
 * @property CertificateRequirementKind $kind
 */
class CertificateRequirement extends Model
{
    /** @use HasFactory<CertificateRequirementFactory> */
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'certificate_requirement_group_id',
        'phase',
        'kind',
        'contact_family_id',
        'activity_type_id',
        'certification_tool_id',
        'certification_role_id',
        'target_count',
        'requires_passing',
        'threshold_note',
        'window_months',
        'label',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'phase' => CertificateRequirementPhase::class,
            'kind' => CertificateRequirementKind::class,
            'target_count' => 'integer',
            'requires_passing' => 'boolean',
            'window_months' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** @return BelongsTo<CertificateRequirementGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CertificateRequirementGroup::class, 'certificate_requirement_group_id');
    }

    /** @return BelongsTo<ContactFamily, $this> */
    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /** @return BelongsTo<CertificationTool, $this> */
    public function certificationTool(): BelongsTo
    {
        return $this->belongsTo(CertificationTool::class);
    }

    /** @return BelongsTo<CertificationRole, $this> */
    public function certificationRole(): BelongsTo
    {
        return $this->belongsTo(CertificationRole::class);
    }

    /** @return HasMany<CertificateRequirementDimensionRule, $this> */
    public function dimensionRules(): HasMany
    {
        return $this->hasMany(CertificateRequirementDimensionRule::class);
    }
}
