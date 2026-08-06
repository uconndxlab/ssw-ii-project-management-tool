@extends('layouts.app')

@section('title', 'Create State')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <x-page-header context="form" entity-type="State" />

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

                <form method="POST" action="{{ route('states.store') }}" id="states-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">State Name</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="states-create-form" save-label="Create State" />
@endsection
