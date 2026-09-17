<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CertificationToolDimension extends Model
{
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

    public function tool(): BelongsTo
    {
        return $this->belongsTo(CertificationTool::class, 'certification_tool_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CertificationToolDimensionOption::class)->orderBy('sort_order');
    }
}
