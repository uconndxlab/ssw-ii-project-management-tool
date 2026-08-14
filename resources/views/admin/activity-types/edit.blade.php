@extends('layouts.app')

@section('title', 'Edit Activity Type')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('activity-types.update', $activityType) }}" id="activity-types-edit-form">
        @csrf
        @method('PUT')
        <x-page-header
            context="form"
            :title="old('name', $activityType->name)"
            entity-type="Activity Type"
            mode="edit"
        />
        @include('admin.activity-types.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="activity-types-edit-form" save-label="Save Activity Type" :last-saved-at="$activityType->updated_at" />
@endsection
