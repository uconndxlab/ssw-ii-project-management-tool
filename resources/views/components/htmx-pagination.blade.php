{{--
    HTMX-aware Bootstrap pagination.
    Props:
        paginator  - LengthAwarePaginator instance
        target     - CSS selector string for hx-target (e.g. "#states-table")
--}}
@props(['paginator', 'target'])

@if($paginator->hasPages())
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted" data-result-count>
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
        of {{ number_format($paginator->total()) }}
    </small>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            {{-- Prev --}}
            @if($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link" aria-hidden="true">‹</span></li>
            @else
                <li class="page-item">
                    <a class="page-link"
                       href="{{ $paginator->previousPageUrl() }}"
                       hx-get="{{ $paginator->previousPageUrl() }}"
                       hx-target="{{ $target }}"
                       hx-swap="innerHTML"
                       hx-push-url="true"
                       aria-label="Previous page">‹</a>
                </li>
            @endif

            {{-- Window of pages (max 7 shown) --}}
            @php
                $window = 3;
                $last   = $paginator->lastPage();
                $cur    = $paginator->currentPage();
                $from   = max(1, $cur - $window);
                $to     = min($last, $cur + $window);
            @endphp

            @if($from > 1)
                <li class="page-item">
                    <a class="page-link"
                       href="{{ $paginator->url(1) }}"
                       hx-get="{{ $paginator->url(1) }}"
                       hx-target="{{ $target }}"
                       hx-swap="innerHTML"
                       hx-push-url="true"
                       aria-label="Page 1">1</a>
                </li>
                @if($from > 2)
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>
                @endif
            @endif

            @for($p = $from; $p <= $to; $p++)
                @if($p === $cur)
                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $p }}</span></li>
                @else
                    <li class="page-item">
                        <a class="page-link"
                           href="{{ $paginator->url($p) }}"
                           hx-get="{{ $paginator->url($p) }}"
                           hx-target="{{ $target }}"
                           hx-swap="innerHTML"
                           hx-push-url="true"
                           aria-label="Page {{ $p }}">{{ $p }}</a>
                    </li>
                @endif
            @endfor

            @if($to < $last)
                @if($to < $last - 1)
                    <li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>
                @endif
                <li class="page-item">
                    <a class="page-link"
                       href="{{ $paginator->url($last) }}"
                       hx-get="{{ $paginator->url($last) }}"
                       hx-target="{{ $target }}"
                       hx-swap="innerHTML"
                       hx-push-url="true"
                       aria-label="Page {{ $last }}">{{ $last }}</a>
                </li>
            @endif

            {{-- Next --}}
            @if($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link"
                       href="{{ $paginator->nextPageUrl() }}"
                       hx-get="{{ $paginator->nextPageUrl() }}"
                       hx-target="{{ $target }}"
                       hx-swap="innerHTML"
                       hx-push-url="true"
                       aria-label="Next page">›</a>
                </li>
            @else
                <li class="page-item disabled"><span class="page-link" aria-hidden="true">›</span></li>
            @endif
        </ul>
    </nav>
</div>
@else
<small class="text-muted" data-result-count>{{ number_format($paginator->total()) }} record{{ $paginator->total() === 1 ? '' : 's' }}</small>
@endif
