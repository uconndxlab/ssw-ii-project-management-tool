<form
    id="state-filters"
    hx-get="{{ route('states.index') }}"
    hx-target="#states-table"
    hx-swap="innerHTML"
    hx-push-url="true"
>
    <div class="row g-3">
        <div class="col-md-10">
            <label for="search" class="form-label">Search</label>
            <input
                type="text"
                class="form-control"
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search states"
                hx-get="{{ route('states.index') }}"
                hx-trigger="keyup changed delay:400ms, search"
                hx-target="#states-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#state-filters"
            >
        </div>

        <div class="col-md-2">
            <label class="form-label d-block">&nbsp;</label>
            <a href="{{ route('states.index') }}" class="btn btn-outline-secondary w-100">
                Reset
            </a>
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>