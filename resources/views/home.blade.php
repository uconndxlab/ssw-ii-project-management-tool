@extends('layouts.app')

@section('title', 'Home')

@section('content')

<div class="container-fluid py-4">
    <x-page-header
        context="dashboard"
        title="Home"
        description="Welcome back, {{ $user->name }}"
    />

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
