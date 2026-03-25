@extends('layouts.app')

@section('title', 'Activities')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Activities</h1>
            <a href="{{ route('activities.create') }}" class="btn btn-primary">Log Activity</a>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
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