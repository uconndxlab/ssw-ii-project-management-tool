@extends('layouts.app')

@section('title', $loggingField->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>{{ $loggingField->name }}</h1>
                <p class="text-muted mb-0">
                    <code>{{ $loggingField->slug }}</code>
                    @if($loggingField->is_active)
                        <span class="badge bg-success ms-2">Active</span>
                    @else
                        <span class="badge bg-secondary ms-2">Inactive</span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('logging-fields.edit', $loggingField) }}" class="btn btn-outline-primary">Edit</a>
                <a href="{{ route('logging-fields.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Field Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 40%;">Field Name</td>
                            <td class="fw-semibold">{{ $loggingField->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Slug</td>
                            <td><code>{{ $loggingField->slug }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Field Type</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($loggingField->field_type) }}</span></td>
                        </tr>
                        @if($loggingField->field_type === 'select' && $loggingField->options_json)
                        <tr>
                            <td class="text-muted">Options</td>
                            <td>
                                <ul class="mb-0">
                                    @foreach($loggingField->options_json as $option)
                                        <li>{{ $option }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                        @endif
                        @if($loggingField->help_text)
                        <tr>
                            <td class="text-muted">Help Text</td>
                            <td>{{ $loggingField->help_text }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted">Sort Order</td>
                            <td>{{ $loggingField->sort_order ?? 'None (alphabetical)' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                @if($loggingField->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created</td>
                            <td>{{ $loggingField->created_at->format('M d, Y g:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated</td>
                            <td>{{ $loggingField->updated_at->format('M d, Y g:i A') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Usage Statistics</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">This field is currently used in:</p>
                
                <div class="mb-4">
                    <h6 class="fw-semibold">Agreements ({{ $loggingField->agreements->count() }})</h6>
                    @if($loggingField->agreements->isNotEmpty())
                        <ul class="list-group list-group-flush">
                            @foreach($loggingField->agreements as $agreement)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none">
                                            {{ $agreement->number }} - {{ $agreement->name }}
                                        </a>
                                        @if($agreement->pivot->is_required)
                                            <span class="badge bg-warning text-dark ms-2">Required</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted small mb-0">Not used in any agreements yet.</p>
                    @endif
                </div>

                <div>
                    <h6 class="fw-semibold">Contact Families ({{ $loggingField->contactFamilies->count() }})</h6>
                    @if($loggingField->contactFamilies->isNotEmpty())
                        <ul class="list-group list-group-flush">
                            @foreach($loggingField->contactFamilies as $family)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        {{ $family->name }}
                                        @if($family->pivot->is_required)
                                            <span class="badge bg-warning text-dark ms-2">Required</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted small mb-0">Not used in any contact families yet.</p>
                    @endif
                </div>
            </div>
        </div>

        @if($loggingField->agreements->isEmpty() && $loggingField->contactFamilies->isEmpty())
        <div class="card border-danger">
            <div class="card-body">
                <h6 class="text-danger mb-2">Delete This Field?</h6>
                <p class="small text-muted mb-3">This field is not currently in use and can be safely deleted.</p>
                <form method="POST" action="{{ route('logging-fields.destroy', $loggingField) }}" class="d-inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('Are you sure you want to delete {{ addslashes($loggingField->name) }}? This action cannot be undone.')">
                        Delete Field
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
