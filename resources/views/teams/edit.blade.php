@extends('layouts.app')

@section('title', 'Edit Team')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Team: {{ $team->name }}</h1>
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

                <form method="POST" action="{{ route('teams.update', $team) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $team->name) }}" 
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
                            <option value="1" {{ old('active', $team->active) ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !old('active', $team->active) ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Team Members</label>

                        <x-user-picker
                            picker-id="team-edit-users"
                            name="user_ids[]"
                            :users="$users"
                            :selected-ids="old('user_ids', $team->users->pluck('id')->toArray())"
                            search-placeholder="Search to add members..."
                            :show-role="true"
                        />

                        <small class="text-muted">
                            Select users who should be members of this team.
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Team</button>
                        <a href="{{ route('teams.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
