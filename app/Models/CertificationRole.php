<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * certificate_id null = standard/global role available to every certificate.
 */
class CertificationRole extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'certificate_id',
        'active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $role) {
            if (blank($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
