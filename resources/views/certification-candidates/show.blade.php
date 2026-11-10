@extends('layouts.app')
@section('title', $candidate->name)
@section('content')
<x-page-header context="show" :title="$candidate->name" entity-type="Candidate" :active="$candidate->active" :action-url="auth()->user()->can('update', $candidate) ? route('certification-candidates.edit', $candidate) : null" />
<x-entity-show main-card-title="Certification">
    <x-slot:summary>
        <dl>
            <dt>Email</dt><dd>{{ $candidate->email ?: '—' }}</dd>
            <dt>Phone</dt><dd>{{ $candidate->phone ?: '—' }}</dd>
            <dt>Start date</dt><dd>{{ $candidate->start_date?->format('M j, Y') ?? '—' }}</dd>
            <dt>Notes</dt><dd class="text-break">{{ $candidate->notes ?: '—' }}</dd>
        </dl>
        <x-relationship-scroll-panel title="Agreements" :count="$row['agreements']->count()" collapsible collapsed>
            @foreach($row['agreements'] as $agreement)<x-relationship-ledger-row :title="$agreement->name" :href="route('agreements.show', $agreement)" />@endforeach
        </x-relationship-scroll-panel>
        @include('certification-candidates.partials.hidden-count', ['count' => $row['hiddenAgreements'], 'kind' => 'agreements'])
    </x-slot:summary>
    <x-slot:relationships>
        <x-relationship-scroll-panel title="Enrollments" :count="$row['enrollments']->count()">
            @foreach($row['enrollments'] as $enrollment)
                @php $badge = $enrollment->lifecycleBadge(); @endphp
                <div class="py-3 border-bottom">
                    @can('view', $enrollment->certificate)<a class="app-link" href="{{ route('certificates.show', $enrollment->certificate) }}">{{ $enrollment->certificate->name }}</a>@else<span>{{ $enrollment->certificate->name }}</span>@endcan
                    <span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    <div class="small text-muted">{{ $enrollment->phase->label() }} · Cycle {{ $enrollment->cycle }} · {{ $enrollment->supervisingCoach?->name ?? 'No supervising coach' }}</div>
                    <a class="app-link" href="{{ route('certificate-enrollments.show', $enrollment) }}">Progress<span class="visually-hidden"> for {{ $enrollment->certificate->name }}</span></a>
                </div>
            @endforeach
        </x-relationship-scroll-panel>
        @include('certification-candidates.partials.hidden-count', ['count' => $row['hiddenCertificates'], 'kind' => 'certificates'])
        <h3 class="h6 mt-4">Award history</h3>
        <div class="table-responsive">
            <table class="table table-sm"><thead><tr><th>Certificate</th><th>Cycle</th><th>Awarded</th><th>Expires</th><th>Revoked</th></tr></thead><tbody>
                @forelse($row['enrollments']->flatMap(fn ($enrollment) => $enrollment->awards->map(fn ($award) => ['certificate' => $enrollment->certificate, 'award' => $award])) as $entry)
                    @php $award = $entry['award']; @endphp
                    <tr><td>{{ $entry['certificate']->name }}</td><td>{{ $award->cycle }}</td><td>{{ $award->awarded_at?->format('M j, Y') ?? 'Pending award' }}</td><td>{{ $award->expires_at?->format('M j, Y') ?? 'No expiry' }}</td><td>{{ $award->revoked_at?->format('M j, Y') ?? '—' }}</td></tr>
                @empty<tr><td colspan="5" class="text-muted">No award history.</td></tr>@endforelse
            </tbody></table>
        </div>
    </x-slot:relationships>
    <x-slot:activity>
        <x-recent-activity-table :activities="$recentActivities" hide-agreements :empty-message="$hiddenActivityCount > 0 ? 'No activities you can see.' : null" />
        @include('certification-candidates.partials.hidden-count', ['count' => $hiddenActivityCount, 'kind' => 'activities'])
    </x-slot:activity>
</x-entity-show>
@endsection
