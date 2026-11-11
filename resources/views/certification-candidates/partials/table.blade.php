@php
    $flip = fn ($col) => ($sort === $col && $direction === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('certification-candidates.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-data-table app-cards">
            <thead>
                <tr>
                    <th><x-table-sort-link column="name" label="Name" :sort="$sort" :direction="$direction" :url="$url('name')" target="#candidates-table" /></th>
                    <th>Certificates</th>
                    <th>Supervising coach</th>
                    <th>Agreements</th>
                    <th class="text-nowrap"><x-table-sort-link column="start_date" label="Start date" :sort="$sort" :direction="$direction" :url="$url('start_date')" target="#candidates-table" /></th>
                    <th><x-table-sort-link column="active" label="Status" :sort="$sort" :direction="$direction" :url="$url('active')" target="#candidates-table" /></th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($candidates as $candidate)
                @php $row = $rows[$candidate->id]; @endphp
                <tr>
                    <td class="app-card-lead app-col-name">
                        <a href="{{ route('certification-candidates.show', $candidate) }}" class="app-link app-link--strong app-row-link">
                            <span class="app-row-link-label">{{ $candidate->name }}</span>
                        </a>
                        @if($candidate->email)<div class="text-muted small text-truncate">{{ $candidate->email }}</div>@endif
                    </td>
                    <td data-label="Certificates">
                        @forelse($row['enrollments'] as $enrollment)
                            @php $badge = $enrollment->lifecycleBadge(); @endphp
                            <div class="d-flex align-items-center gap-2 small">
                                <span class="text-truncate" style="max-width: 14rem" title="{{ $enrollment->certificate->name }}">{{ $enrollment->certificate->name }}</span>
                                <span class="{{ $badge['class'] }}" title="{{ $badge['label'] }}">{{ \Illuminate\Support\Str::before($badge['label'], ' ·') }}</span>
                            </div>
                        @empty
                            @if($row['hiddenCertificates'] === 0)<span class="text-muted">—</span>@endif
                        @endforelse
                        @include('certification-candidates.partials.hidden-count', ['count' => $row['hiddenCertificates'], 'kind' => 'certificates'])
                    </td>
                    <td data-label="Supervising coach">{{ $row['enrollments']->pluck('supervisingCoach.name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                    <td data-label="Agreements">
                        <x-table-badge-list kind="agreement" :items="$row['agreements']" route-name="agreements.show" :hidden-count="$row['hiddenAgreements']" />
                    </td>
                    <td data-label="Start date" class="text-muted small">{{ $candidate->start_date?->format('M d, Y') ?? '—' }}</td>
                    <td class="app-card-status"><x-status-badge :active="$candidate->active" /></td>
                    <td class="app-col-actions">
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Candidate actions for {{ $candidate->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="{{ route('certification-candidates.show', $candidate) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View candidate</a>
                                @can('update', $candidate)
                                <a href="{{ route('certification-candidates.edit', $candidate) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit candidate</a>
                                @endcan
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">No candidates match these filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$candidates" target="#candidates-table" />
    </div>
</div>
