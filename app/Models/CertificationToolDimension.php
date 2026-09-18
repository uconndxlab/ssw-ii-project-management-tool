<?php

namespace App\Models;

use Database\Factories\CertificationToolDimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CertificationToolDimension extends Model
{
    /** @use HasFactory<CertificationToolDimensionFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_tool_id',
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

    /** @return BelongsTo<CertificationTool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(CertificationTool::class, 'certification_tool_id');
    }

    /** @return HasMany<CertificationToolDimensionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CertificationToolDimensionOption::class)->orderBy('sort_order');
    }
}
