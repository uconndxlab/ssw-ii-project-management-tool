@php
    $s = $sort ?? 'date';
    $d = $direction ?? 'desc';
    $flip = function ($col) use ($s, $d) {
        if ($s === $col) {
            return $d === 'asc' ? 'desc' : 'asc';
        }

        return $col === 'date' ? 'desc' : 'asc';
    };
    $url = fn ($col) => route('activities.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">
                        <x-table-sort-link column="date" label="Date" :sort="$s" :direction="$d" :url="$url('date')" target="#activities-table" />
                    </th>
                    <th style="min-width: 180px;">
                        <x-table-sort-link column="agreement" label="Agreement" :sort="$s" :direction="$d" :url="$url('agreement')" target="#activities-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="activity_type" label="Activity Type" :sort="$s" :direction="$d" :url="$url('activity_type')" target="#activities-table" />
                    </th>
                    <th style="min-width: 120px;">
                        <x-table-sort-link column="logged_by" label="Logged By" :sort="$s" :direction="$d" :url="$url('logged_by')" target="#activities-table" />
                    </th>
                    <th class="text-end fw-normal" style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                <tr>
                    <td class="small text-nowrap">
                        {{ $activity->engagement_date->format('M d, Y') }}
                        @if($activity->cancelled)
                            <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($activity->agreements as $agreement)
                                @if($agreement->isLinkable())
                                    <x-entity-relation-badge kind="agreement" :href="route('agreements.show', $agreement)">
                                        {{ $agreement->name }}
                                    </x-entity-relation-badge>
                                @else
                                    <x-entity-relation-badge kind="agreement">
                                        {{ $agreement->name }}
                                    </x-entity-relation-badge>
                                @endif
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark">{{ $activity->activityType->name }}</span>
                    </td>
                    <td class="small">
                        <x-user-link :user="$activity->user" class="text-decoration-none" />
                    </td>
                    <td class="text-end text-nowrap">
                        @php
                            $actionKey = 'activity-actions-' . $activity->id;
                            $canManage = auth()->user()->isAdmin() || $activity->user_id === auth()->id();
                        @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Activity actions for {{ $activity->engagement_date->format('M d, Y') }}">
                            <a href="{{ route('activities.show', $activity) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View activity"
                               aria-label="View activity">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($canManage)
                                <a href="{{ route('activities.edit', $activity) }}"
                                   class="btn btn-outline-secondary"
                                   data-bs-toggle="tooltip"
                                   data-bs-title="Edit activity"
                                   aria-label="Edit activity">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="submit"
                                        form="{{ $actionKey }}-duplicate"
                                        class="btn btn-outline-secondary"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Duplicate activity"
                                        aria-label="Duplicate activity"
                                        onclick="return confirm('Duplicate this activity? A new activity will be created with the same details.')">
                                    <i class="bi bi-files"></i>
                                </button>
                                <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        class="btn btn-outline-danger"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Delete activity"
                                        aria-label="Delete activity"
                                        onclick="return confirm('Delete this activity?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                        @if($canManage)
                            <form id="{{ $actionKey }}-duplicate" method="POST" action="{{ route('activities.duplicate', $activity) }}" class="d-none">
                                @csrf
                            </form>
                            <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('activities.destroy', $activity) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">
                            @if(auth()->user()->isAdmin())
                                No activities logged yet.
                            @else
                                No activities found for your assigned agreements.
                            @endif
                        </p>
                        <a href="{{ route('activities.create') }}" class="btn btn-sm btn-primary">Log Activity</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$activities" target="#activities-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'activities-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('activity-filters');
                if (form) {
                    if (params.has('sort')) {
                        var sortInput = form.querySelector('[name=sort]');
                        if (sortInput) {
                            sortInput.value = params.get('sort');
                        }
                    }
                    if (params.has('direction')) {
                        var directionInput = form.querySelector('[name=direction]');
                        if (directionInput) {
                            directionInput.value = params.get('direction');
                        }
                    }
                }
            }
        });
    })();
    </script>
@endonce
