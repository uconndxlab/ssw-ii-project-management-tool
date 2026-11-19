<?php

namespace App\Models;

use App\Enums\CertificationInstrumentScoreUnit;
use Database\Factories\CertificationInstrumentScoreFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Recorded-only field (a number, or Pass/Fail stored as 1/0). Never evaluated by the system - see the spine's "no score math" rule.
 *
 * @property CertificationInstrumentScoreUnit $unit
 * @property int|null $max_points Points only: scores are whole numbers from 0 to this; null means no ceiling.
 */
class CertificationInstrumentScoreField extends Model
{
    /** @use HasFactory<CertificationInstrumentScoreFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_instrument_id',
        'name',
        'slug',
        'unit',
        'max_points',
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
            'unit' => CertificationInstrumentScoreUnit::class,
            'max_points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificationInstrument, $this> */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrument::class, 'certification_instrument_id');
    }
}
