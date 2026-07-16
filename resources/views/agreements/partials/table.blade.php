@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('agreements.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 220px;">
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#agreements-table" hx-push-url="true">
                            Agreement{!! $icon('name') !!}
                        </a>
                    </th>
                    <th style="min-width: 180px;">Scope</th>
                    <th style="min-width: 160px;">Location</th>
                    <th style="min-width: 150px;">
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('start_date') }}"
                           hx-get="{{ $url('start_date') }}" hx-target="#agreements-table" hx-push-url="true">
                            Timeline{!! $icon('start_date') !!}
                        </a>
                    </th>
                    <th style="min-width: 180px;">Staff &amp; Deliverables</th>
                    <th class="text-end text-nowrap" style="width:{{ auth()->user()->isAdmin() ? '130px' : '52px' }};">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agreements as $agreement)
                @php
                    $teamMemberCount = $agreement->teams
                        ->flatMap(fn ($team) => $team->users)
                        ->pluck('id')
                        ->merge($agreement->users->pluck('id'))
                        ->unique()
                        ->count();
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('agreements.show', $agreement) }}" class="fw-semibold text-decoration-none text-dark d-block">
                            {{ $agreement->name }}
                        </a>
                        @if($agreement->abstract)
                            <div class="text-muted small text-truncate" style="max-width: 320px;" title="{{ $agreement->abstract }}">
                                {{ $agreement->abstract }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1 mb-1">
                            @forelse($agreement->projects->sortBy('name') as $project)
                                <span class="badge bg-primary-subtle text-primary-emphasis border">{{ $project->name }}</span>
                            @empty
                                <span class="text-muted small">No projects</span>
                            @endforelse
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($agreement->programs->sortBy('name') as $program)
                                <span class="badge bg-warning-subtle text-warning-emphasis border">{{ $program->name }}</span>
                            @empty
                                <span class="text-muted small">No programs</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1 mb-1">
                            @forelse($agreement->organizations as $org)
                                <span class="badge bg-secondary text-wrap text-break text-start" style="white-space: normal; max-width: 100%;">{{ $org->name }}</span>
                            @empty
                                <span class="text-muted small">No orgs</span>
                            @endforelse
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($agreement->states as $state)
                                <span class="badge bg-info text-dark">{{ $state->name }}</span>
                            @empty
                                <span class="text-muted small">No states</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="small">
                        <div>{{ $agreement->start_date?->format('M d, Y') ?? '—' }} → {{ $agreement->end_date?->format('M d, Y') ?? '—' }}</div>
                        @if($agreement->extension_start_date || $agreement->extension_end_date)
                            <div class="text-muted mt-1">
                                Ext. {{ $agreement->extension_start_date?->format('M d, Y') ?? '—' }}
                                → {{ $agreement->extension_end_date?->format('M d, Y') ?? '—' }}
                            </div>
                        @endif
                        @if($agreement->time_tracking_mode)
                            <div class="text-muted mt-1">{{ $agreement->time_tracking_mode->label() }}</div>
                        @endif
                    </td>
                    <td class="small">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @forelse($agreement->teams->sortBy('name') as $team)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ $team->name }}</span>
                            @empty
                                <span class="text-muted">No teams</span>
                            @endforelse
                        </div>
                        <div class="text-muted">
                            {{ $teamMemberCount }} staff
                            · {{ $agreement->active_deliverables_count ?? 0 }} deliverable{{ ($agreement->active_deliverables_count ?? 0) === 1 ? '' : 's' }}
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'agreement-actions-' . $agreement->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Agreement actions for {{ $agreement->name }}">
                            <a href="{{ route('agreements.show', $agreement) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View agreement"
                               aria-label="View agreement">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('agreements.edit', $agreement) }}"
                                   class="btn btn-outline-secondary"
                                   data-bs-toggle="tooltip"
                                   data-bs-title="Edit agreement"
                                   aria-label="Edit agreement">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="submit"
                                        form="{{ $actionKey }}-duplicate"
                                        class="btn btn-outline-secondary"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Duplicate agreement"
                                        aria-label="Duplicate agreement"
                                        onclick="return confirm('Duplicate {{ addslashes($agreement->name) }}? Activities and logged progress will not be copied.')">
                                    <i class="bi bi-files"></i>
                                </button>
                                <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        class="btn btn-outline-danger"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Delete agreement"
                                        aria-label="Delete agreement"
                                        onclick="return confirm('Delete {{ addslashes($agreement->name) }}?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                        @if(auth()->user()->isAdmin())
                            <form id="{{ $actionKey }}-duplicate" method="POST" action="{{ route('agreements.duplicate', $agreement) }}" class="d-none">
                                @csrf
                            </form>
                            <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('agreements.destroy', $agreement) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <p class="text-muted mb-2">
                            @if(auth()->user()->isAdmin()) No agreements found.
                            @else You are not assigned to any agreements.
                            @endif
                        </p>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-primary">Create Agreement</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$agreements" target="#agreements-table" />
    </div>
</div>

@once
    <script>
    (function () {
        function initAgreementTableTooltips(scope) {
            if (!window.bootstrap || !bootstrap.Tooltip) {
                return;
            }

            (scope || document).querySelectorAll('#agreements-table [data-bs-toggle="tooltip"]').forEach(function (element) {
                bootstrap.Tooltip.getOrCreateInstance(element);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initAgreementTableTooltips();
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'agreements-table') {
                initAgreementTableTooltips(event.target);
            }
        });
    })();
    </script>
@endonce
