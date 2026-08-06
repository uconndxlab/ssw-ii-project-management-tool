@extends('layouts.app')

@section('title', 'Create Organization')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <x-page-header context="form" entity-type="Organization" />

        <form method="POST" action="{{ route('organizations.store') }}" id="organizations-create-form">
            @csrf
            @include('organizations.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="organizations-create-form" save-label="Create Organization" />
@endsection
