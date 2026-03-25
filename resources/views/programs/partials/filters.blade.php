<form
    id="program-filters"
    hx-get="{{ route('programs.index') }}"
    hx-target="#programs-table"
    hx-swap="innerHTML"
    hx-push-url="true"
>
    <div class="row g-3">
        <div class="col-md-5">
            <label for="search" class="form-label">Search</label>
            <input
                type="text"
                class="form-control"
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search programs"
                hx-get="{{ route('programs.index') }}"
                hx-trigger="keyup changed delay:400ms, search"
                hx-target="#programs-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#program-filters"
            >
        </div>

        <div class="col-md-4">
            <label for="status" class="form-label">Status</label>
            <select
                class="form-select"
                id="status"
                name="status"
                hx-get="{{ route('programs.index') }}"
                hx-trigger="change"
                hx-target="#programs-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#program-filters"
            >
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label d-block">&nbsp;</label>
            <a href="{{ route('programs.index') }}" class="btn btn-outline-secondary w-100">
                Reset
            </a>
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>