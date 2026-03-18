@extends('layouts.app')

@section('title', 'Agreements')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Agreements</h1>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('agreements.create') }}" class="btn btn-primary">Create Agreement</a>
            @endif
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form
                    id="agreement-filters"
                    hx-get="{{ route('agreements.index') }}"
                    hx-target="#agreements-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                >
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input
                                type="text"
                                class="form-control"
                                id="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search agreements, organizations, or states"
                                hx-get="{{ route('agreements.index') }}"
                                hx-trigger="keyup changed delay:400ms, search"
                                hx-target="#agreements-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                                hx-include="#agreement-filters"
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="organization_id" class="form-label">Organization</label>
                            <select
                                class="form-select"
                                id="organization_id"
                                name="organization_id"
                                hx-get="{{ route('agreements.index') }}"
                                hx-trigger="change"
                                hx-target="#agreements-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                                hx-include="#agreement-filters"
                            >
                                <option value="">All Organizations</option>
                                @foreach($organizations as $organization)
                                    <option
                                        value="{{ $organization->id }}"
                                        @selected((string) request('organization_id') === (string) $organization->id)
                                    >
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="state_id" class="form-label">State</label>
                            <select
                                class="form-select"
                                id="state_id"
                                name="state_id"
                                hx-get="{{ route('agreements.index') }}"
                                hx-trigger="change"
                                hx-target="#agreements-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                                hx-include="#agreement-filters"
                            >
                                <option value="">All States</option>
                                @foreach($states as $state)
                                    <option
                                        value="{{ $state->id }}"
                                        @selected((string) request('state_id') === (string) $state->id)
                                    >
                                        {{ $state->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label d-block">&nbsp;</label>
                            <a href="{{ route('agreements.index') }}" class="btn btn-outline-secondary w-100">
                                Reset
                            </a>
                        </div>
                    </div>

                    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
                    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
                </form>
            </div>
        </div>
    </div>
</div>

<div id="agreements-table">
    @include('agreements.partials.table', [
        'agreements' => $agreements,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection