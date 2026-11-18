@props(['route', 'parametres', 'libelle'])
{{-- Liens d'export d'un même contenu en Excel, PDF et CSV (module 08). --}}
<span class="liens-export">
    <span class="aide">{{ $libelle }}</span>
    <a href="{{ route($route, [...(array) $parametres, 'format' => 'xlsx']) }}" class="btn btn-secondaire btn--sm"><i data-lucide="file-spreadsheet"></i>Excel</a>
    <a href="{{ route($route, [...(array) $parametres, 'format' => 'pdf']) }}" class="btn btn-secondaire btn--sm"><i data-lucide="file-down"></i>PDF</a>
    <a href="{{ route($route, [...(array) $parametres, 'format' => 'csv']) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="file-text"></i>CSV</a>
</span>
