@if ($paginator->hasPages())
    <nav class="pm-pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pm-btn pm-btn-sm" aria-disabled="true" style="opacity:.5">Previous</span>
        @else
            <a class="pm-btn pm-btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        <span class="pm-muted pm-small">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pm-btn pm-btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="pm-btn pm-btn-sm" aria-disabled="true" style="opacity:.5">Next</span>
        @endif
    </nav>
@endif
