<form id="activity-filters"
      data-table-filter-form
      hx-get="{{ route('activities.index') }}"
      hx-target="#activities-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-2">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search activities…" value="{{ request('search') }}"
                   hx-get="{{ route('activities.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#activities-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#activity-filters">
        </div>
        <div class="col-md-2">
            <select name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="#activity-filters-container" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-filters"
                    hx-vals='{"partial":"filters","organization_id":"","agreement_id":""}'>
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="organization_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="#activity-filters-container" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-filters"
                    hx-vals='{"partial":"filters","agreement_id":""}'>
                <option value="">All Organizations</option>
                @foreach($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected((string) request('organization_id') === (string) $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="agreement_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="#activities-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-filters">
                <option value="">All Agreements</option>
                @foreach($agreements as $agreement)
                    <option value="{{ $agreement->id }}" @selected((string) request('agreement_id') === (string) $agreement->id)>
                        {{ $agreement->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="activity_type_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="#activities-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-filters">
                <option value="">All Types</option>
                @foreach($activityTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('activity_type_id') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <x-table-filter-clear
            :href="route('activities.index')"
            :filter-keys="['search', 'state_id', 'organization_id', 'agreement_id', 'activity_type_id']"
        />
    </div>
    <input type="hidden" name="sort" value="{{ $sort ?? 'date' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'desc' }}">
</form>
