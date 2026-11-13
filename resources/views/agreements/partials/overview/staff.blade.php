@php
    // A person in two teams is listed under both (it is true of each team); the heading count stays unique people.
    $staffGroups = $agreement->teams->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
        ->map(fn ($team) => ['team' => $team, 'members' => $overviewStaff->filter(fn ($entry) => $team->users->contains('id', $entry['user']->id))])
        ->push(['team' => null, 'members' => $overviewStaff->filter(fn ($entry) => $entry['teams'] === [])])
        ->filter(fn ($group) => $group['members']->isNotEmpty());
@endphp
<x-record-section title="Staff" :count="$overviewStaff->count()">
    <x-record-list kind="staff" :count="$overviewStaff->count()">
        @forelse($staffGroups as $group)
            <div class="app-ledger-group" data-ledger-group>
                <div class="app-ledger-group-head">
                    @if($group['team'])
                        @can('view', $group['team'])<a class="app-link" href="{{ route('teams.show', $group['team']) }}">{{ $group['team']->name }}</a>@else{{ $group['team']->name }}@endcan
                    @else
                        Added directly
                    @endif
                    <span class="app-count">{{ $group['members']->count() }}</span>
                </div>
                @foreach($group['members'] as $entry)
                    <x-relationship-ledger-row :title="$entry['user']->name" :href="$entry['href']" :context-badges="$entry['is_pi'] ? [['label' => 'PI', 'kind' => 'pi']] : []" />
                @endforeach
            </div>
        @empty
            <p class="text-muted small mb-0">No staff assigned.</p>
        @endforelse
    </x-record-list>
</x-record-section>
