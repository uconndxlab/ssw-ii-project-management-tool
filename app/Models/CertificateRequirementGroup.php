<?php

namespace App\Models;

use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementPhase;
use Database\Factories\CertificateRequirementGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateRequirementGroup extends Model
{
    /** @use HasFactory<CertificateRequirementGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'phase',
        'label',
        'satisfy_mode',
        'required_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'phase' => CertificateRequirementPhase::class,
            'satisfy_mode' => CertificateGroupSatisfyMode::class,
            'required_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class)->orderBy('sort_order');
    }
}
