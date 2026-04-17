<form
    id="activity-type-filters"
    hx-get="{{ route('activity-types.index') }}"
    hx-target="#activity-types-table"
    hx-swap="innerHTML"
    hx-push-url="true"
>
    <div class="row g-3">
        <div class="col-md-4">
            <label for="search" class="form-label">Search</label>
            <input
                type="text"
                class="form-control"
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search activity types or contact families"
                hx-get="{{ route('activity-types.index') }}"
                hx-trigger="keyup changed delay:400ms, search"
                hx-target="#activity-types-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-type-filters"
            >
        </div>

        <div class="col-md-3">
            <label for="contact_family_id" class="form-label">Contact Family</label>
            <select
                class="form-select"
                id="contact_family_id"
                name="contact_family_id"
                hx-get="{{ route('activity-types.index') }}"
                hx-trigger="change"
                hx-target="#activity-types-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-type-filters"
            >
                <option value="">All Contact Families</option>
                @foreach($contactFamilies as $contactFamily)
                    <option
                        value="{{ $contactFamily->id }}"
                        @selected((string) request('contact_family_id') === (string) $contactFamily->id)
                    >
                        {{ $contactFamily->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label for="active" class="form-label">Status</label>
            <select
                class="form-select"
                id="active"
                name="active"
                hx-get="{{ route('activity-types.index') }}"
                hx-trigger="change"
                hx-target="#activity-types-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-type-filters"
            >
                <option value="">All Statuses</option>
                <option value="1" @selected(request('active') === '1')>Active</option>
                <option value="0" @selected(request('active') === '0')>Inactive</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label d-block">&nbsp;</label>
            <a href="{{ route('activity-types.index') }}" class="btn btn-outline-secondary w-100">
                Reset
            </a>
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>