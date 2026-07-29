@include('activities.partials.filters', compact(
    'states',
    'organizations',
    'agreements',
    'activityTypes',
    'sort',
    'direction'
))

<div id="activities-table" hx-swap-oob="innerHTML">
    @include('activities.partials.table', compact('activities', 'sort', 'direction'))
</div>
