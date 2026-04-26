<form id="team-filters"
      hx-get="{{ route('teams.index') }}"
      hx-target="#teams-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search teams…" value="{{ request('search') }}"
                   hx-get="{{ route('teams.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#teams-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#team-filters">
        </div>
        <div class="col-md-3">
            <select name="active" class="form-select form-select-sm"
                    hx-get="{{ route('teams.index') }}" hx-trigger="change"
                    hx-target="#teams-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#team-filters">
                <option value="">All Teams</option>
                <option value="1" @selected(request('active') === '1')>Active Only</option>
                <option value="0" @selected(request('active') === '0')>Inactive Only</option>
            </select>
        </div>
        @if(request()->hasAny(['search', 'active']))
        <div class="col-auto">
            <a href="{{ route('teams.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @endif
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>
