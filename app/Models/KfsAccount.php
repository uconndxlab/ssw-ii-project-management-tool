<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KfsAccount extends Model
{
    protected $fillable = [
        'number',
    ];

    /**
     * @param  Builder<KfsAccount>  $query
     * @return Builder<KfsAccount>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('number', 'asc');
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_kfs_account')->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'agreement_organization_kfs_account')
            ->withPivot(['agreement_id'])
            ->withTimestamps();
    }
}
