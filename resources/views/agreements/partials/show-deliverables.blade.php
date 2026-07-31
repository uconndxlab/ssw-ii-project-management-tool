@php
    $renderContributorLabel = function (array $row): string {
        $label = $row['user']->name;
        if (!empty($row['team_name'])) {
            $label .= ' from ' . $row['team_name'];
        }

        return $label;
    };
@endphp

@if($deliverableGroups->isEmpty())
    <p class="text-muted small mb-0">No deliverables defined for this agreement.</p>
@else
    @foreach($deliverableGroups as $familyGroup)
        <div class="mb-4 {{ !$loop->last ? 'pb-4 border-bottom' : '' }}">
            <h5 class="fw-semibold mb-3">{{ $familyGroup['contact_family_label'] }}</h5>

            @foreach($familyGroup['activity_groups'] as $activityGroup)
                <div class="mb-3 ps-2 border-start border-3 border-light">
                    <h6 class="fw-semibold text-muted mb-2">{{ $activityGroup['activity_type_label'] }}</h6>

                    @foreach($activityGroup['program_groups'] as $programGroup)
                        <div class="mb-3 ps-3">
                            <div class="small text-muted mb-2">
                                <i class="bi bi-funnel me-1"></i>{{ $programGroup['program_label'] }}
                            </div>

                            @foreach($programGroup['deliverables'] as $progress)
                                @php
                                    $deliverable = $progress['deliverable'];
                                    $target = $progress['target'];
                                    $completedValue = $progress['completed_value'];
                                    $percent = $progress['percent'];
                                    $unitLabel = $progress['unit_label'];
                                @endphp

                                <div class="border rounded p-3 mb-3 bg-body">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <div>
                                            <div class="fw-semibold">{{ $progress['metric_summary'] }}</div>
                                            @if($deliverable->suggested_due_date)
                                                <div class="text-muted small">Suggested due {{ $deliverable->suggested_due_date->format('M d, Y') }}</div>
                                            @endif
                                        </div>
                                        <div class="text-end small text-nowrap">
                                            @if($progress['is_individual'])
                                                <span class="text-muted">{{ $progress['individual_progress']->count() }} assigned</span>
                                                <div class="text-muted">{{ number_format($target, 1) }} {{ strtolower($unitLabel) }} each</div>
                                            @else
                                                <strong>{{ number_format($completedValue, 1) }}</strong>
                                                @if($target > 0)
                                                    <span class="text-muted">/ {{ number_format($target, 1) }}</span>
                                                @endif
                                                <div class="text-muted">{{ $unitLabel }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    @if(!$progress['is_individual'])
                                        <div class="progress mb-3" style="height:6px;">
                                            <div class="progress-bar {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $percent }}%"></div>
                                        </div>
                                    @endif

                                    @if($progress['is_joint'] && $progress['live_assignment_groups']->isNotEmpty())
                                        <div class="mb-2">
                                            @foreach($progress['live_assignment_groups'] as $group)
                                                <div class="mb-2">
                                                    @if($group['team'])
                                                        <a href="{{ route('teams.show', $group['team']) }}" class="badge bg-secondary-subtle text-secondary-emphasis border text-decoration-underline">{{ $group['team']->name }}</a>
                                                    @else
                                                        <span class="text-muted small fw-semibold">Additional users</span>
                                                    @endif
                                                    <div class="{{ $group['team'] ? 'ps-3 mt-1' : 'mt-1' }}">
                                                        @foreach($group['users'] as $row)
                                                            <div class="d-flex justify-content-between small py-1">
                                                                <span>
                                                                    <x-user-link :user="$row['user']" :label="$renderContributorLabel($row)" class="text-decoration-underline" />
                                                                </span>
                                                                <span class="text-muted text-nowrap">{{ number_format($row['completed_value'], 1) }} {{ strtolower($unitLabel) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($progress['is_individual'])
                                        <div class="mt-1">
                                            @forelse($progress['individual_progress'] as $individual)
                                                @php
                                                    $userPercent = $individual['percent'];
                                                    $userCompleted = $individual['completed_value'];
                                                    $userTarget = $individual['target'];
                                                @endphp
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                                                        <span>
                                                            <x-user-link :user="$individual['user']" :label="$renderContributorLabel($individual)" class="text-decoration-underline fw-semibold" />
                                                        </span>
                                                        <span class="text-muted text-nowrap">
                                                            {{ number_format($userCompleted, 1) }}@if($userTarget > 0) / {{ number_format($userTarget, 1) }}@endif {{ strtolower($unitLabel) }}
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height:5px;">
                                                        <div class="progress-bar {{ $userPercent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $userPercent }}%"></div>
                                                    </div>
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
                                        <div class="mt-3 pt-2 border-top">
                                            <div class="text-muted small fw-semibold mb-2">Past contributions</div>
                                            @foreach($progress['past_individual_progress'] as $individual)
                                                @php
                                                    $userPercent = $individual['percent'];
                                                    $userCompleted = $individual['completed_value'];
                                                    $userTarget = $individual['target'];
                                                @endphp
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                                                        <span class="text-muted">{{ $renderContributorLabel($individual) }}</span>
                                                        <span class="text-muted text-nowrap">
                                                            {{ number_format($userCompleted, 1) }}@if($userTarget > 0) / {{ number_format($userTarget, 1) }}@endif {{ strtolower($unitLabel) }}
                                                        </span>
                                                    </div>
                                                    <div class="progress" style="height:5px;">
                                                        <div class="progress-bar bg-secondary" style="width:{{ $userPercent }}%"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif($progress['is_joint'] && $progress['past_contributions']->isNotEmpty())
                                        <div class="mt-3 pt-2 border-top">
                                            <div class="text-muted small fw-semibold mb-2">Past contributions</div>
                                            @foreach($progress['past_contributions'] as $summary)
                                                <div class="d-flex justify-content-between small py-1 text-muted">
                                                    <span>{{ $renderContributorLabel($summary) }}</span>
                                                    <span>{{ number_format($summary['completed_value'], 1) }} {{ strtolower($unitLabel) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($deliverable->notes)
                                        <div class="text-muted fst-italic small mt-2">{{ $deliverable->notes }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach
@endif
