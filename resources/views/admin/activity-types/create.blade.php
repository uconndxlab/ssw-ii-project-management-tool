@extends('layouts.app')

@section('title', 'Create Activity Type')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Create Activity Type</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
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
                    @include('admin.activity-types.partials.form-fields')

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="activity-types-create-form" cancel-url="{{ route('activity-types.index') }}" save-label="Create Activity Type" />
@endsection
