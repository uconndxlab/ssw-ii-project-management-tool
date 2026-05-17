@extends('layouts.app')

@section('title', 'Agreement Logging Fields')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Agreement Logging Fields</h1>
        <p class="text-muted small mb-0">{{ $loggingFields->total() }} total</p>
    </div>
    <a href="{{ route('agreement-logging-fields.create') }}" class="btn btn-primary">+ Create Field</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th class="text-end" style="width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loggingFields as $field)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $field->name }}</div>
                            @if($field->help_text)
                                <div class="small text-muted">{{ $field->help_text }}</div>
                            @endif
                        </td>
                        <td><span class="badge bg-secondary">{{ ucfirst($field->field_type) }}</span></td>
                        <td>
                            @if($field->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $field->sort_order ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('agreement-logging-fields.show', $field) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('agreement-logging-fields.edit', $field) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No agreement logging fields yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($loggingFields->hasPages())
    <div class="mt-3">
        {{ $loggingFields->links() }}
    </div>
@endif
@endsection
