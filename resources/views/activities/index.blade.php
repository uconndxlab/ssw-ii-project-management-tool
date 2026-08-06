@extends('layouts.app')

@section('title', 'Activities')

@section('content')

<x-page-header context="index" title="Activities" description="{{ $activities->total() }} total">
    <x-slot:controls>
        <a href="{{ route('activities.create') }}" class="btn btn-primary">+ Log Activity</a>
    </x-slot:controls>
</x-page-header>

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
