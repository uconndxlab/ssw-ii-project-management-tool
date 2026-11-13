<x-record-section title="Candidates" :count="$certificationRosterDisplay->count()" link="#certification" link-label="View certification" link-section="certification">
    <x-record-list kind="candidates" :count="$certificationRosterDisplay->count()">
        @forelse($certificationRosterDisplay as $entry)
            @php $candidate = $entry['roster']->candidate; @endphp
            @continue(! $candidate)
            <x-relationship-ledger-row :title="$candidate->name" :href="auth()->user()->can('view', $candidate) ? route('certification-candidates.show', $candidate) : null" multi-link>
                <x-slot:actions><x-restricted-count :count="$entry['restricted_count']" kind="certificates" /></x-slot:actions>
                <x-slot:footer>
                    <div class="w-100">
                        @forelse($entry['enrollments'] as $enrollment)
                            @php $badge = $enrollment->lifecycleBadge(); @endphp
                            <div class="mb-2">
                                <a class="app-link" href="{{ route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreement->id]) }}">{{ $enrollment->certificate->name }}</a>
                                <span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                <div class="text-muted small">{{ $enrollment->supervisingCoach ? 'Coach '.$enrollment->supervisingCoach->name : 'No coach selected' }}</div>
                            </div>
                        @empty
                            <span class="text-muted">No certificate assigned</span>
                        @endforelse
                    </div>
                </x-slot:footer>
            </x-relationship-ledger-row>
        @empty
            <p class="text-muted small mb-0">No candidates assigned.</p>
        @endforelse
    </x-record-list>
</x-record-section>
