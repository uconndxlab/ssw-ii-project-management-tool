<x-record-section title="Deliverables" :count="$overviewDeliverables['total']" link="#deliverables" link-label="View deliverables" link-section="deliverables">
    @if(!$overviewDeliverables['total'])
        <p class="text-muted mb-0">No deliverables set up.</p>
    @else
        @if($overviewDeliverables['note'])
            <p class="text-muted small">{{ $overviewDeliverables['note'] }}</p>
        @else
            @php
                // Setup gaps are configuration problems, not progress: each group gets its own labeled bar and legend.
                $segments = collect(['progress' => false, 'setup' => true])
                    ->map(fn (bool $setup) => [
                        'blocks' => collect($overviewDeliverables['blocks'])->where('setup', $setup),
                        'buckets' => collect($overviewDeliverables['buckets'])->where('setup', $setup),
                    ])
                    ->filter(fn (array $segment) => $segment['buckets']->isNotEmpty());
            @endphp
            <div class="app-deliverable-status">
                @foreach($segments as $key => $segment)
                    <div class="app-deliverable-status-segment" style="flex: {{ $segment['buckets']->sum('count') }} 1 0">
                        <span class="app-deliverable-status-label" id="deliverable-status-{{ $key }}">{{ ucfirst($key) }}</span>
                        <div class="app-deliverable-status-bar" aria-hidden="true">
                            @foreach($segment['blocks'] as $block)
                                <span class="app-status-block--{{ $block['tone'] }}" style="flex: {{ $block['count'] }}"></span>
                            @endforeach
                        </div>
                        <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0" aria-labelledby="deliverable-status-{{ $key }}">
                            @foreach($segment['buckets'] as $bucket)
                                <li><span class="app-status app-status--{{ $bucket['tone'] }}">@if($bucket['key'] === 'no_assignees')<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>@endif{{ $bucket['count'] }} {{ $bucket['label'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
        <h3 class="h6 small mt-4 mb-1">Flagged</h3>
        <div>
            @forelse($overviewDeliverables['attention'] as $item)
                <a class="app-attention-row" href="#deliverables" data-section-goto="deliverables" data-deliverable-target="{{ $item['deliverable_id'] }}">
                    <span class="min-w-0"><span class="app-link">{{ $item['title'] }}</span><span class="d-block text-muted small">{{ $item['meta'] }}</span></span>
                    <span class="app-status app-status--{{ $item['status_tone'] }}">@if($item['bucket'] === 'no_assignees')<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>@endif{{ $item['status_label'] }}</span>
                    <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                </a>
            @empty
                <p class="mb-1">Nothing flagged</p>
                <p class="text-muted small mb-0">No deliverables are off track or missing assignees.</p>
            @endforelse
        </div>
    @endif
</x-record-section>
