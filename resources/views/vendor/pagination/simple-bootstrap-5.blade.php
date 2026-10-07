<nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100 pagination-summary" aria-label="{{ __('Pagination Navigation') }}">
    <p class="small text-muted mb-0">
        {{ __('Showing') }}
        <span class="fw-semibold">{{ $paginator->firstItem() ?? 0 }}</span>
        {{ __('to') }}
        <span class="fw-semibold">{{ $paginator->lastItem() ?? 0 }}</span>
        {{ __('entries') }}
    </p>
    @if ($paginator->hasPages())
        <ul class="pagination mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('pagination.previous') }}</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('pagination.previous') }}</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('pagination.next') }}</a></li>
            @else
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ __('pagination.next') }}</span></li>
            @endif
        </ul>
    @endif
</nav>
