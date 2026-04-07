@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="qs-pagination-wrap">
        <div class="qs-pagination-meta">
            Page {{ $paginator->currentPage() }} of {{ max($paginator->lastPage(), 1) }}
        </div>

        <ul class="qs-pagination-list">
            @if ($paginator->onFirstPage())
                <li>
                    <span class="qs-pagination-link is-disabled" aria-disabled="true" aria-label="Previous">Prev</span>
                </li>
            @else
                <li>
                    <a class="qs-pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">Prev</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="qs-pagination-link is-disabled" aria-disabled="true">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="qs-pagination-link is-active" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a class="qs-pagination-link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <a class="qs-pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">Next</a>
                </li>
            @else
                <li>
                    <span class="qs-pagination-link is-disabled" aria-disabled="true" aria-label="Next">Next</span>
                </li>
            @endif
        </ul>
    </nav>
@endif