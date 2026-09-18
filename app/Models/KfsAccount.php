<?php

namespace App\Models;

use App\Models\Pivots\AgreementOrganizationKfsAccountPivot;
use Database\Factories\KfsAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property AgreementOrganizationKfsAccountPivot|null $pivot
 */
class KfsAccount extends Model
{
    /** @use HasFactory<KfsAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('number', 'asc');
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_kfs_account')->withTimestamps();
    }

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'agreement_organization_kfs_account')
            ->withPivot(['agreement_id'])
            ->withTimestamps();
    }
}
