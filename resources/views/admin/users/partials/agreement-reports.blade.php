@if($agreementReports->isEmpty())
    <p class="text-muted mb-0">This user is not assigned to any agreements.</p>
@else
    @foreach($agreementReports as $report)
        @php
            $agreement = $report['agreement'];
        @endphp
        <div class="mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                @if($agreement->isLinkable())
                    <a href="{{ route('agreements.show', $agreement) }}" class="h5 fw-semibold text-decoration-underline mb-0">
                        {{ $agreement->name }}
                    </a>
                @else
                    <span class="h5 fw-semibold mb-0">{{ $agreement->name }}</span>
                @endif
                @if(!$report['direct'])
                    @foreach($report['teams'] as $team)
                        <x-entity-relation-badge kind="team" :href="route('teams.show', $team)">{{ $team->name }}</x-entity-relation-badge>
                    @endforeach
                @endif
            </div>

            @if($agreement->organizations->isNotEmpty())
                <div class="small text-muted mb-2">{{ $agreement->organizations->pluck('name')->join(', ') }}</div>
            @endif

            @if($agreement->states->isNotEmpty())
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @foreach($agreement->states as $state)
                        <x-entity-relation-badge kind="state" :href="route('states.show', $state)">{{ $state->name }}</x-entity-relation-badge>
                    @endforeach
                </div>
            @endif

            @include('admin.users.partials.deliverable-report-section', [
                'deliverableGroups' => $report['deliverableGroups'],
            ])
        </div>
    @endforeach
@endif
