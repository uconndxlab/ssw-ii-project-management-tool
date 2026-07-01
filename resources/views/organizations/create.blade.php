@extends('layouts.app')

@section('title', 'Create Organization')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Organization</h1>
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

                <form method="POST" action="{{ route('organizations.store') }}" id="organizations-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Organization Name</label>
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

                    <div class="mb-3">
                        <label class="form-label">State(s)</label>
                        <x-token-picker
                            picker-id="organization-states"
                            name="state_ids[]"
                            :items="$states"
                            :selected-ids="old('state_ids', [])"
                            placeholder="Search states..."
                            :height="'300px'"
                        />
                        @error('state_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Projects</label>
                        <x-token-picker
                            picker-id="organization-projects"
                            name="project_ids[]"
                            :items="$projects"
                            :selected-ids="old('project_ids', [])"
                            placeholder="Search projects..."
                        />
                        @error('project_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Programs</label>
                        <x-token-picker
                            picker-id="organization-programs"
                            name="program_ids[]"
                            :items="$programs"
                            :selected-ids="old('program_ids', [])"
                            placeholder="Search programs..."
                        />
                        @error('program_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="organizations-create-form" cancel-url="{{ route('organizations.index') }}" save-label="Create Organization" />
@endsection
