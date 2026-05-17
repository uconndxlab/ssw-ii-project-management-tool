@extends('layouts.app')

@section('title', $state->name)

@section('content')
<x-entity-show
    title="{{ $state->name }}"
    type="State"
    typeBadgeClass="bg-info text-dark"
    editRoute="{{ auth()->user()->isAdmin() ? route('states.edit', $state) : null }}"
    backRoute="{{ route('states.index') }}"
    backLabel="All States"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $state->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $state->organizations->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success rounded-pill">{{ $state->agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Added</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $state->created_at->format('M d, Y') }}</dd>
        </dl>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4">
            {{-- Organizations --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Organizations
                    <span class="badge bg-primary rounded-pill ms-1">{{ $state->organizations->count() }}</span>
                </h6>
                @forelse($state->organizations as $org)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <a href="{{ route('organizations.show', $org) }}" class="text-decoration-none fw-semibold">
                            {{ $org->name }}
                        </a>
                        <span class="badge bg-light text-dark">{{ $org->agreements->count() }} agreements</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No organizations in this state yet.</p>
                @endforelse
            </div>

            {{-- Agreements --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Agreements
                    <span class="badge bg-success rounded-pill ms-1">{{ $state->agreements->count() }}</span>
                </h6>
                @forelse($state->agreements->sortBy('name') as $agreement)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none fw-semibold d-block">
                            {{ $agreement->name }}
                        </a>
                        @if($agreement->organizations->isNotEmpty())
                            <small class="text-muted">{{ $agreement->organizations->pluck('name')->join(', ') }}</small>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">No agreements linked to this state yet.</p>
                @endforelse
            </div>
        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        {{-- TODO: link state to activities once activity_state pivot is surfaced here --}}
        <p class="text-muted small mb-0">
            Activity data is tracked at the agreement level.
            View individual <a href="{{ route('agreements.index') }}">agreements</a> for activity logs.
        </p>
    </x-slot:activity>
</x-entity-show>
@endsection
