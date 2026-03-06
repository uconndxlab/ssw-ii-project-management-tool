@extends('layouts.app')

@section('title', 'Activities')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Activities</h1>
            <a href="{{ route('activities.create') }}" class="btn btn-primary">Log Activity</a>
        </div>
    </div>
</div>

<div class="row mb-3">
  <div class="col-12">
    <form method="GET" action="{{ route('activities.index') }}" class="card">
      <div class="card-body">
        <div class="row g-3">

          <div class="col-12 col-md-3">
            <label class="form-label">Organization</label>
            <select name="organization_id" class="form-select">
              <option value="">All</option>
              @foreach($organizations as $org)
                <option value="{{ $org->id }}" @selected(($filters['organization_id'] ?? null) == $org->id)>
                  {{ $org->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label">Agreement</label>
            <select name="agreement_id" class="form-select">
              <option value="">All</option>
              @foreach($agreements as $agreement)
                <option value="{{ $agreement->id }}" @selected(($filters['agreement_id'] ?? null) == $agreement->id)>
                  {{ $agreement->name }}
                  @if($agreement->organization?->name)
                    — {{ $agreement->organization->name }}
                  @endif
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-2">
            <label class="form-label">State</label>
            <select name="state_id" class="form-select">
              <option value="">All</option>
              @foreach($states as $state)
                <option value="{{ $state->id }}" @selected(($filters['state_id'] ?? null) == $state->id)>
                  {{ $state->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-2">
            <label class="form-label">Activity Type</label>
            <select name="activity_type_id" class="form-select">
              <option value="">All</option>
              @foreach($activityTypes as $type)
                <option value="{{ $type->id }}" @selected(($filters['activity_type_id'] ?? null) == $type->id)>
                  {{ $type->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
            <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
          </div>

        </div>
      </div>
    </form>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agreement</th>
                                <th>Activity Type</th>
                                <th>Total Hours</th>
                                <th>Logged By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activities as $activity)
                            <tr>
                                <td>{{ $activity->engagement_date->format('M d, Y') }}</td>
                                <td>{{ $activity->agreement->name }}</td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $activity->activityType->name }}
                                    </span>
                                </td>
                                <td>{{ number_format($activity->total_hours, 2) }}</td>
                                <td>{{ $activity->user->name }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('activities.show', $activity) }}" class="btn btn-outline-secondary">View</a>
                                        @if(auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                                        <a href="{{ route('activities.edit', $activity) }}" class="btn btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('activities.destroy', $activity) }}" class="d-inline" 
                                              hx-confirm="Are you sure you want to delete this activity?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    @if(auth()->user()->isAdmin())
                                        No activities logged yet
                                    @else
                                        No activities found for your assigned agreements
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $activities->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
