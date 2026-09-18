<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Pivots\AgreementOrganizationPivot;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * View: you are on organization_user, or a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 *
 * @property ProgramScopeMode $program_scope_mode
 * @property AgreementOrganizationPivot|null $pivot
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @return BelongsToMany<State, $this> */
    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'organization_state')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')->withTimestamps();
    }

    /** @return HasMany<OrganizationContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContact::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return BelongsToMany<Agreement, $this, AgreementOrganizationPivot> */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_organization')
            ->withPivot(['payor_source', 'recipient'])
            ->using(AgreementOrganizationPivot::class)
            ->withTimestamps();
    }

    /** @return BelongsToMany<KfsAccount, $this> */
    public function kfsAccounts(): BelongsToMany
    {
        return $this->belongsToMany(KfsAccount::class, 'agreement_organization_kfs_account')
            ->withPivot(['agreement_id'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Activity, $this> */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_organization')->withTimestamps();
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'organization_program')->withTimestamps();
    }
}
