@props([
    'contact',
])

@php
    $isPrimary = (bool) $contact->is_primary;
@endphp

<div class="border rounded px-3 py-2 {{ $isPrimary ? 'bg-primary-subtle' : 'bg-body' }}">
    <div class="d-flex flex-wrap align-items-center gap-2">
        @if($isPrimary)
            <i class="bi bi-star-fill text-primary" title="Primary contact" aria-label="Primary contact"></i>
        @endif
        @if(filled($contact->name))
            <span class="fw-semibold small text-break">{{ $contact->name }}</span>
        @endif
        @if(filled($contact->title))
            <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ $contact->title }}</span>
        @endif
    </div>

    @if(filled($contact->email) || filled($contact->phone))
        <div class="d-flex flex-wrap gap-3 mt-1">
            @if(filled($contact->email))
                <a href="mailto:{{ $contact->email }}" class="small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-envelope"></i>{{ $contact->email }}
                </a>
            @endif
            @if(filled($contact->phone))
                <a href="tel:{{ $contact->phone }}" class="small text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="bi bi-telephone"></i>{{ $contact->phone }}
                </a>
            @endif
        </div>
    @endif
</div>
