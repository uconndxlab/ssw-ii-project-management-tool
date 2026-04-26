<form id="agreement-filters"
      hx-get="{{ route('agreements.index') }}"
      hx-target="#agreements-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search agreements…" value="{{ request('search') }}"
                   hx-get="{{ route('agreements.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#agreements-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#agreement-filters">
        </div>
        <div class="col-md-3">
            <select name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                    hx-target="#agreement-filters-container" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#agreement-filters"
                    hx-vals='{"partial":"filters","organization_id":""}'
                    hx-on::after-request="htmx.ajax('GET', '{{ route('agreements.index') }}?' + new URLSearchParams(Array.from(new FormData(document.getElementById('agreement-filters')).entries()).filter(([k]) => k !== 'organization_id')).toString(), {target:'#agreements-table', swap:'innerHTML'});">
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="organization_id" class="form-select form-select-sm"
                    hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                    hx-target="#agreements-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#agreement-filters">
                <option value="">All Organizations</option>
                @foreach($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected((string) request('organization_id') === (string) $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @if(request()->hasAny(['search', 'state_id', 'organization_id']))
        <div class="col-auto">
            <a href="{{ route('agreements.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @endif
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>
