<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasProgramScope;

    protected $fillable = [
        'name',
        'active',
        'po_number',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'organization_state')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')->withTimestamps();
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_organization')
            ->withPivot(['payor_source', 'recipient'])
            ->withTimestamps();
    }

    public function kfsAccounts(): BelongsToMany
    {
        return $this->belongsToMany(KfsAccount::class, 'agreement_organization_kfs_account')
            ->withPivot(['agreement_id'])
            ->withTimestamps();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_organization')->withTimestamps();
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'organization_program')->withTimestamps();
    }
}
