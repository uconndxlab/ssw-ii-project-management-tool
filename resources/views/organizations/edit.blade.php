@extends('layouts.app')

@section('title', 'Edit Organization')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('organizations.update', $organization) }}" id="organizations-edit-form">
        @csrf
        @method('PUT')
        <x-page-header form-id="organizations-edit-form" save-label="Save Organization" :last-saved-at="$organization->updated_at" context="form" :title="old('name', $organization->name)" entity-type="Organization" mode="edit" />
        @if($organization->agreements_count === 0)
            <x-form-note title="Before you start">Organizations appear on Log Activity only after they're added to at least one agreement.</x-form-note>
        @endif
        @include('organizations.partials.form-fields', ['organization' => $organization])
    </form>
</x-form-shell>
@endsection
