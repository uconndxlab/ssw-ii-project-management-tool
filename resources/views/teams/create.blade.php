@extends('layouts.app')

@section('title', 'Create Team')

@section('content')
@php
    $teamUserOptions = $users->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Team</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
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

                <form method="POST" action="{{ route('teams.store') }}" id="teams-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name</label>
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
                        <label for="active" class="form-label">Status</label>
                        <select class="form-select @error('active') is-invalid @enderror" 
                                id="active" 
                                name="active" 
                                required>
                            <option value="1" {{ old('active', '1') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('active') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Team Members</label>

                        <x-token-picker
                            picker-id="team-create-users"
                            name="user_ids[]"
                            :options="$teamUserOptions"
                            :selected-ids="old('user_ids', [])"
                            label-key="label"
                            value-key="value"
                            search-key="search"
                            placeholder="Search to add members..."
                            :height="'300px'"
                        />

                        <small class="text-muted">
                            Select users who should be members of this team.
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Team</button>
                        <a href="{{ route('teams.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="teams-create-form" cancel-url="{{ route('teams.index') }}" save-label="Create Team" />
@endsection
