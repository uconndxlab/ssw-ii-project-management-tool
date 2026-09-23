<?php

namespace App\Models;

use Database\Factories\CertificationToolDimensionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CertificationToolDimensionOption extends Model
{
    /** @use HasFactory<CertificationToolDimensionOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_tool_dimension_id',
        'label',
        'value',
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
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CertificationToolDimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CertificationToolDimension::class, 'certification_tool_dimension_id');
    }
}
