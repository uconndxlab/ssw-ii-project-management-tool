@extends('layouts.app')

@section('title', 'Edit Organization')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Organization</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
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

                <form method="POST" action="{{ route('organizations.update', $organization) }}" id="organizations-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Organization Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $organization->name) }}" 
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">State(s)</label>
                        <x-state-picker
                            picker-id="organization-states"
                            name="state_ids[]"
                            :states="$states"
                            :selected-ids="old('state_ids', $organization->states->pluck('id')->toArray())"
                        />
                        @error('state_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="organizations-edit-form" cancel-url="{{ route('organizations.index') }}" save-label="Save Organization" :last-saved-at="$organization->updated_at" />
@endsection
