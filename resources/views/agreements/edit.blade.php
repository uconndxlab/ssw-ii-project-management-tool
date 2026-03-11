@extends('layouts.app')

@section('title', 'Edit Agreement')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Agreement</h1>
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

                <form method="POST" action="{{ route('agreements.update', $agreement) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Agreement Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $agreement->name) }}" 
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
                                            {{ old('organization_id', $agreement->organization_id) == $organization->id ? 'selected' : '' }}>
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
                                            {{ old('state_id', $agreement->state_id) == $state->id ? 'selected' : '' }}>
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

                    <div class="mb-3">
                        <label for="abstract" class="form-label">Abstract</label>
                        <textarea class="form-control @error('abstract') is-invalid @enderror" 
                                  id="abstract" 
                                  name="abstract" 
                                  rows="4">{{ old('abstract', $agreement->abstract) }}</textarea>
                        @error('abstract')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" 
                                       class="form-control @error('start_date') is-invalid @enderror" 
                                       id="start_date" 
                                       name="start_date" 
                                       value="{{ old('start_date', $agreement->start_date?->format('Y-m-d')) }}">
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
                                       value="{{ old('end_date', $agreement->end_date?->format('Y-m-d')) }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="original_end_date" class="form-label">Original End Date</label>
                                <input type="date" 
                                       class="form-control @error('original_end_date') is-invalid @enderror" 
                                       id="original_end_date" 
                                       name="original_end_date" 
                                       value="{{ old('original_end_date', $agreement->original_end_date?->format('Y-m-d')) }}">
                                @error('original_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">For tracking agreement extensions</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="extended_end_date" class="form-label">Extended End Date</label>
                                <input type="date" 
                                       class="form-control @error('extended_end_date') is-invalid @enderror" 
                                       id="extended_end_date" 
                                       name="extended_end_date" 
                                       value="{{ old('extended_end_date', $agreement->extended_end_date?->format('Y-m-d')) }}">
                                @error('extended_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="certification_candidates" class="form-label">Certification Candidates</label>
                        <textarea class="form-control @error('certification_candidates') is-invalid @enderror" 
                                  id="certification_candidates" 
                                  name="certification_candidates" 
                                  rows="3">{{ old('certification_candidates', $agreement->certification_candidates) }}</textarea>
                        @error('certification_candidates')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">List of certification candidates (placeholder)</small>
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
                                       {{ in_array($user->id, old('user_ids', $agreement->users->pluck('id')->toArray())) ? 'checked' : '' }}>
                                <label class="form-check-label" for="user_{{ $user->id }}">
                                    {{ $user->name }} ({{ ucfirst($user->role) }})
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Or use controls below for live updates</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quick Add User</label>
                        <form hx-post="{{ route('agreements.assign-user', $agreement) }}"
                              hx-target="#user-list"
                              hx-swap="innerHTML"
                              class="mb-2">
                            @csrf
                            <div class="input-group">
                                <select class="form-select" name="user_id" required>
                                    <option value="">Select a user to add...</option>
                                    @foreach($users->whereNotIn('id', $agreement->users->pluck('id')) as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ ucfirst($user->role) }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary">
                                    Add User
                                </button>
                            </div>
                        </form>
                        <div id="user-list" class="list-group">
                            @include('agreements.partials.user-list', ['agreement' => $agreement])
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <h5 class="mb-3">Deliverables</h5>
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Add Deliverable</h6>
                                <form hx-post="{{ route('agreements.add-deliverable', $agreement) }}"
                                      hx-target="#deliverable-list"
                                      hx-swap="innerHTML">
                                    @csrf
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="deliverable_contact_family_id" class="form-label">Contact Family</label>
                                                <select class="form-select" 
                                                        id="deliverable_contact_family_id" 
                                                        name="contact_family_id"
                                                        hx-get="{{ route('activity-types.by-family') }}"
                                                        hx-target="#deliverable_activity_type_id"
                                                        hx-swap="innerHTML"
                                                        hx-include="this">
                                                    <option value="">Select contact family...</option>
                                                    @foreach($contactFamilies as $family)
                                                    <option value="{{ $family->id }}">{{ $family->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="deliverable_activity_type_id" class="form-label">Activity Type</label>
                                                <select class="form-select" 
                                                        id="deliverable_activity_type_id" 
                                                        name="activity_type_id">
                                                    <option value="">Select contact family first...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="deliverable_required_hours" class="form-label">Required Hours</label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="deliverable_required_hours" 
                                                       name="required_hours" 
                                                       min="0" 
                                                       step="0.1">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="deliverable_required_activities" class="form-label">Required Activities</label>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="deliverable_required_activities" 
                                                       name="required_activities" 
                                                       min="0" 
                                                       step="1">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deliverable_notes" class="form-label">Notes</label>
                                        <textarea class="form-control" 
                                                  id="deliverable_notes" 
                                                  name="notes" 
                                                  rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        Add Deliverable
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Activity Type</th>
                                        <th>Contact Family</th>
                                        <th class="text-center">Hours</th>
                                        <th class="text-center">Activities</th>
                                        <th>Notes</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="deliverable-list">
                                    @include('agreements.partials.deliverable-list', ['agreement' => $agreement])
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Agreement</button>
                        <a href="{{ route('agreements.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
