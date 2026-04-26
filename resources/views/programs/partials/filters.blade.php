<form id="program-filters"
      hx-get="{{ route('programs.index') }}"
      hx-target="#programs-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search programs…" value="{{ request('search') }}"
                   hx-get="{{ route('programs.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#programs-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#program-filters">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route('programs.index') }}" hx-trigger="change"
                    hx-target="#programs-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#program-filters">
                <option value="">All Statuses</option>
                <option value="active"   @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        @if(request()->hasAny(['search', 'status']))
        <div class="col-auto">
            <a href="{{ route('programs.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @endif
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>
