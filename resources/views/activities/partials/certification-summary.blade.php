@if(count($certificationForm['saved']) || $activity->certification_review_requested_at)
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="h5 mb-0">Certification</h2>
        </div>
        <div class="card-body">
            @if($activity->certification_review_requested_at)<p><span class="app-status app-status--warning">Needs certification review</span></p>@endif
            @foreach($certificationForm['saved'] as $tag)
                @php $person = collect($certificationForm['candidates'])->firstWhere('id', $tag['certification_candidate_id']); @endphp
                <div class="border-bottom pb-2 mb-2">
                    <strong>{{ $person['name'] }}</strong>
                    <span class="small text-muted">{{ $tag['role_name'] ?? 'No role' }} · {{ $tag['completion_units'] }} unit(s) · {{ $tag['submission_count'] }} submission(s)</span>
                    @foreach($person['enrollments'] as $enrollment)
                        @if($enrollment['url'])<div><a class="small" href="{{ route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment['id'], 'agreement_id' => $tag['agreement_id']]) }}">{{ $enrollment['name'] }} progress</a></div>@endif
                    @endforeach
                </div>
            @endforeach
            @can('update', $activity)<a href="{{ route('activities.edit', $activity) }}#activity-certification" class="small">Edit certification evidence</a>@endcan
        </div>
    </div>
@endif
