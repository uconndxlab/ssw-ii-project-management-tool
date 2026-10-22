@props([
    'name' => null,
    'href' => null,
    'user' => null,
    'role' => null,
    'isPrincipalInvestigator' => false,
])

<x-relationship-ledger-row
    :title="$name ?? $user?->name"
    :href="$user ? \App\Support\UserProfileLink::route($user) : $href"
    :context-badges="collect([
        $role ? ['label' => ucfirst($role), 'kind' => 'role'] : null,
        $isPrincipalInvestigator ? ['label' => 'PI', 'kind' => 'pi'] : null,
    ])->filter()->all()"
    {{ $attributes }}
>
    @isset($after)
        <x-slot:footer>{{ $after }}</x-slot:footer>
    @endisset
</x-relationship-ledger-row>
