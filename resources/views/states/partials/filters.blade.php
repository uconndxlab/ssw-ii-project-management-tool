<form id="state-filters"
      hx-get="{{ route('states.index') }}"
      hx-target="#states-table"
      hx-swap="innerHTML"
      hx-push-url="true">

    <div class="row g-2 align-items-center">
        <div class="col">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search states…" value="{{ request('search') }}"
                   hx-get="{{ route('states.index') }}"
                   hx-trigger="keyup changed delay:400ms"
                   hx-target="#states-table"
                   hx-swap="innerHTML"
                   hx-push-url="true"
                   hx-include="#state-filters">
        </div>
        <div class="col-auto">
            @if(request()->hasAny(['search']))
                <a href="{{ route('states.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>
