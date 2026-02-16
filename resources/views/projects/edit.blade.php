@extends('layouts.app')

@section('title', 'Edit Project')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Project</h1>
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

                <form method="POST" action="{{ route('projects.update', $project) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Project Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $project->name) }}" 
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organization</label>
                                <select class="form-select @error('organization_id') is-invalid @enderror" 
                                        id="organization_id" 
                                        name="organization_id" 
                                        required>
                                    <option value="">Select organization...</option>
                                    @foreach($organizations as $organization)
                                        <option value="{{ $organization->id }}" 
                                            {{ old('organization_id', $project->organization_id) == $organization->id ? 'selected' : '' }}>
                                            {{ $organization->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('organization_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="state_id" class="form-label">State</label>
                                <select class="form-select @error('state_id') is-invalid @enderror" 
                                        id="state_id" 
                                        name="state_id" 
                                        required>
                                    <option value="">Select state...</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}" 
                                            {{ old('state_id', $project->state_id) == $state->id ? 'selected' : '' }}>
                                            {{ $state->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('state_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" 
                                       class="form-control @error('start_date') is-invalid @enderror" 
                                       id="start_date" 
                                       name="start_date" 
                                       value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" 
                                       class="form-control @error('end_date') is-invalid @enderror" 
                                       id="end_date" 
                                       name="end_date" 
                                       value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign Users</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            @foreach($users as $user)
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="user_ids[]" 
                                       value="{{ $user->id }}" 
                                       id="user_{{ $user->id }}"
                                       {{ in_array($user->id, old('user_ids', $project->users->pluck('id')->toArray())) ? 'checked' : '' }}>
                                <label class="form-check-label" for="user_{{ $user->id }}">
                                    {{ $user->name }} ({{ ucfirst($user->role) }})
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Or use HTMX controls below for live updates</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quick Add User (HTMX)</label>
                        <div class="input-group mb-2">
                            <select class="form-select" id="htmx-user-select">
                                <option value="">Select a user to add...</option>
                                @foreach($users->whereNotIn('id', $project->users->pluck('id')) as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
                                @endforeach
                            </select>
                            <button type="button" 
                                    class="btn btn-outline-primary"
                                    onclick="addUserHtmx()">
                                Add User
                            </button>
                        </div>
                        <div id="user-list" class="list-group">
                            @include('projects.partials.user-list', ['project' => $project])
                        </div>
                    </div>

                    <script>
                    function addUserHtmx() {
                        const select = document.getElementById('htmx-user-select');
                        const userId = select.value;
                        if (!userId) return;
                        
                        fetch('{{ route("projects.assign-user", $project) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'text/html'
                            },
                            body: JSON.stringify({ user_id: userId })
                        })
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('user-list').innerHTML = html;
                            select.value = '';
                            // Remove added user from dropdown
                            const option = select.querySelector(`option[value="${userId}"]`);
                            if (option) option.remove();
                        });
                    }
                    </script>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Project</button>
                        <a href="{{ route('projects.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
