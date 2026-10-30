@extends('layouts.app')
@section('title', $enrollment->candidate->name.' — '.$enrollment->certificate->name.' cycle '.$award->cycle.' award')
@section('content')
@php
    $snapshot = $award->requirements_snapshot;
    $status = match (true) {
        $award->revoked_at !== null => ['Revoked '.$award->revoked_at->format('M j, Y'), 'danger'],
        $award->isExpired() => ['Expired '.$award->expires_at?->format('M j, Y'), 'neutral'],
        $superseded => ['Superseded by a later cycle', 'neutral'],
        default => ['Valid', 'success'],
    };
@endphp
<x-form-shell>
    <x-page-header :title="($snapshot['certificate']['name'] ?? $enrollment->certificate->name).' · Cycle '.$award->cycle" :description="$enrollment->candidate->name" entity-type="Certificate award" :breadcrumb-label="'Cycle '.$award->cycle.' award'">
        <x-slot:badges><x-status-badge :tone="$status[1]" :active-label="$status[0]" /></x-slot:badges>
    </x-page-header>
    <x-section-card title="Award">
        <dl class="row small mb-0">
            <dt class="col-sm-3 text-muted fw-normal">Stage</dt><dd class="col-sm-9">{{ $award->phase->label() }} · cycle {{ $award->cycle }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Endorsed</dt><dd class="col-sm-9">{{ $award->endorsed_at->format('M j, Y') }} by {{ $award->endorsedBy?->name ?? 'a former staff member' }}@if($award->endorsement_note) — “{{ $award->endorsement_note }}”@endif</dd>
            <dt class="col-sm-3 text-muted fw-normal">Awarded</dt><dd class="col-sm-9">{{ $award->awarded_at->format('M j, Y') }} by {{ $award->awardedBy?->name ?? 'a former staff member' }}@if($award->award_note) — “{{ $award->award_note }}”@endif</dd>
            <dt class="col-sm-3 text-muted fw-normal">Valid</dt><dd class="col-sm-9">{{ $award->awarded_at->format('M j, Y') }} – {{ $award->expires_at?->format('M j, Y') ?? 'no expiry' }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Prerequisites</dt><dd class="col-sm-9">{{ collect($snapshot['prerequisites'] ?? [])->map(fn ($row) => $row['certificate'].' (cycle '.$row['cycle'].')')->join(', ') ?: 'None' }}</dd>
            @if($award->revoked_at)
                <dt class="col-sm-3 text-muted fw-normal">Revoked</dt><dd class="col-sm-9">{{ $award->revoked_at->format('M j, Y') }} by {{ $award->revokedBy?->name ?? 'a former staff member' }} · Reason: “{{ $award->revoke_reason }}”</dd>
            @endif
        </dl>
        @if($canManage && $award->isLive() && ! $superseded)
            @include('certification.partials.revoke-form')
        @endif
    </x-section-card>
    <x-section-card title="Requirements at endorsement" :subtitle="'Recorded '.$award->endorsed_at->format('M j, Y').'. Later catalog or evidence changes don’t alter this record. '.($snapshot['met'] ?? 0).' of '.($snapshot['total'] ?? 0).' met.'">
        @foreach($snapshot['requirements'] ?? [] as $row)
            <section class="border-bottom pb-3 mb-3" aria-labelledby="snapshot-requirement-{{ $row['id'] }}">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h3 class="h6 mb-0" id="snapshot-requirement-{{ $row['id'] }}">{{ $row['label'] }}</h3>
                    <span class="small text-muted">{{ $row['count'] }} / {{ $row['target'] }}</span>
                    <span class="ms-auto app-status {{ $row['satisfied'] ? 'app-status--success' : 'app-status--neutral' }}">{{ $row['satisfied'] ? 'Met' : 'Not met' }}</span>
                </div>
                @foreach($row['rules'] as $rule)
                    <div class="small text-muted">{{ $rule['dimension'] }} {{ $rule['mode'] }} · {{ implode(', ', $rule['options']) }} · {{ $rule['count'] }} / {{ $rule['target'] }}</div>
                @endforeach
                @if($row['window_start'])<div class="small text-muted">Window from {{ \Illuminate\Support\Carbon::parse($row['window_start'])->format('M j, Y') }}</div>@endif
                @if($row['threshold_note'])<div class="small text-muted">Guidance: {{ $row['threshold_note'] }}</div>@endif
                @if($row['attestation'])
                    <div class="small mt-1">{{ $row['attestation']['satisfied'] ? 'Confirmed' : 'Not confirmed' }} by {{ $row['attestation']['by'] ?? 'a former staff member' }}@if($row['attestation']['at']) on {{ \Illuminate\Support\Carbon::parse($row['attestation']['at'])->format('M j, Y') }}@endif.@if($row['attestation']['note']) <span class="text-muted">{{ $row['attestation']['note'] }}</span>@endif</div>
                @endif
                @if($row['counted'] !== [])
                    <details class="app-details mt-2">
                        <summary class="small">Counted evidence: {{ count($row['counted']) }}</summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm small align-middle mb-0">
                                <thead><tr><th scope="col">Date</th><th scope="col">Activity</th><th scope="col">Evidence</th></tr></thead>
                                <tbody>
                                    @foreach($row['counted'] as $item)
                                        @php $activity = $activities[$item['activity_id']] ?? null; @endphp
                                        <tr>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($item['date'])->format('M j, Y') }}</td>
                                            <td>@if($activity && auth()->user()->can('view', $activity))<a href="{{ route('activities.show', $activity) }}">{{ $item['activity_type'] ?? 'Activity' }}</a>@else{{ $item['activity_type'] ?? 'Activity' }}@endif</td>
                                            <td>
                                                @if($item['submission'])
                                                    <div>{{ ucfirst(str_replace('_', ' ', $item['submission']['outcome'])) }}@if($item['submission']['recorded_by']) · {{ $item['submission']['recorded_by'] }}@endif</div>
                                                    @foreach($item['submission']['options'] as $option)<div>{{ $option }}</div>@endforeach
                                                    @foreach($item['submission']['scores'] as $score)<div>{{ $score['field'] }}: {{ rtrim(rtrim($score['value'], '0'), '.') }}{{ $score['unit'] === 'percent' ? '%' : '' }}</div>@endforeach
                                                    @if($item['submission']['notes'])<div class="text-muted">{{ $item['submission']['notes'] }}</div>@endif
                                                @else
                                                    {{ $item['units'] }} unit(s)
                                                @endif
                                                @if($item['tag_notes'])<div class="text-muted">{{ $item['tag_notes'] }}</div>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>
        @endforeach
    </x-section-card>
</x-form-shell>
@endsection
