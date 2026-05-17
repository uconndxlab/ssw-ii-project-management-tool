<form
    id="activity-filters"
    hx-get="{{ route('activities.index') }}"
    hx-target="#activities-table"
    hx-swap="innerHTML"
    hx-push-url="true"
>
    <div class="row g-3">
        <div class="col-md-3">
            <label for="search" class="form-label">Search</label>
            <input
                type="text"
                class="form-control"
                id="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search activities"
                hx-get="{{ route('activities.index') }}"
                hx-trigger="keyup changed delay:400ms, search"
                hx-target="#activities-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-filters"
            >
        </div>

        <div class="col-md-2">
            <label for="state_id" class="form-label">State</label>
            <select
                class="form-select"
                id="state_id"
                name="state_id"
                hx-get="{{ route('activities.index') }}"
                hx-trigger="change"
                hx-target="#activity-filters-container"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-filters"
                hx-vals='{"partial":"filters","organization_id":"","agreement_id":""}'
                hx-on::after-request="
                    const params = new URLSearchParams(
                        Array.from(new FormData(document.getElementById('activity-filters')).entries())
                            .filter(([k]) => !['organization_id', 'agreement_id'].includes(k))
                    );
                    htmx.ajax('GET', '{{ route('activities.index') }}?' + params.toString(), {
                        target: '#activities-table',
                        swap: 'innerHTML'
                    });
                "
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

        <div class="col-md-3">
            <label for="organization_id" class="form-label">Organization</label>
            <select
                class="form-select"
                id="organization_id"
                name="organization_id"
                hx-get="{{ route('activities.index') }}"
                hx-trigger="change"
                hx-target="#activity-filters-container"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-filters"
                hx-vals='{"partial":"filters","agreement_id":""}'
                hx-on::after-request="
                    const params = new URLSearchParams(
                        Array.from(new FormData(document.getElementById('activity-filters')).entries())
                            .filter(([k]) => k !== 'agreement_id')
                    );
                    htmx.ajax('GET', '{{ route('activities.index') }}?' + params.toString(), {
                        target: '#activities-table',
                        swap: 'innerHTML'
                    });
                "
            >
                <option value="">All Organizations</option>
                @foreach($organizations as $organization)
                    <option
                        value="{{ $organization->id }}"
                        @selected((string) request('organization_id') === (string) $organization->id)
                    >
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label for="agreement_id" class="form-label">Agreement</label>
            <select
                class="form-select"
                id="agreement_id"
                name="agreement_id"
                hx-get="{{ route('activities.index') }}"
                hx-trigger="change"
                hx-target="#activities-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-filters"
            >
                <option value="">All Agreements</option>
                @foreach($agreements as $agreement)
                    <option
                        value="{{ $agreement->id }}"
                        @selected((string) request('agreement_id') === (string) $agreement->id)
                    >
                        {{ $agreement->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label for="activity_type_id" class="form-label">Activity Type</label>
            <select
                class="form-select"
                id="activity_type_id"
                name="activity_type_id"
                hx-get="{{ route('activities.index') }}"
                hx-trigger="change"
                hx-target="#activities-table"
                hx-swap="innerHTML"
                hx-push-url="true"
                hx-include="#activity-filters"
            >
                <option value="">All Types</option>
                @foreach($activityTypes as $type)
                    <option
                        value="{{ $type->id }}"
                        @selected((string) request('activity_type_id') === (string) $type->id)
                    >
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-12">
            <div class="d-flex justify-content-end">
                <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </div>
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'date' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'desc' }}">
</form>