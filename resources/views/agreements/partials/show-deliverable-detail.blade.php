@php
    $deliverable = $progress['deliverable'];
    $unitLabel = $progress['unit_label'];
    $renderContributorLabel = $renderContributorLabel ?? function (array $row, bool $includeTeam = true): string {
        $label = $row['user']->name;
        if ($includeTeam && !empty($row['team_name'])) {
            $label .= ' from ' . $row['team_name'];
        }

        return $label;
    };
@endphp

@if(!empty($progress['burn_up']['buckets']))
    <div class="mb-3">
        <div class="text-muted small fw-semibold mb-1">Progress over time</div>
        <div class="deliverable-burn-up-chart" style="height: 180px;">
            <canvas data-burn-up-chart data-burn-up='@json($progress['burn_up'])'></canvas>
        </div>
    </div>
@endif

@if($progress['is_joint'] && $progress['live_assignment_groups']->isNotEmpty())
    <div class="mb-2">
        @foreach($progress['live_assignment_groups'] as $group)
            @php
                $teamCompleted = $group['users']->sum(fn ($row) => (float) ($row['completed_value'] ?? 0));
                $teamRecommended = $group['team_recommended_target'] ?? null;
            @endphp
            <div class="mb-2">
                @if($group['team'])
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <a href="{{ route('teams.show', $group['team']) }}" class="badge bg-secondary-subtle text-secondary-emphasis border text-decoration-underline">{{ $group['team']->name }}</a>
                        @if($teamRecommended)
                            <span class="text-muted small text-nowrap">
                                {{ number_format($teamCompleted, 1) }} / {{ number_format($teamRecommended, 1) }} {{ strtolower($unitLabel) }}
                            </span>
                        @endif
                    </div>
                @else
                    <span class="text-muted small fw-semibold">Additional users</span>
                @endif
                <div class="{{ $group['team'] ? 'ps-3 mt-1' : 'mt-1' }}">
                    @foreach($group['users'] as $row)
                        <div class="d-flex justify-content-between small py-1">
                            <span>
                                <x-user-link :user="$row['user']" :label="$renderContributorLabel($row)" />
                            </span>
                            <span class="text-muted text-nowrap">
                                {{ number_format($row['completed_value'], 1) }}
                                @if(!empty($row['recommended_target']))
                                    / {{ number_format($row['recommended_target'], 1) }}
                                @endif
                                {{ strtolower($unitLabel) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <div class="border rounded bg-body-tertiary px-3 py-2 small text-muted mb-3 d-flex gap-2">
        <i class="bi bi-info-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
        <span>Per-user quantities are suggested shares, not requirements. Progress is measured against the shared deliverable target.</span>
    </div>
@elseif($progress['is_contact'] && $progress['tagged_assignment_groups']->isNotEmpty())
    <div class="mb-2">
        @foreach($progress['tagged_assignment_groups'] as $group)
            @php
                $teamCompleted = $group['users']->sum(fn ($row) => (float) ($row['completed_value'] ?? 0));
                $teamRecommended = $group['team_recommended_target'] ?? null;
            @endphp
            <div class="mb-2">
                @if($group['team'])
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <a href="{{ route('teams.show', $group['team']) }}" class="badge bg-secondary-subtle text-secondary-emphasis border text-decoration-underline">{{ $group['team']->name }}</a>
                        @if($teamRecommended)
                            <span class="text-muted small text-nowrap">
                                {{ number_format($teamCompleted, 1) }} / {{ number_format($teamRecommended, 1) }} {{ strtolower($unitLabel) }}
                            </span>
                        @endif
                    </div>
                @else
                    <span class="text-muted small fw-semibold">Tagged users</span>
                @endif
                <div class="{{ $group['team'] ? 'ps-3 mt-1' : 'mt-1' }}">
                    @foreach($group['users'] as $row)
                        <div class="d-flex justify-content-between small py-1">
                            <span><x-user-link :user="$row['user']" :label="$renderContributorLabel($row)" /></span>
                            <span class="text-muted text-nowrap">
                                @if(!empty($row['recommended_target']))
                                    {{ number_format($row['completed_value'], 1) }} / {{ number_format($row['recommended_target'], 1) }} {{ strtolower($unitLabel) }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <div class="border rounded bg-body-tertiary px-3 py-2 small text-muted mb-3 d-flex gap-2">
        <i class="bi bi-info-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
        <span>Per-user quantities are suggested shares, not requirements. Contributions are counted at the contact level and are not solely derived from listed users.</span>
    </div>
@elseif($progress['is_individual'])
    <div class="mt-1">
        @forelse($progress['individual_progress'] as $individual)
            @php
                $userPercent = $individual['percent'];
                $userCompleted = $individual['completed_value'];
                $userTarget = $individual['target'];
                $userHasTarget = $individual['has_target'];
            @endphp
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-start gap-2 small mb-1">
                    <span>
                        <x-user-link :user="$individual['user']" :label="$renderContributorLabel($individual, false)" class="fw-semibold" />
                    </span>
                    <span class="text-end text-nowrap">
                        <span class="text-muted">
                            {{ number_format($userCompleted, 1) }}@if($userHasTarget) / {{ number_format($userTarget, 1) }}@endif {{ strtolower($unitLabel) }}
                        </span>
                        <x-deliverable-status :status="$individual['status'] ?? null" class="mt-1" />
                    </span>
                </div>
                @if($userHasTarget)
                    <div class="progress" style="height:5px;">
                        <div class="progress-bar {{ $userPercent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ min(100, $userPercent) }}%"></div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-muted small">No users currently assigned.</div>
        @endforelse
    </div>
@elseif($progress['shows_contributor_breakdown'])
    <div class="text-muted small mt-2">No user-attributed contributions recorded yet.</div>
@else
    <div class="text-muted small mt-2">
        Progress counts contact-level {{ strtolower($unitLabel) }} from matching logged activity.
    </div>
@endif

@if($progress['is_individual'] && $progress['past_individual_progress']->isNotEmpty())
    <details class="mt-3 pt-2 border-top">
        <summary class="text-muted small fw-semibold mb-2">Past contributions</summary>
        @foreach($progress['past_individual_progress'] as $individual)
            <div class="d-flex justify-content-between small py-1 text-muted">
                <span>{{ $renderContributorLabel($individual, false) }}</span>
                <span>{{ number_format($individual['completed_value'], 1) }} {{ strtolower($unitLabel) }}</span>
            </div>
        @endforeach
    </details>
@elseif($progress['is_joint'] && $progress['past_contributions']->isNotEmpty())
    <details class="mt-3 pt-2 border-top">
        <summary class="text-muted small fw-semibold mb-2">Past contributions</summary>
        @foreach($progress['past_contributions'] as $summary)
            <div class="d-flex justify-content-between small py-1 text-muted">
                <span>{{ $renderContributorLabel($summary) }}</span>
                <span>{{ number_format($summary['completed_value'], 1) }} {{ strtolower($unitLabel) }}</span>
            </div>
        @endforeach
    </details>
@endif

@if($deliverable->notes)
    <div class="text-muted fst-italic small mt-2">{{ $deliverable->notes }}</div>
@endif
