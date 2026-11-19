{{-- Bas de liste paginée : {{ $agents->onEachSide(1)->links('pagination.tresor', ['libelle' => 'agents']) }}
     Pages voisines de la page courante, première et dernière, « … » entre les trous (calculées par Laravel). --}}
<div class="pagination">
    <span>
        @if ($paginator->total() > 0)
            Affichage de <strong class="chiffres">{{ $paginator->firstItem() }} à {{ $paginator->lastItem() }}</strong> sur <span class="chiffres">{{ number_format($paginator->total(), 0, ',', ' ') }}</span> {{ $libelle ?? 'éléments' }}
        @else
            Aucun élément
        @endif
    </span>

    @if ($paginator->hasPages())
        <nav class="pagination__pages" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="pagination__lien" aria-disabled="true" aria-label="Page précédente"><i data-lucide="chevron-left"></i></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__lien" rel="prev" aria-label="Page précédente"><i data-lucide="chevron-left"></i></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__ellipse" aria-hidden="true">…</span>
                @else
                    @foreach ($element as $page => $adresse)
                        @if ($page === $paginator->currentPage())
                            <span class="pagination__lien est-actif" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $adresse }}" class="pagination__lien" aria-label="Aller à la page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__lien" rel="next" aria-label="Page suivante"><i data-lucide="chevron-right"></i></a>
            @else
                <span class="pagination__lien" aria-disabled="true" aria-label="Page suivante"><i data-lucide="chevron-right"></i></span>
            @endif
        </nav>
    @endif
</div>
