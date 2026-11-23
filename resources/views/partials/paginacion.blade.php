@if ($paginator->hasPages())
    <nav class="paginacion" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="off">‹ Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
        @endif

        @if (isset($elements))
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="off">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="actual">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
        @else
            <span class="off">Siguiente ›</span>
        @endif
    </nav>
@endif
