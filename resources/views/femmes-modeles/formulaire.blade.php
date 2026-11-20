<x-layouts.app centre titre="Ajouter une femme modèle" :ariane="['Pilotage', 'Femmes modèles', 'Ajouter']" actif="femmes-modeles">
    <div class="titre-page">
        <div>
            <h1>Ajouter une femme modèle</h1>
            <p>Femme modèle d’une édition antérieure à la plateforme.</p>
        </div>
    </div>

    <div class="carte">
        <form method="POST" action="{{ route('femmes-modeles.store') }}" novalidate>
            @csrf
            <div class="carte__corps formulaire formulaire--deux-colonnes">
                <div class="formulaire__groupe">
                    <label class="libelle" for="matricule">Matricule <span class="requis">*</span></label>
                    <input id="matricule" name="matricule" class="champ" value="{{ old('matricule') }}" autocomplete="off" required autofocus>
                    @error('matricule')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe">
                    <label class="libelle" for="annee">Année de l’élection <span class="requis">*</span></label>
                    <input id="annee" name="annee" type="number" min="{{ $anneeMinimale }}" max="{{ $anneeMaximale }}" class="champ" value="{{ old('annee') }}" required>
                    @error('annee')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe formulaire__groupe--pleine">
                    <label class="libelle" for="nom">Nom et prénoms</label>
                    <input id="nom" name="nom" class="champ" maxlength="200" value="{{ old('nom') }}">
                    <p class="aide">Facultatif si l’agent figure dans la liste des agents : son nom est alors repris de sa fiche.</p>
                    @error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe formulaire__groupe--pleine">
                    <label class="libelle" for="scrutin">Scrutin</label>
                    <input id="scrutin" name="scrutin" class="champ" maxlength="200" value="{{ old('scrutin') }}" placeholder="Ex. : Finale">
                    @error('scrutin')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="carte__corps formulaire__actions">
                <a href="{{ route('femmes-modeles.index') }}" class="btn btn-tertiaire" wire:navigate>Annuler</a>
                <button type="submit" class="btn btn-primaire"><i data-lucide="user-plus"></i>Ajouter à l’historique</button>
            </div>
        </form>
    </div>
</x-layouts.app>
