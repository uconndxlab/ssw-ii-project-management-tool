@extends('layouts.app')

@section('title', 'Create Logging Field')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <x-page-header context="form" entity-type="Logging Field" description="Define a new field that can be used across the application." />

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

                <form method="POST" action="{{ route('logging-fields.store') }}" id="logging-field-create-form">
                    @csrf
                    @include('logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="logging-field-create-form" save-label="Create Logging Field" />

@endsection
