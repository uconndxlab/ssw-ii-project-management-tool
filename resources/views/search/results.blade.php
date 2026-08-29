@extends('layouts.app')

@section('title', $query ? "Search: {$query}" : 'Search')

@section('content')
<div class="container-fluid py-4">
    <x-page-header
        context="index"
        title="Search"
        :description="$query ? 'Results for ' . $query : 'Search agreements, organizations, and people.'"
    />

    {{-- Search bar --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <form action="{{ route('search') }}" method="get" class="d-flex gap-2">
                <input type="text"
                       name="q"
                       class="form-control"
                       placeholder="Search agreements, organizations, people..."
                       value="{{ $query }}"
                       autofocus>
                <button type="submit" class="btn btn-primary text-nowrap">Search</button>
            </form>
        </div>
    </div>

    @if($query)

    {{-- No results --}}
    @if($agreements->isEmpty() && $organizations->isEmpty() && $users->isEmpty())
        <div class="alert alert-light border">
            No results found for <strong>{{ $query }}</strong>.
        </div>
    @endif

    <div class="row g-4">

        {{-- Agreements --}}
        @if($agreements->isNotEmpty())
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success bg-opacity-10">
                    <h6 class="mb-0 fw-semibold text-success">
                        Agreements
                        <span class="badge bg-success rounded-pill ms-1">{{ $agreements->count() }}</span>
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($agreements as $agreement)
                        <a href="{{ route('agreements.show', $agreement) }}"
                           class="list-group-item list-group-item-action">
                            <div class="fw-semibold">{{ $agreement->name }}</div>
                            <div class="small text-muted">
                                @if($agreement->organizations->isNotEmpty())
                                    {{ $agreement->organizations->pluck('name')->join(', ') }}
                                @endif
                                @if($agreement->states->isNotEmpty())
                                    &middot; {{ $agreement->states->pluck('name')->join(', ') }}
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Organizations --}}
        @if($organizations->isNotEmpty())
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary bg-opacity-10">
                    <h6 class="mb-0 fw-semibold text-primary">
                        Organizations
                        <span class="badge bg-primary rounded-pill ms-1">{{ $organizations->count() }}</span>
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($organizations as $org)
                        <a href="{{ route('organizations.show', $org) }}"
                           class="list-group-item list-group-item-action">
                            <div class="fw-semibold">{{ $org->name }}</div>
                            @if($org->states->isNotEmpty())
                                <div class="small text-muted">{{ $org->states->pluck('name')->join(', ') }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- People (admin only) --}}
        @if($users->isNotEmpty())
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-secondary bg-opacity-10">
                    <h6 class="mb-0 fw-semibold text-secondary">
                        People
                        <span class="badge bg-secondary rounded-pill ms-1">{{ $users->count() }}</span>
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($users as $person)
                        @php $personHref = \App\Support\UserProfileLink::route($person); @endphp
                        @if($personHref)
                        <a href="{{ $personHref }}"
                           class="list-group-item list-group-item-action">
                        @else
                        <div class="list-group-item">
                        @endif
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="fw-semibold">{{ $person->name }}</div>
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    @if(!$person->active)
                                        <span class="badge bg-secondary" style="font-size:.7rem;">Inactive</span>
                                    @endif
                                    <x-category-badge kind="role" style="font-size:.7rem;">{{ $person->accessLabel() }}</x-category-badge>
                                </div>
                            </div>
                            <div class="small text-muted">{{ $person->email }}</div>
                        @if($personHref)
                        </a>
                        @else
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /row --}}
    @endif

</div>
@endsection
