<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case OnTrack = 'on_track';
    case NeedsAttention = 'needs_attention';
    case OffTrack = 'off_track';
    case Complete = 'complete';
    case ProgressMade = 'progress_made';
    case NoProgressMade = 'no_progress_made';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'On Track',
            self::NeedsAttention => 'Needs Attention',
            self::OffTrack => 'Off Track',
            self::Complete => 'Complete',
            self::ProgressMade => 'Progress Made',
            self::NoProgressMade => 'No Progress Made',
            self::NotApplicable => 'N/A',
        };
    }

    public function icon(): ?string
    {
        return match ($this) {
            self::OnTrack, self::Complete => 'check-circle-fill',
            self::NeedsAttention => 'exclamation-triangle-fill',
            self::OffTrack => 'x-circle-fill',
            self::ProgressMade => 'arrow-right-circle-fill',
            self::NoProgressMade => 'dash-circle-fill',
            self::NotApplicable => null,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OnTrack, self::Complete => 'success',
            self::NeedsAttention => 'warning',
            self::OffTrack => 'danger',
            self::ProgressMade => 'info',
            self::NoProgressMade => 'secondary',
            self::NotApplicable => 'muted',
        };
    }
}
