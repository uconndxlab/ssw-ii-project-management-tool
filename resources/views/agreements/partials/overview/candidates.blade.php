<x-record-section title="Candidates" :count="$certificationRosterDisplay->count()" link="#certification" link-label="View certification" link-section="certification">
    <x-record-list kind="candidates" :count="$certificationRosterDisplay->count()">
        @forelse($certificationRosterDisplay as $entry)
            @php $candidate = $entry['roster']->candidate; @endphp
            @continue(! $candidate)
            <div class="app-ledger-group" data-ledger-group>
                <div class="app-ledger-group-head">
                    @can('view', $candidate)<a class="app-link" href="{{ route('certification-candidates.show', $candidate) }}">{{ $candidate->name }}</a>@else{{ $candidate->name }}@endcan
                    <x-restricted-count :count="$entry['restricted_count']" kind="certificates" />
                </div>
                @forelse($entry['enrollments'] as $enrollment)
                    @php $badge = $enrollment->lifecycleBadge(); @endphp
                    <x-relationship-ledger-row :title="$enrollment->certificate->name" :href="route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreement->id])" :subtitle="$enrollment->supervisingCoach ? 'Coach '.$enrollment->supervisingCoach->name : 'No coach selected'" class="app-ledger-row--status-aside">
                        <x-slot:actions><span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span></x-slot:actions>
                    </x-relationship-ledger-row>
                @empty
                    <div class="app-ledger-row app-ledger-row--simple text-muted small">No certificate assigned</div>
                @endforelse
            </div>
        @empty
            <p class="text-muted small mb-0">No candidates assigned.</p>
        @endforelse
    </x-record-list>
</x-record-section>
