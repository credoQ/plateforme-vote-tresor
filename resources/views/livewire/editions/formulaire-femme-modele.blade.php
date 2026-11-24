{{-- Ajout d'une femme modèle : envoyé sans recharger la page ; rien ne part au serveur avant « Ajouter ». --}}
<div class="carte">
    <form wire:submit="enregistrer" novalidate>
        <div class="carte__corps formulaire formulaire--deux-colonnes">
            <div class="formulaire__groupe">
                <label class="libelle" for="matricule">Matricule <span class="requis">*</span></label>
                <input id="matricule" class="champ" wire:model="matricule" autocomplete="off" required autofocus>
                @error('matricule')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
            <div class="formulaire__groupe">
                <label class="libelle" for="annee">Année de l’élection <span class="requis">*</span></label>
                <input id="annee" type="number" min="{{ $anneeMinimale }}" max="{{ $anneeMaximale }}" class="champ" wire:model="annee" required>
                @error('annee')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
            <div class="formulaire__groupe formulaire__groupe--pleine">
                <label class="libelle" for="nom">Nom et prénoms</label>
                <input id="nom" class="champ" maxlength="200" wire:model="nom">
                <p class="aide">Facultatif si l’agent figure dans la liste des agents : son nom est alors repris de sa fiche.</p>
                @error('nom')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
            <div class="formulaire__groupe formulaire__groupe--pleine">
                <label class="libelle" for="scrutin">Scrutin</label>
                <input id="scrutin" class="champ" maxlength="200" wire:model="scrutin" placeholder="Ex. : Finale">
                @error('scrutin')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="carte__corps formulaire__actions">
            <a href="{{ route('femmes-modeles.index') }}" class="btn btn-tertiaire" wire:navigate>Annuler</a>
            <button type="submit" class="btn btn-primaire" wire:loading.attr="disabled" wire:target="enregistrer"><i data-lucide="user-plus"></i>Ajouter à l’historique</button>
        </div>
    </form>
</div>
