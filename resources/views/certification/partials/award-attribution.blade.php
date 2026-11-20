{{ $award->awarded_at->format('M j, Y') }} ·
@if($award->manual_award)
    Recorded by {{ $award->awardedBy?->name ?? 'Former staff member' }}@if($award->originally_awarded_by) · originally awarded by {{ $award->originally_awarded_by }}@endif
@else
    {{ $award->awardedBy?->name ?? 'Former staff member' }}
@endif
