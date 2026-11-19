<?php

namespace App\Models;

use Database\Factories\CertificationInstrumentDimensionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CertificationInstrumentDimensionOption extends Model
{
    /** @use HasFactory<CertificationInstrumentDimensionOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_instrument_dimension_id',
        'label',
        'value',
        'scored',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $option) {
            if (blank($option->value)) {
                $option->value = Str::slug($option->label);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scored' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificationInstrumentDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrumentDimension::class, 'certification_instrument_dimension_id');
    }
}
