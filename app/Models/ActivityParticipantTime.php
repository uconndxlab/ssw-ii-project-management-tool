<?php

namespace App\Models;

use Database\Factories\ActivityParticipantTimeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipantTime extends Model
{
    /** @use HasFactory<ActivityParticipantTimeFactory> */
    use HasFactory;

    protected $table = 'activity_participant_times';

    protected $fillable = [
        'activity_id',
        'user_id',
        'participant_name',
        'hours',
        'prep_hours',
        'follow_up_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'follow_up_hours' => 'decimal:2',
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
