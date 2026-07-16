<form id="organization-filters"
      hx-get="{{ route('organizations.index') }}"
      hx-target="#organizations-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search organizations…" value="{{ request('search') }}"
                   hx-get="{{ route('organizations.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#organizations-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#organization-filters">
        </div>
        <div class="col-md-2">
            <select name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        @if(request()->hasAny(['search', 'state_id', 'status']))
        <div class="col-auto">
            <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @endif
    </div>
</form>
