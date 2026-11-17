<?php

namespace App\Models;

use Database\Factories\CertificationInstrumentDimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CertificationInstrumentDimension extends Model
{
    /** @use HasFactory<CertificationInstrumentDimensionFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_instrument_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $dimension) {
            if (blank($dimension->slug)) {
                $dimension->slug = Str::slug($dimension->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificationInstrument, $this> */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrument::class, 'certification_instrument_id');
    }

    /** @return HasMany<CertificationInstrumentDimensionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CertificationInstrumentDimensionOption::class)->orderBy('sort_order');
    }
}
