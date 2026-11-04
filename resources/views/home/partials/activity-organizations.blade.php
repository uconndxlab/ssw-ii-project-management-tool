@if($activity->organizations->isNotEmpty())
    <div class="d-flex flex-wrap align-items-center gap-1 small mt-2">
        <span class="text-muted">Covered organizations:</span>
        @foreach($activity->organizations->take(3) as $organization)
            <x-entity-relation-badge kind="organization" :href="$organization->isLinkable() ? route('organizations.show', $organization) : null" wrap>
                {{ $organization->name }}
            </x-entity-relation-badge>
        @endforeach
        @if($activity->organizations->count() > 3)
            <span class="text-muted">+{{ $activity->organizations->count() - 3 }} more</span>
        @endif
    </div>
@endif
