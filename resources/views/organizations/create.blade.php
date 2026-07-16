@extends('layouts.app')

@section('title', 'Create Organization')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <h1>Create Organization</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <form method="POST" action="{{ route('organizations.store') }}" id="organizations-create-form">
            @csrf
            @include('organizations.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="organizations-create-form" cancel-url="{{ route('organizations.index') }}" save-label="Create Organization" />
@endsection
