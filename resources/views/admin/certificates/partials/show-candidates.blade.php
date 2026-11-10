<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
            <thead><tr><th>Candidate</th><th>Status</th><th>Supervising coach</th><th>Phase / cycle</th><th>Progress</th></tr></thead>
            <tbody>
                @forelse($visibleEnrollments as $enrollment)
                    @php $badge = $enrollment->lifecycleBadge(); @endphp
                    <tr>
                        <td class="app-card-lead"><a href="{{ route('certification-candidates.show', $enrollment->candidate) }}" class="app-link app-link--strong app-row-link">{{ $enrollment->candidate->name }}</a></td>
                        <td data-label="Status"><span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                        <td data-label="Supervising coach">{{ $enrollment->supervisingCoach?->name ?? 'Not assigned' }}</td>
                        <td data-label="Phase / cycle">{{ $enrollment->phase->label() }} · {{ $enrollment->cycle }}</td>
                        <td data-label="Progress">@if(in_array($enrollment->id, $progressEnrollmentIds, true))<a href="{{ route('certificate-enrollments.show', $enrollment) }}" class="app-link">Progress</a>@else<span class="text-muted">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-5">No candidates you can see are enrolled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        {{ $visibleEnrollments->fragment('candidates')->links() }}
        @if($hiddenEnrollmentCount > 0)<p class="small text-muted mb-0">+{{ $hiddenEnrollmentCount }} others you can't see</p>@endif
    </div>
</div>
