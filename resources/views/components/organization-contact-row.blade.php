@props([
    'contact',
])

<x-relationship-ledger-row
    :title="filled($contact->name) ? $contact->name : 'Contact'"
    :context-badges="collect([
        filled($contact->title) ? ['label' => $contact->title] : null,
        $contact->is_primary ? ['label' => 'Primary contact'] : null,
    ])->filter()->all()"
    {{ $attributes }}
>
    @if(filled($contact->email) || filled($contact->phone))
        <x-slot:footer>
            @if(filled($contact->email))
                <span><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></span>
            @endif
            @if(filled($contact->phone))
                <span><a href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a></span>
            @endif
        </x-slot:footer>
    @endif
</x-relationship-ledger-row>
