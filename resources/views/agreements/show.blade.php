@extends('layouts.app')

@section('title', $agreement->name)

@section('content')

@php
    $agreementProgramIds = $agreement->programs->pluck('id')->map(fn ($id) => (int) $id);
    $activityOnlyPrograms = $programs->filter(fn ($program) => !$agreementProgramIds->contains((int) $program->id));
    $organizationKfsNumbers = $agreement->organizationKfsAccounts
        ->groupBy(fn ($account) => (int) $account->pivot->organization_id)
        ->map(fn ($accounts) => $accounts->pluck('number')->sort()->values()->all())
        ->all();
    $periodStart = $agreement->start_date;
    $periodEnd = $agreement->extension_end_date ?? $agreement->end_date;
    $periodSuffix = null;
    if ($periodStart && $periodEnd) {
        $today = today(config('app.timezone'));
        if ($periodStart->greaterThan($today)) {
            $days = (int) $today->diffInDays($periodStart);
            $periodSuffix = 'Starts in '.$days.' '.\Illuminate\Support\Str::plural('day', $days);
        } elseif ($periodEnd->lessThan($today)) {
            $days = (int) $periodEnd->diffInDays($today);
            $periodSuffix = 'Ended '.$days.' '.\Illuminate\Support\Str::plural('day', $days).' ago';
        } else {
            $days = (int) $today->diffInDays($periodEnd);
            $periodSuffix = $days === 0 ? 'Ends today' : $days.' '.\Illuminate\Support\Str::plural('day', $days).' left';
        }
        // Track positions as a share of the whole period (0–100).
        $periodDays = max(1, $periodStart->diffInDays($periodEnd));
        $elapsedPercent = round(min(100, max(0, $periodStart->diffInDays($today, false) / $periodDays * 100)), 2);
        $extensionPercent = $agreement->extension_end_date && $agreement->end_date
            ? round(min(100, max(0, $periodStart->diffInDays($agreement->end_date) / $periodDays * 100)), 2)
            : null;
    }
@endphp

<x-page-header
    context="show"
    :title="$agreement->name"
    entity-type="Agreement"
    :active="$agreement->active"
>
    <x-slot:facts>
        <div><dt>Period</dt><dd>
            @if($periodStart && $periodEnd)
                {{ $periodStart->format('M j, Y') }} – {{ $periodEnd->format('M j, Y') }} · {{ $periodSuffix }}
                @if($agreement->extension_end_date && $agreement->end_date)<span class="text-muted">· extended from {{ $agreement->end_date->format('M j, Y') }}</span>@endif
                <div @class(['app-period-track', 'app-period-track--ended' => $elapsedPercent >= 100]) aria-hidden="true">
                    @if($extensionPercent !== null)<span class="app-period-extension" style="left: {{ $extensionPercent }}%"></span>@endif
                    <span class="app-period-elapsed" style="width: {{ $elapsedPercent }}%"></span>
                    @if($elapsedPercent > 0 && $elapsedPercent < 100)<span class="app-period-today" style="left: {{ $elapsedPercent }}%"></span>@endif
                </div>
            @else
                Not set
            @endif
        </dd></div>
        <div><dt>{{ $agreement->principalInvestigators->count() > 1 ? 'Principal investigators' : 'Principal investigator' }}</dt><dd>
            @forelse($agreement->principalInvestigators as $investigator)
                <x-user-link :user="$investigator" />@if(!$loop->last), @endif
            @empty
                Not assigned
            @endforelse
        </dd></div>
    </x-slot:facts>
</x-page-header>

@can('duplicate', $agreement)
    <form id="agreement-duplicate-form" method="POST" action="{{ route('agreements.duplicate', $agreement) }}" class="d-none">
        @csrf
    </form>
@endcan

@php
    $tabs = ['overview' => 'Overview', 'activity' => 'All activity', 'deliverables' => 'Deliverables', 'certification' => 'Certification'];
    if (auth()->user()->can('create', [\App\Models\ReportRequest::class, $agreement])) {
        $tabs['reporting'] = 'Reporting';
    }
@endphp

<x-section-tabs :tabs="$tabs" label="Agreement sections" :hash="true" :sticky-title="$agreement->name" class="mb-4">
    <x-slot:actions>
        @if($agreement->active)
            <a class="btn btn-primary btn-sm text-nowrap" href="{{ route('activities.create', ['agreement_id' => $agreement->id]) }}">Log activity</a>
        @else
            <button type="button" class="btn btn-primary btn-sm text-nowrap" disabled aria-describedby="agreement-inactive-reason">Log activity</button>
            <span class="text-muted small" id="agreement-inactive-reason">This agreement is inactive.</span>
        @endif
        @can('update', $agreement)<a class="btn btn-outline-primary btn-sm" href="{{ route('agreements.edit', $agreement) }}">Edit</a>@endcan
        @can('duplicate', $agreement)
            <div class="dropdown">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button type="submit"
                                form="agreement-duplicate-form"
                                class="dropdown-item"
                                data-confirm
                                data-confirm-title="Duplicate agreement?"
                                data-confirm-record="{{ $agreement->name }}"
                                data-confirm-message="Activities and logged progress will not be copied. Review the new copy before saving."
                                data-confirm-action="Duplicate agreement"
                                data-confirm-tone="primary">
                            <i class="bi bi-files me-2" aria-hidden="true"></i>Duplicate
                        </button>
                    </li>
                </ul>
            </div>
        @endcan
    </x-slot:actions>
    <div id="section-overview" data-section="overview">
        <div class="app-overview-grid" data-overview-grid>
            <div class="app-overview-column" data-overview-column="work">
                @include('agreements.partials.overview.deliverables')
                @include('agreements.partials.overview.candidates')
                @include('agreements.partials.overview.activity')
            </div>
            <div class="app-overview-column" data-overview-column="connections">
                @include('agreements.partials.overview.about')
                @include('agreements.partials.overview.organizations')
                @include('agreements.partials.overview.staff')
                @include('agreements.partials.overview.coverage')
            </div>
        </div>
    </div>
    <div id="section-activity" data-section="activity" hidden>
        <div id="agreement-activities"
             hx-get="{{ route('activities.index', ['agreement_id' => $agreement->id, 'embedded' => 1]) }}"
             hx-trigger="intersect once" hx-swap="innerHTML" hx-push-url="false">
            <p class="text-muted" role="status">Loading activities…</p>
        </div>
        <x-activity-action-log-modal />
    </div>
    <div id="section-deliverables" data-section="deliverables" hidden>
        <div class="card shadow-sm entity-show-card">
            <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0">Deliverables</h2>
            </div>
            <div class="card-body">
                @include('agreements.partials.show-deliverables', ['deliverableGroups' => $deliverableGroups])
            </div>
        </div>
    </div>
    <div id="section-certification" data-section="certification" hidden>
        <section class="card shadow-sm entity-show-card" aria-labelledby="certificate-assignments-heading">
            <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0" id="certificate-assignments-heading">Certificate assignments</h2>
                <span class="text-muted small">{{ $certificationRosterDisplay->count() }} {{ \Illuminate\Support\Str::plural('candidate', $certificationRosterDisplay->count()) }}</span>
                @can('update', $agreement)
                    <a href="{{ route('agreements.edit', $agreement) }}#certification-candidates" class="btn btn-sm btn-outline-primary ms-auto">Manage assignments</a>
                @endcan
            </div>
            <div class="card-body">
                @forelse($certificationRosterDisplay as $entry)
                    @php
                        $person = $entry['roster']->candidate;
                        $assignments = $entry['enrollments'];
                    @endphp
                    @if($person)
                        <div class="py-3 {{ $loop->first ? 'pt-0' : 'border-top' }} {{ $loop->last ? 'pb-0' : '' }}">
                            <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <h3 class="h6 mb-0 text-break">@can('view', $person)<a class="app-link" href="{{ route('certification-candidates.show', $person) }}">{{ $person->name }}</a>@else{{ $person->name }}@endcan</h3>
                                    @if($person->email)<div class="small text-muted text-break">{{ $person->email }}</div>@endif
                                    @if($person->start_date)<div class="small text-muted">Start date: {{ $person->start_date->format('M j, Y') }}</div>@endif
                                </div>
                                @can('update', $agreement)
                                    <a class="app-link small ms-auto" href="{{ route('agreements.edit', $agreement) }}#certification-candidate-{{ $person->id }}">{{ $assignments->isEmpty() ? 'Assign a certificate' : 'Edit assignments' }}</a>
                                @endcan
                            </div>
                            @forelse($assignments as $enrollment)
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 py-2">
                                    <div class="min-w-0">
                                        <div class="fw-medium text-break">{{ $enrollment->certificate?->name ?? 'Certificate' }}</div>
                                        <div class="small text-muted">
                                            {{ $enrollment->supervisingCoach ? 'Coach: '.$enrollment->supervisingCoach->name : 'No coach selected' }}
                                            @if($enrollment->enrolled_at) · Assigned {{ $enrollment->enrolled_at->format('M j, Y') }}@endif
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">@php $lifecycleBadge = $enrollment->lifecycleBadge(); @endphp<span class="{{ $lifecycleBadge['class'] }}">{{ $lifecycleBadge['label'] }}</span>
                                        <a href="{{ route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreement->id]) }}" class="app-link small">Progress</a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">No certificates assigned.</p>
                            @endforelse
                        </div>
                    @endif
                @empty
                    <p class="text-muted mb-0">Add a candidate to assign certificates on this agreement.</p>
                @endforelse
            </div>
        </section>
    </div>
    @can('create', [\App\Models\ReportRequest::class, $agreement])
        <div id="section-reporting" data-section="reporting" hidden>
            @include('agreements.partials.reporting-tab', compact('agreement', 'reportRequests', 'reportActivityCount'))
        </div>
    @endcan
</x-section-tabs>
@endsection
