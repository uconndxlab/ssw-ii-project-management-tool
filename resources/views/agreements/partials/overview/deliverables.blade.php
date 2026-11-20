<x-record-section title="Deliverables" :count="$overviewDeliverables['total']" link="#deliverables" link-label="View deliverables" link-section="deliverables">
    @if(!$overviewDeliverables['total'])
        <p class="text-muted mb-0">No deliverables set up.</p>
    @else
        @if($overviewDeliverables['note'])
            <p class="text-muted small">{{ $overviewDeliverables['note'] }}</p>
        @else
            <div class="app-deliverable-status-bar" aria-hidden="true">
                @foreach($overviewDeliverables['blocks'] as $block)
                    <span @class(['app-status-block--'.$block['tone'], 'app-status-block--striped' => $block['striped']]) style="flex: {{ $block['count'] }}"></span>
                @endforeach
            </div>
            <ul class="list-unstyled d-flex flex-wrap gap-2 mb-3" aria-label="Deliverable status">
                @foreach($overviewDeliverables['buckets'] as $bucket)
                    <li><span @class(['app-status', 'app-status--'.$bucket['tone'], 'app-status--striped' => $bucket['striped']])>{{ $bucket['count'] }} {{ $bucket['label'] }}</span></li>
                @endforeach
            </ul>
        @endif
        <h3 class="h6 small mt-4 mb-1">Flagged</h3>
        <div>
            @forelse($overviewDeliverables['attention'] as $item)
                <a class="app-attention-row" href="#deliverables" data-section-goto="deliverables" data-deliverable-target="{{ $item['deliverable_id'] }}">
                    <span class="min-w-0"><span class="app-link">{{ $item['title'] }}</span><span class="d-block text-muted small">{{ $item['meta'] }}</span></span>
                    <span @class(['app-status', 'app-status--'.$item['status_tone'], 'app-status--striped' => $item['status_striped']])>{{ $item['status_label'] }}</span>
                    <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
                </a>
            @empty
                <p class="mb-1">Nothing flagged</p>
                <p class="text-muted small mb-0">No deliverables are off track or missing assignees.</p>
            @endforelse
        </div>
    @endif
</x-record-section>
