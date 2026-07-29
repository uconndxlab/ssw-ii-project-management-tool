@extends('layouts.app')

@section('title', 'Activities')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Activities</h1>
        <p class="text-muted small mb-0">{{ $activities->total() }} total</p>
    </div>
    <a href="{{ route('activities.create') }}" class="btn btn-primary">+ Log Activity</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="activity-filters-container">
            @include('activities.partials.filters', [
                'states' => $states,
                'organizations' => $organizations,
                'agreements' => $agreements,
                'activityTypes' => $activityTypes,
                'sort' => $sort,
                'direction' => $direction,
            ])
        </div>
    </div>
</div>

<div id="activities-table">
    @include('activities.partials.table', [
        'activities' => $activities,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>

@endsection
