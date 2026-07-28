@extends('layouts.app')

@section('title', 'Edit Activity Type')

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

        <form method="POST" action="{{ route('activity-types.update', $activityType) }}" id="activity-types-edit-form">
            @csrf
            @method('PUT')
            <x-form-page-header
                entity-type="Activity Type"
                entity-type-badge-class="bg-primary"
                mode="edit"
                :record-name="old('name', $activityType->name)"
                :show-active="true"
                :active-default="old('active', $activityType->active)"
                active-help="Only active activity types appear in activity forms."
            />
            @include('admin.activity-types.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="activity-types-edit-form" cancel-url="{{ route('activity-types.index') }}" save-label="Save Activity Type" :last-saved-at="$activityType->updated_at" />
@endsection
