@props([
    'status' => null,
])

@if($status === \App\Enums\DeliverableStatus::NotApplicable)
    <div {{ $attributes->class(['small text-muted']) }}>Status: N/A</div>
@elseif($status)
    <div {{ $attributes->class(['small d-flex align-items-center justify-content-end gap-1 text-'.$status->tone()]) }}>
        @if($status->icon())
            <i class="bi bi-{{ $status->icon() }}" aria-hidden="true"></i>
        @endif
        <span>{{ $status->label() }}</span>
    </div>
@endif
