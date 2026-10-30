<?php

namespace App\Models;

use Database\Factories\ActivityReviewRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A coach an editor asked to review an activity's certification evidence. Clearing the activity's
 * review flag marks its rows cleared instead of deleting them, so the coach keeps access to the
 * evidence they were asked to review.
 *
 * @property-read Activity $activity
 * @property-read User $coach
 */
class ActivityReviewRequest extends Model
{
    /** @use HasFactory<ActivityReviewRequestFactory> */
    use HasFactory;

    protected $fillable = ['activity_id', 'coach_user_id', 'requested_by_user_id', 'cleared_at'];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }
}
