@props(['title', 'count' => null, 'restricted' => 0, 'restrictedKind' => 'records', 'link' => null, 'linkLabel' => null, 'linkSection' => null])

<section {{ $attributes->class(['app-record-section']) }}>
    <div class="app-record-section-heading">
        <h2 class="h6 mb-0">{{ $title }}</h2>
        @if($count !== null)<span class="app-count">{{ $count }}</span>@endif
        <x-restricted-count :count="$restricted" :kind="$restrictedKind" />
        @if($link)
            <a href="{{ $link }}" @if($linkSection) data-section-goto="{{ $linkSection }}" @endif class="app-link small ms-auto">{{ $linkLabel }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        @endif
    </div>
    <div class="card shadow-sm app-record-section-card"><div class="card-body">{{ $slot }}</div></div>
</section>
