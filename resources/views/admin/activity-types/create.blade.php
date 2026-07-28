@extends('layouts.app')

@section('title', 'Create Activity Type')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('activity-types.store') }}" id="activity-types-create-form">
            @csrf
            <x-form-page-header
                entity-type="Activity Type"
                entity-type-badge-class="bg-primary"
                mode="create"
                :show-active="true"
                :active-default="old('active', true)"
                active-help="Only active activity types appear in activity forms."
            />
            @include('admin.activity-types.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="activity-types-create-form" cancel-url="{{ route('activity-types.index') }}" save-label="Create Activity Type" />
@endsection
