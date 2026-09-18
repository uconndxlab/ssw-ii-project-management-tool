<?php

namespace App\Models;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use Database\Factories\UserCertificationDutyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Self-auditing: revoked rows are retained rather than deleted, so no separate audit table exists.
 *
 * @property CertificationDuty $duty
 * @property PrivilegeScopeType $scope_type
 */
class UserCertificationDuty extends Model
{
    /** @use HasFactory<UserCertificationDutyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'duty',
        'scope_type',
        'scope_id',
        'granted_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'duty' => CertificationDuty::class,
            'scope_type' => PrivilegeScopeType::class,
            'scope_id' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
