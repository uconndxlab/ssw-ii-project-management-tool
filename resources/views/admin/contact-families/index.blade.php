@extends('layouts.app')

@section('title', 'Contact Families')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Contact Families</h1>
        <p class="text-muted small mb-0">{{ $contactFamilies->count() }} total</p>
    </div>
    <a href="{{ route('contact-families.create') }}" class="btn btn-primary">+ Add Contact Family</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form id="cf-filters"
              hx-get="{{ route('contact-families.index') }}"
              hx-target="#cf-table"
              hx-swap="innerHTML"
              hx-push-url="true">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Search contact families…" value="{{ request('search') }}"
                           hx-get="{{ route('contact-families.index') }}"
                           hx-trigger="keyup changed delay:400ms, search"
                           hx-target="#cf-table" hx-swap="outerHTML"
                           hx-select="#cf-table"
                           hx-push-url="true" hx-include="#cf-filters">
                </div>
                @if(request('search'))
                <div class="col-auto">
                    <a href="{{ route('contact-families.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
                @endif
            </div>
        </form>
    </div>
</div>

<div id="cf-table">
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Activity Types</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th class="text-end" style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contactFamilies as $family)
                    <tr>
                        <td class="fw-semibold">{{ $family->name }}</td>
                        <td><span class="badge bg-secondary">{{ $family->activity_types_count }}</span></td>
                        <td class="text-muted small">{{ $family->sort_order }}</td>
                        <td>
                            @if($family->active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-nowrap">
                                <a href="{{ route('contact-families.edit', $family) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('contact-families.destroy', $family) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete {{ addslashes($family->name) }}?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <p class="text-muted mb-2">No contact families found.</p>
                            <a href="{{ route('contact-families.create') }}" class="btn btn-sm btn-primary">Add Contact Family</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
