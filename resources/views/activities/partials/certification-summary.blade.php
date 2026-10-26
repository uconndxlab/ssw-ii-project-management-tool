@if(count($certificationForm['saved']) || $activity->certification_review_requested_at)
    <x-section-card title="Certification">
        @if($activity->certification_review_requested_at)<p class="badge text-bg-warning">Needs certification review</p>@endif
        @foreach($certificationForm['saved'] as $tag)
            @php $person = collect($certificationForm['candidates'])->firstWhere('id', $tag['certification_candidate_id']); @endphp
            <div class="border-bottom pb-2 mb-2">
                <strong>{{ $person['name'] }}</strong>
                <span class="small text-muted">{{ $tag['role_name'] ?? 'No role' }} · {{ $tag['completion_units'] }} unit(s) · {{ $tag['submission_count'] }} submission(s)</span>
                @foreach($person['enrollments'] as $enrollment)
                    @if($enrollment['url'])<div><a class="small" href="{{ $enrollment['url'] }}">{{ $enrollment['name'] }} progress</a></div>@endif
                @endforeach
            </div>
        @endforeach
        @can('update', $activity)<a href="{{ route('activities.edit', $activity) }}#activity-certification" class="small">Edit certification evidence</a>@endcan
    </x-section-card>
@endif
