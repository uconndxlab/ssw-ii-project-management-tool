@if($deliverableGroups->isEmpty())
    <p class="text-muted small mb-0">No deliverables assigned to this user on this agreement.</p>
@else
    @foreach($deliverableGroups as $familyGroup)
        <div class="mb-4 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
            <h6 class="fw-semibold mb-2">{{ $familyGroup['contact_family_label'] }}</h6>

            @foreach($familyGroup['activity_groups'] as $activityGroup)
                <div class="mb-3 ps-2 border-start border-3 border-light">
                    <div class="small fw-semibold text-muted mb-2">{{ $activityGroup['activity_type_label'] }}</div>

                    @foreach($activityGroup['program_groups'] as $programGroup)
                        <div class="mb-3 ps-2">
                            <div class="small text-muted mb-2">
                                <i class="bi bi-funnel me-1"></i>{{ $programGroup['program_label'] }}
                            </div>

                            @foreach($programGroup['deliverables'] as $progress)
                                @php
                                    $deliverable = $progress['deliverable'];
                                    $focus = $progress['user_focus'];
                                    $unitLabel = $progress['unit_label'];
                                    $unitLower = strtolower($unitLabel);
                                @endphp

                                <div class="border rounded p-3 mb-2 bg-body">
                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                        <div>
                                            <div class="fw-semibold">{{ $progress['metric_summary'] }}</div>
                                            @if($deliverable->suggested_due_date)
                                                <div class="text-muted small">Suggested due {{ $deliverable->suggested_due_date->format('M d, Y') }}</div>
                                            @endif
                                        </div>
                                        <div class="text-end small text-nowrap">
                                            @if($focus['shared'] && $progress['is_joint'])
                                                <span class="text-muted">Your contribution</span>
                                                <div><strong>{{ number_format($focus['completed'], 1) }}</strong> {{ $unitLower }}</div>
                                            @elseif($focus['shared'])
                                                <strong>{{ number_format($focus['completed'], 1) }}</strong>
                                                @if(($focus['target'] ?? 0) > 0)
                                                    <span class="text-muted">/ {{ number_format($focus['target'], 1) }}</span>
                                                @endif
                                                <div class="text-muted">{{ $unitLabel }}</div>
                                            @else
                                                <strong>{{ number_format($focus['completed'], 1) }}</strong>
                                                @if(($focus['target'] ?? 0) > 0)
                                                    <span class="text-muted">/ {{ number_format($focus['target'], 1) }}</span>
                                                @endif
                                                <div class="text-muted">{{ $unitLower }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    @if(!$focus['shared'] || ($focus['percent'] ?? null) !== null)
                                        @php $barPercent = (float) ($focus['percent'] ?? 0); @endphp
                                        <div class="progress" style="height:6px;">
                                            <div class="progress-bar {{ $barPercent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $barPercent }}%"></div>
                                        </div>
                                    @elseif($progress['is_joint'] && ($progress['target'] ?? 0) > 0)
                                        <div class="text-muted small mt-1">
                                            Agreement total: {{ number_format($progress['completed_value'], 1) }} / {{ number_format($progress['target'], 1) }} {{ $unitLower }}
                                        </div>
                                    @endif

                                    @if($deliverable->notes)
                                        <div class="text-muted fst-italic small mt-2 mb-0">{{ $deliverable->notes }}</div>
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
