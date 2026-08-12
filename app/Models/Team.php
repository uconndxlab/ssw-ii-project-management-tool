<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory, HasProgramScope;

    protected $fillable = [
        'name',
        'active',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')->withTimestamps();
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_team')->withTimestamps();
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'team_program')->withTimestamps();
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            AgreementDeliverable::class,
            'deliverable_team',
            'team_id',
            'agreement_deliverable_id'
        )
            ->withPivot(['assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }
}
