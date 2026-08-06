@extends('layouts.app')

@section('title', 'Edit Organization')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <x-page-header context="form" :title="old('name', $organization->name)" entity-type="Organization" mode="edit" />

        <form method="POST" action="{{ route('organizations.update', $organization) }}" id="organizations-edit-form">
            @csrf
            @method('PUT')
            @include('organizations.partials.form-fields', ['organization' => $organization])
        </form>
    </div>
</div>
<x-save-bar form-id="organizations-edit-form" save-label="Save Organization" :last-saved-at="$organization->updated_at" />
@endsection
