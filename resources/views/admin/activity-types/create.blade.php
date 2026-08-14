@extends('layouts.app')

@section('title', 'Create Activity Type')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('activity-types.store') }}" id="activity-types-create-form">
        @csrf
        <x-page-header context="form" entity-type="Activity Type" mode="create" />
        @include('admin.activity-types.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="activity-types-create-form" save-label="Create Activity Type" />
@endsection
