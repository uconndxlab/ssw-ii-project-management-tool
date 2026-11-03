<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivityReviewRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Activity $activity, public User $requestedBy)
    {
        // Queue after the activity transaction commits so the link never reaches a coach before the record exists.
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $coach): MailMessage
    {
        $activity = $this->activity->loadMissing(['activityType', 'agreements:id,name', 'certificationCandidates.candidate.enrollments']);
        $type = $activity->activityType instanceof ActivityType ? $activity->activityType->name : 'Activity';
        $mine = $activity->supervisedCandidateNames([$coach->id]);
        $others = $activity->certificationCandidates->pluck('candidate.name')->filter()->diff($mine);

        return (new MailMessage)
            ->subject("Review requested: {$type} on {$activity->engagement_date->format('M j')}")
            ->markdown('mail.activity-review-requested', [
                'firstName' => strtok($coach->name, ' '),
                'requester' => $this->requestedBy->name,
                'type' => $type,
                'date' => $activity->engagement_date->format('M j, Y'),
                'agreements' => $activity->agreements->pluck('name')->join(', '),
                'mine' => $mine->join(', '),
                'others' => $others->join(', '),
                'url' => route('activities.show', $activity),
            ]);
    }
}
