<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityActionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'activity_id',
        'user_id',
        'action',
        'related_activity_id',
    ];

    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'user_id' => 'integer',
            'action' => ActivityAction::class,
            'related_activity_id' => 'integer',
            'created_at' => 'datetime',
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

    public function relatedActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'related_activity_id');
    }

    public function relatedActivityLabel(): ?string
    {
        if (! $this->related_activity_id) {
            return null;
        }

        $related = $this->relatedActivity;

        if ($related) {
            $date = $related->engagement_date?->format('M j, Y');
            $type = $related->activityType?->name;

            return collect([$date, $type])->filter()->implode(' · ') ?: 'Activity';
        }

        return 'Deleted activity';
    }

    public function relatedActivityHref(): ?string
    {
        $related = $this->relatedActivity;

        if (! $related || ! $related->isLinkable()) {
            return null;
        }

        return route('activities.show', $related);
    }
}
