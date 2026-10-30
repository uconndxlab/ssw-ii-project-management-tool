{{--
    HTMX-aware Bootstrap pagination.
    Props:
        paginator  - LengthAwarePaginator instance
        target     - CSS selector string for hx-target (e.g. "#states-table")
--}}
@props(['paginator', 'target'])

<div class="app-table-pagination">
    <form method="GET" action="{{ request()->url() }}"
          class="app-table-page-size"
          hx-get="{{ request()->url() }}"
          hx-trigger="change"
          hx-target="{{ $target }}"
          hx-swap="innerHTML"
          hx-push-url="true">
        @foreach(request()->except(['page', 'per_page', 'partial']) as $name => $value)
            @if(is_scalar($value))
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="page" value="1">
        <label class="d-flex align-items-center gap-2">
            Show
            <select name="per_page" class="form-select form-select-sm" data-table-page-size aria-label="Rows per page">
                @foreach(\App\Support\TablePagination::SIZES as $size)
                    <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <span class="text-muted">per page</span>
        </label>
        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Apply</button></noscript>
    </form>
    <small class="text-muted" data-result-count>
        Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}
        of {{ number_format($paginator->total()) }}
    </small>
    @if($paginator->hasPages())
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
    @endif
</div>
