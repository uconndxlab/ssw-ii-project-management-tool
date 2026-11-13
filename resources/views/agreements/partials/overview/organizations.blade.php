<x-record-section title="Organizations" :count="$overviewOrganizations->count()">
    <x-record-list kind="organizations" :count="$overviewOrganizations->count()">
        @forelse($overviewOrganizations as $organization)
            <x-organization-relation-row :organization="$organization" :show-route="auth()->user()->can('view', $organization) ? route('organizations.show', $organization) : ''" :kfs-numbers="$organizationKfsNumbers[(int) $organization->id] ?? []" />
        @empty
            <p class="text-muted small mb-0">No organizations linked.</p>
        @endforelse
    </x-record-list>
</x-record-section>
