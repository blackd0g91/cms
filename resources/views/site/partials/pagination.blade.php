{{-- Moving between pages of posts. Expects $paginator; shows nothing with one page. --}}
@if ($paginator->hasPages())
    <nav aria-label="Pages" class="mt-10 grid grid-cols-3 items-center gap-4 font-mono text-xs">
        <span>
            @unless ($paginator->onFirstPage())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-accent">
                    <span aria-hidden="true">←</span> Previous
                </a>
            @endunless
        </span>

        <span class="text-center text-muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        <span class="text-right">
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-accent">
                    Next <span aria-hidden="true">→</span>
                </a>
            @endif
        </span>
    </nav>
@endif
