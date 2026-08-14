@extends('layouts.app')

@section('title', 'Edit Organization')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('organizations.update', $organization) }}" id="organizations-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="old('name', $organization->name)" entity-type="Organization" mode="edit" />
        @include('organizations.partials.form-fields', ['organization' => $organization])
    </form>
</x-form-shell>
<x-save-bar form-id="organizations-edit-form" save-label="Save Organization" :last-saved-at="$organization->updated_at" />
@endsection
