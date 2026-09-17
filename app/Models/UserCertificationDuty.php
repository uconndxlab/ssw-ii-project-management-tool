<?php

namespace App\Models;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Self-auditing: revoked rows are retained rather than deleted, so no separate audit table exists.
 */
class UserCertificationDuty extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
