<form id="logging-field-filters"
      hx-get="{{ route('logging-fields.index') }}"
      hx-target="#logging-fields-table"
      hx-swap="innerHTML"
      hx-push-url="true">

    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search fields…" value="{{ request('search') }}"
                   hx-get="{{ route('logging-fields.index') }}"
                   hx-trigger="keyup changed delay:400ms"
                   hx-target="#logging-fields-table"
                   hx-swap="innerHTML"
                   hx-push-url="true"
                   hx-include="#logging-field-filters">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="field_type" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Field Types</option>
                <option value="number" {{ request('field_type') === 'number' ? 'selected' : '' }}>Number</option>
                <option value="decimal" {{ request('field_type') === 'decimal' ? 'selected' : '' }}>Decimal</option>
                <option value="text" {{ request('field_type') === 'text' ? 'selected' : '' }}>Text</option>
                <option value="textarea" {{ request('field_type') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                <option value="checkbox" {{ request('field_type') === 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                <option value="select" {{ request('field_type') === 'select' ? 'selected' : '' }}>Select</option>
                <option value="document" {{ request('field_type') === 'document' ? 'selected' : '' }}>Document Upload</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="availability" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Availability</option>
                <option value="available_in_agreements" {{ request('availability') === 'available_in_agreements' ? 'selected' : '' }}>Agreements</option>
                <option value="available_in_contact_families" {{ request('availability') === 'available_in_contact_families' ? 'selected' : '' }}>Contact Families</option>
                <option value="available_in_activities" {{ request('availability') === 'available_in_activities' ? 'selected' : '' }}>Activities</option>
            </select>
        </div>
        <div class="col-auto">
            @if(request()->hasAny(['search', 'status', 'field_type', 'availability']))
                <a href="{{ route('logging-fields.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </div>
    </div>

    <input type="hidden" name="sort_by" value="{{ request('sort_by', 'sort_order') }}">
    <input type="hidden" name="sort_dir" value="{{ request('sort_dir', 'asc') }}">
</form>
