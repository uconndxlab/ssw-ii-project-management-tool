@extends('layouts.app')

@section('title', 'Edit Activity Type')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <h1>Edit Activity Type</h1>
    </div>
</div>

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
            @include('admin.activity-types.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="activity-types-edit-form" cancel-url="{{ route('activity-types.index') }}" save-label="Save Activity Type" :last-saved-at="$activityType->updated_at" />
@endsection
