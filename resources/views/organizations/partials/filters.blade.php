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
        <div class="col-md-3">
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
        @if(request()->hasAny(['search', 'state_id']))
        <div class="col-auto">
            <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @endif
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>
