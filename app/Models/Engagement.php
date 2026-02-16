<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Engagement extends Model
{
    public const TYPES = [
        'technical_assistance',
        'coaching',
        'training',
    ];

    protected $fillable = [
        'project_id',
        'user_id',
        'engagement_date',
        'engagement_type',
        'hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'hours' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withTimestamps();
    }
}
