<?php

namespace App\Models;

use Database\Factories\ActivityContactTimeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityContactTime extends Model
{
    /** @use HasFactory<ActivityContactTimeFactory> */
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'activity_hours',
        'prep_hours',
        'follow_up_hours',
    ];

    protected function casts(): array
    {
        return [
            'activity_hours' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'follow_up_hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
