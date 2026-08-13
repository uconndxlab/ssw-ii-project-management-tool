@extends('layouts.app')

@section('title', $loggingField->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <x-page-header
            context="show"
            :title="$loggingField->name"
            entity-type="Logging Field"
            :active="$loggingField->is_active"
            :action-url="route('logging-fields.edit', $loggingField)"
        >
            <x-slot:badges>
                @if($loggingField->is_full_width)
                    <span class="badge bg-dark">Full width</span>
                @endif
            </x-slot:badges>
        </x-page-header>
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
                            <td class="text-muted">Field Type</td>
                            <td><span class="badge bg-secondary">{{ $loggingField->fieldTypeLabel() }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Available In</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @if($loggingField->available_in_agreements)
                                        <span class="badge bg-primary">Agreements</span>
                                    @endif
                                    @if($loggingField->available_in_contact_families)
                                        <span class="badge bg-info text-dark">Activity Families</span>
                                    @endif
                                    @if($loggingField->available_in_activities)
                                        <span class="badge bg-warning text-dark">Activity Types</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($loggingField->usesOptions() && $loggingField->normalizedOptions())
                        <tr>
                            <td class="text-muted">Options</td>
                            <td>
                                <ul class="mb-0">
                                    @foreach($loggingField->normalizedOptions() as $option)
                                        <li>{{ $option['label'] }}</li>
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
                            <td class="text-muted">Layout</td>
                            <td>{{ $loggingField->is_full_width ? 'Full width' : 'Half width' }}</td>
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
                                        @if($agreement->isLinkable())
                                            <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none">
                                                {{ $agreement->name }}
                                            </a>
                                        @else
                                            <span>{{ $agreement->name }}</span>
                                        @endif
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
                    <h6 class="fw-semibold">Activity Families ({{ $loggingField->contactFamilies->count() }})</h6>
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
                        <p class="text-muted small mb-0">Not used in any activity families yet.</p>
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
