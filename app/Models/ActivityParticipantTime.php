<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipantTime extends Model
{
    protected $table = 'activity_participant_times';

    protected $fillable = [
        'activity_id',
        'user_id',
        'hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
