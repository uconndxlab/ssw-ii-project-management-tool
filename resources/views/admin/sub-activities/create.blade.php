@extends('layouts.app')

@section('title', 'Create Sub-activity')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('sub-activities.store') }}" id="sub-activities-create-form">
        @csrf
        <x-page-header form-id="sub-activities-create-form" save-label="Create Sub-activity" context="form" entity-type="Sub-activity" mode="create" />
        @include('admin.sub-activities.partials.form-fields')
    </form>
</x-form-shell>
@endsection
