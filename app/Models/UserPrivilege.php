<?php

namespace App\Models;

use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use Database\Factories\UserPrivilegeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PrivilegeCapability $capability
 * @property PrivilegeScopeType $scope_type
 */
class UserPrivilege extends Model
{
    /** @use HasFactory<UserPrivilegeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'capability',
        'scope_type',
        'scope_id',
    ];

    protected function casts(): array
    {
        return [
            'capability' => PrivilegeCapability::class,
            'scope_type' => PrivilegeScopeType::class,
            'scope_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->scope_type === PrivilegeScopeType::System;
    }
}
