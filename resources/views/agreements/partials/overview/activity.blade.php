<x-record-section title="Activity" :count="$lifetimeTotals['activities']" :restricted="$restrictedActivityCount" restricted-kind="activities" link="#activity" link-label="View all activity" link-section="activity">
    @if(count($overviewTimeline['buckets']))
        @php
            $timeline = $overviewTimeline;
            $todayLeft = $timeline['today_offset'] !== null ? round($timeline['today_offset'] * 100, 2) : null;
            // Label every bucket up to 12; past that, every nth so labels never collide.
            $labelStep = max(1, (int) ceil(count($timeline['buckets']) / 12));
        @endphp
        <figure class="app-activity-chart">
            <figcaption class="app-activity-chart-caption">Activities logged per {{ $timeline['granularity'] }} in the agreement period</figcaption>
            <div class="app-activity-plot">
                @if($todayLeft !== null)<div class="app-chart-future" style="left: {{ $todayLeft }}%" aria-hidden="true"></div>@endif
                <div class="app-activity-bars" role="group" aria-label="Activities per {{ $timeline['granularity'] }}" data-activity-chart>
                    @foreach($timeline['buckets'] as $bucket)
                        @php $summary = $bucket['label'].' · '.$bucket['count'].' '.Str::plural('activity', $bucket['count']); @endphp
                        <div class="app-activity-bucket">
                            <button type="button" class="app-activity-bar" tabindex="{{ $loop->first ? '0' : '-1' }}" style="--bar-height: {{ $bucket['count'] ? max(4, round($bucket['count'] / $timeline['max'] * 100, 1)) : 0 }}%" aria-label="{{ $summary }}" data-chart-bar></button>
                            <span class="app-chart-tooltip" aria-hidden="true"><b>{{ $bucket['label'] }}</b> · {{ $bucket['count'] }} {{ Str::plural('activity', $bucket['count']) }}</span>
                        </div>
                    @endforeach
                </div>
                @if($todayLeft !== null)<div class="app-chart-today" style="left: {{ $todayLeft }}%" aria-hidden="true"><span>Today</span></div>@endif
            </div>
            <div class="app-activity-axis" aria-hidden="true">
                @foreach($timeline['buckets'] as $bucket)<span>{{ $loop->index % $labelStep === 0 ? $bucket['short_label'] : '' }}</span>@endforeach
            </div>
        </figure>
        <div class="visually-hidden"><table><caption>Activities logged per {{ $timeline['granularity'] }} in the agreement period</caption><thead><tr><th scope="col">Period</th><th scope="col">Activities</th></tr></thead><tbody>
            @foreach($timeline['buckets'] as $bucket)<tr><th scope="row">{{ $bucket['label'] }}</th><td>{{ $bucket['count'] }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
    <x-recent-activity-table :activities="$recentActivities" :toolbar="false" hide-agreements empty-message="No activities logged for this agreement yet." />
</x-record-section>
