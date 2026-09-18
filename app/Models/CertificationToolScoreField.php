<?php

namespace App\Models;

use App\Enums\CertificationToolScoreUnit;
use Database\Factories\CertificationToolScoreFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Recorded-only numeric field. Never evaluated by the system - see the spine's "no score math" rule.
 */
class CertificationToolScoreField extends Model
{
    /** @use HasFactory<CertificationToolScoreFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_tool_id',
        'name',
        'slug',
        'unit',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $field) {
            if (blank($field->slug)) {
                $field->slug = Str::slug($field->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'unit' => CertificationToolScoreUnit::class,
            'sort_order' => 'integer',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(CertificationTool::class, 'certification_tool_id');
    }
}
