@props(['kind', 'count', 'scrollAfter' => 6, 'filterAfter' => 10])

<div class="app-ledger app-record-list" data-relationship-list data-list-kind="{{ $kind }}">
    @if($count > $filterAfter)
        <label class="form-label small" for="filter-{{ $kind }}">Filter {{ $kind }}</label>
        <input type="search" id="filter-{{ $kind }}" class="form-control form-control-sm mb-2" data-relationship-filter autocomplete="off">
        <span class="visually-hidden" aria-live="polite" data-filter-live></span>
    @endif
    <div class="app-ledger-scroll-frame">
        <div class="app-ledger-body" @if($count > $scrollAfter) data-ledger-scroll data-scroll-label="{{ ucfirst($kind) }}" data-scroll-rows="{{ $scrollAfter }}" @endif>{{ $slot }}</div>
    </div>
    <p class="app-ledger-scroll-status" data-ledger-scroll-status hidden></p>
    <p class="text-muted small mb-0" data-filter-empty hidden></p>
</div>
