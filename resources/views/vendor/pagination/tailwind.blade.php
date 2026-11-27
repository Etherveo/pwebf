@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Navigasi halaman">
        <div class="pager-mobile">
            @if ($paginator->onFirstPage())
                <span class="pager-btn pager-btn-wide is-disabled" aria-disabled="true">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    <span>Sebelumnya</span>
                </span>
            @else
                <a class="pager-btn pager-btn-wide" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    <span>Sebelumnya</span>
                </a>
            @endif

            <span class="pager-mobile-meta">
                Halaman <strong>{{ $paginator->currentPage() }}</strong> / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a class="pager-btn pager-btn-wide" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    <span>Berikutnya</span>
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </a>
            @else
                <span class="pager-btn pager-btn-wide is-disabled" aria-disabled="true">
                    <span>Berikutnya</span>
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                </span>
            @endif
        </div>

        <div class="pager-desktop">
            <p class="pager-summary">
                Menampilkan
                @if ($paginator->firstItem())
                    <strong>{{ $paginator->firstItem() }}</strong>&ndash;<strong>{{ $paginator->lastItem() }}</strong>
                @else
                    <strong>{{ $paginator->count() }}</strong>
                @endif
                dari <strong>{{ $paginator->total() }}</strong> hasil
            </p>

            <div class="pager-list">
                @if ($paginator->onFirstPage())
                    <span class="pager-btn is-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @else
                    <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pager-dots" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pager-btn is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager-btn" href="{{ $url }}" aria-label="{{ __('Halaman :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span class="pager-btn is-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
