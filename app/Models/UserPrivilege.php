<?php

namespace App\Models;

use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPrivilege extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSystem(): bool
    {
        return $this->scope_type === PrivilegeScopeType::System;
    }
}
