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

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-data-table app-cards">
            <thead>
                <tr>
                    <th class="text-nowrap">
                        <x-table-sort-link column="date" label="Date" :sort="$s" :direction="$d" :url="$url('date')" target="#activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="agreement" label="Agreement" :sort="$s" :direction="$d" :url="$url('agreement')" target="#activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="activity_type" label="Activity Type" :sort="$s" :direction="$d" :url="$url('activity_type')" target="#activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="logged_by" label="Logged By" :sort="$s" :direction="$d" :url="$url('logged_by')" target="#activities-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                <tr>
                    <td class="text-nowrap app-card-lead">
                        @if($activity->isLinkable())
                            <a href="{{ route('activities.show', $activity) }}" class="app-link--strong app-row-link">{{ $activity->engagement_date->format('M d, Y') }}</a>
                        @else
                            {{ $activity->engagement_date->format('M d, Y') }}
                        @endif
                        @if($activity->cancelled)
                            <x-status-badge :active="false" inactive-label="Cancelled" tone="danger" class="ms-1" />
                        @endif
                    </td>
                    <td data-label="Agreement">
                        <x-table-badge-list
                            kind="agreement"
                            :items="$activity->agreements->map(fn ($agreement) => [
                                'name' => $agreement->name,
                                'href' => $agreement->isLinkable() ? route('agreements.show', $agreement) : null,
                            ])"
                            href-key="href"
                            container-class="app-chip-list app-chip-list--primary"
                        />
                    </td>
                    <td data-label="Type">
                        <span class="app-tag">{{ $activity->activityType->name }}</span>
                    </td>
                    <td data-label="Logged by">
                        <x-user-link :user="$activity->user" />
                    </td>
                    <td class="app-col-actions">
                        @php
                            $actionKey = 'activity-actions-' . $activity->id;
                            $canUpdate = auth()->user()->can('update', $activity);
                            $canDelete = auth()->user()->can('delete', $activity);
                            $canDuplicate = auth()->user()->can('duplicate', $activity);
                            $canEditCertification = $activity->certification_candidates_count > 0 && auth()->user()->access()->isCertificationCoachOnActivity($activity);
                        @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Activity actions for {{ $activity->engagement_date->format('M d, Y') }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('activities.show', $activity) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View activity</a>
                            <x-activity-action-log-button :activity="$activity" menu />
                            @if($canUpdate)
                                <a href="{{ route('activities.edit', $activity) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit activity</a>
                            @endif
                            @if($canEditCertification)
                                <a href="{{ route('activities.certification.edit', $activity) }}#activity-certification" class="dropdown-item"><i class="bi bi-patch-check me-2" aria-hidden="true"></i>Edit certification</a>
                            @endif
                            @if($canDuplicate)
                                <button type="submit"
                                        form="{{ $actionKey }}-duplicate"
                                        data-confirm
                                        data-confirm-title="Duplicate activity?"
                                        data-confirm-record="{{ $activity->activityType?->name ?? 'Activity' }} · {{ $activity->engagement_date->format('M d, Y') }}"
                                        data-confirm-message="A new activity will be created with the same details."
                                        data-confirm-action="Duplicate activity"
                                        data-confirm-tone="primary" class="dropdown-item"><i class="bi bi-files me-2" aria-hidden="true"></i>Duplicate activity</button>
                            @endif
                            @if($canDelete)
                                <hr class="dropdown-divider">
                            <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        data-confirm
                                        data-confirm-title="Delete activity?"
                                        data-confirm-record="{{ $activity->activityType?->name ?? 'Activity' }} · {{ $activity->engagement_date->format('M d, Y') }}"
                                        data-confirm-message="This action cannot be undone."
                                        data-confirm-action="Delete activity" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete activity</button>
                            @endif
                            </div>
                        </div>
                        @if($canDuplicate)
                            <form id="{{ $actionKey }}-duplicate" method="POST" action="{{ route('activities.duplicate', $activity) }}" class="d-none">
                                @csrf
                            </form>
                        @endif
                        @if($canDelete)
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
                        <p class="text-muted mb-2">No activities found.</p>
                        <a href="{{ route('activities.create') }}" class="btn btn-sm btn-primary">Log Activity</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
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
