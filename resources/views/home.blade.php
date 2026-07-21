@extends('layouts.app')

@section('title', 'Home')

@section('content')

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-6 mb-0">Home</h1>
            <p class="text-muted mt-1">Welcome back, {{ $user->name }}</p>
        </div>
    </div>

    <!-- Quick Actions -->
    @include('home.partials.quick-actions')

    <!-- Stats Snapshot -->
    @include('home.partials.stats')

    <!-- My Work -->
    @include('home.partials.my-work')

    <!-- Quick Search -->
    @include('home.partials.search')

    <!-- Recent System Activity -->
    @include('home.partials.recent-activity', ['recentActivities' => $recentActivities ?? collect()])
</div>

@endsection
