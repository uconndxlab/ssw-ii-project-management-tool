<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    protected $fillable = [
        'name',
        'organization_id',
        'state_id',
        'abstract',
        'start_date',
        'end_date',
        'original_end_date',
        'extended_end_date',
        'certification_candidates',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'original_end_date' => 'date',
            'extended_end_date' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agreement_user')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(AgreementDeliverable::class);
    }
}
