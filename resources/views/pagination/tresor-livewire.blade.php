{{-- Bas de liste paginée dans un composant Livewire : même rendu que pagination.tresor, mais la page change
     sans rechargement (gotoPage, previousPage, nextPage du trait WithPagination). --}}
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
            <button type="button" class="pagination__lien" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) aria-label="Page précédente"><i data-lucide="chevron-left"></i></button>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__ellipse" aria-hidden="true">…</span>
                @else
                    @foreach ($element as $page => $adresse)
                        @if ($page === $paginator->currentPage())
                            <span class="pagination__lien est-actif" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" class="pagination__lien" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" aria-label="Aller à la page {{ $page }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <button type="button" class="pagination__lien" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages()) aria-label="Page suivante"><i data-lucide="chevron-right"></i></button>
        </nav>
    @endif
</div>
