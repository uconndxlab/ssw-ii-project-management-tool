@if(count($certificationForm['saved']) || $activity->certification_review_requested_at)
    @php
        $candidates = collect($certificationForm['candidates']);
        $flagged = $activity->certification_review_requested_at;
        $isReviewer = $flagged && auth()->user()->access()->isCertificationReviewerOnActivity($activity);
        $isCoach = auth()->user()->access()->isCertificationCoachOnActivity($activity);
        $askedCoaches = $activity->reviewRequests->whereNull('cleared_at')->pluck('coach.name')->filter()->join(', ');
        $mine = $activity->supervisedCandidateNames([auth()->id()]);
    @endphp
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0">Certification</h2>
                @if($flagged)<span class="app-status app-status--warning">Needs certification review</span>@endif
            </div>
            @if($isCoach)
                @unless($isReviewer)<a href="{{ route('activities.certification.edit', $activity) }}#activity-certification" class="btn btn-sm btn-outline-primary">Edit certification evidence</a>@endunless
            @else
                @can('update', $activity)<a href="{{ route('activities.edit', $activity) }}#activity-certification" class="btn btn-sm btn-outline-primary">Edit certification evidence</a>@endcan
            @endif
        </div>
        <div class="card-body">
            @if($isReviewer)
                <div class="app-review-banner" role="status">
                    <div class="min-w-0">
                        <div class="app-review-banner__title"><strong>{{ $activity->certificationReviewRequestedBy?->name ?? 'Someone' }}</strong> asked you to review certification for <strong>{{ $activity->activityType->name }}</strong></div>
                        <div class="app-review-banner__meta">Requested {{ $flagged->format('M j, Y') }}</div>
                    </div>
                    <a href="{{ route('activities.certification.edit', $activity) }}#activity-certification" class="btn btn-primary flex-shrink-0">Review &amp; record submissions</a>
                </div>
            @elseif($askedCoaches)
                <p class="small text-muted mb-3">Review requested from {{ $askedCoaches }}.</p>
            @endif

            <ul class="app-cert-list list-unstyled mb-0">
                @foreach($certificationForm['saved'] as $tag)
                    @php $person = $candidates->firstWhere('id', $tag['certification_candidate_id']); @endphp
                    <li class="app-cert-list__item">
                        <div class="min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <strong class="app-cert-list__name">{{ $person['name'] }}</strong>
                                @if($mine->contains($person['name']))<span class="badge app-tag">You supervise</span>@endif
                            </div>
                            <div class="small text-muted">
                                {{ $tag['role_name'] ?? 'No role' }} · {{ $tag['completion_units'] }} {{ \Illuminate\Support\Str::plural('unit', $tag['completion_units']) }} · {{ $tag['submission_count'] }} {{ \Illuminate\Support\Str::plural('submission', $tag['submission_count']) }}
                            </div>
                        </div>
                        <div class="app-cert-list__links">
                            @foreach($person['enrollments'] as $enrollment)
                                @if($enrollment['url'])<a class="app-link small" href="{{ route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment['id'], 'agreement_id' => $tag['agreement_id']]) }}">{{ $enrollment['name'] }} progress</a>@endif
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
