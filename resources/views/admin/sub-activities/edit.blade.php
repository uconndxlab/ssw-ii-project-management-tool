@extends('layouts.app')

@section('title', 'Edit Sub-activity')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('sub-activities.update', $subActivity) }}" id="sub-activities-edit-form">
        @csrf
        @method('PUT')
        <x-page-header form-id="sub-activities-edit-form" save-label="Save Sub-activity" :last-saved-at="$subActivity->updated_at"
            context="form"
            :title="old('name', $subActivity->name)"
            entity-type="Sub-activity"
            mode="edit"
        />
        @include('admin.sub-activities.partials.form-fields')
    </form>
</x-form-shell>
@endsection
