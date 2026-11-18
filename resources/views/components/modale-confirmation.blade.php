@props(['id', 'titre', 'action', 'bouton', 'icone' => 'check', 'danger' => false])
{{-- Modale de confirmation d'une action : nomme l'objet et dit ce qui va se passer (règles des vues). --}}
<div class="voile" id="{{ $id }}" hidden>
    <div class="modale" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-titre">
        <form method="POST" action="{{ $action }}">
            @csrf
            <div class="modale__entete">
                <span @class(['pastille-icone', 'pastille-icone--danger' => $danger])><i data-lucide="{{ $icone }}"></i></span>
                <div><h2 id="{{ $id }}-titre">{{ $titre }}</h2></div>
            </div>
            <div class="modale__corps">
                <p>{{ $slot }}</p>
                {{ $champs ?? '' }}
            </div>
            <div class="modale__pied">
                <button type="button" class="btn btn-secondaire" data-fermer>Annuler</button>
                <button type="submit" @class(['btn', 'btn-primaire' => ! $danger, 'btn-danger' => $danger])><i data-lucide="{{ $icone }}"></i>{{ $bouton }}</button>
            </div>
        </form>
    </div>
</div>
