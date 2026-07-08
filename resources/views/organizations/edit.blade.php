@extends('layouts.app')

@section('title', 'Edit Organization')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-md-6">
        <h1>Edit Organization</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <form method="POST" action="{{ route('organizations.update', $organization) }}" id="organizations-edit-form">
            @csrf
            @method('PUT')
            @include('organizations.partials.form-fields', ['organization' => $organization])
        </form>
    </div>
</div>
<x-save-bar form-id="organizations-edit-form" cancel-url="{{ route('organizations.index') }}" save-label="Save Organization" :last-saved-at="$organization->updated_at" />
@endsection
