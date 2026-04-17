<form
    id="organization-filters"
    hx-get="{{ route('organizations.index') }}"
    hx-target="#organizations-table"
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
                placeholder="Search organizations or states"
                hx-get="{{ route('organizations.index') }}"
                hx-trigger="keyup changed delay:400ms, search"
                hx-target="#organizations-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#organization-filters"
            >
        </div>

        <div class="col-md-5">
            <label for="state_id" class="form-label">State</label>
                <select
                    class="form-select"
                    id="state_id"
                    name="state_id"
                    hx-get="{{ route('organizations.index') }}"
                    hx-trigger="change"
                    hx-target="#organizations-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#organization-filters"
                >
                    <option value="">All States</option>
                    @foreach($states as $state)
                        <option
                            value="{{ $state->id }}"
                            @selected((string) request('state_id') === (string) $state->id)
                        >
                            {{ $state->name }}
                        </option>
                    @endforeach
                </select>
        </div>

        <div class="col-md-2">
            <label class="form-label d-block">&nbsp;</label>
            <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary w-100">
                Reset
            </a>
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>