<?php

namespace App\Models;

use Database\Factories\CertificationRoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * certificate_id null = standard/global role available to every certificate.
 */
class CertificationRole extends Model
{
    /** @use HasFactory<CertificationRoleFactory> */
    use HasFactory;

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

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
