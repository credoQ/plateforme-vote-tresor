@php $estNouvelle = ! $edition->exists; @endphp
<x-layouts.app :titre="$estNouvelle ? 'Créer une édition' : 'Édition '.$edition->annee" :ariane="['Pilotage', 'Éditions', $estNouvelle ? 'Créer' : (string) $edition->annee]" actif="editions">
    <div class="titre-page">
        <div>
            <h1>{{ $estNouvelle ? 'Créer une édition' : 'Paramétrer l’édition '.$edition->annee }}</h1>
        </div>
    </div>

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ $estNouvelle ? route('editions.store') : route('editions.update', $edition) }}" class="formulaire" novalidate>
                @csrf
                @unless ($estNouvelle) @method('PUT') @endunless

                @foreach ([['annee', 'Année', 'number'], ['libelle', 'Libellé', 'text'], ['date_debut', 'Début de l’édition', 'date'], ['date_fin', 'Fin de l’édition', 'date'], ['date_gel', 'Gel des listes électorales', 'date']] as [$champ, $libelle, $type])
                    @php $valeur = $edition->{$champ} instanceof \DateTimeInterface ? $edition->{$champ}->format('Y-m-d') : $edition->{$champ}; @endphp
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} <span class="requis">*</span></label>
                        <input id="{{ $champ }}" name="{{ $champ }}" type="{{ $type }}" class="champ" value="{{ old($champ, $valeur) }}" required>
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <fieldset class="formulaire__groupe">
                    <legend class="libelle">Paramètres du vote</legend>
                    <label class="case"><input type="checkbox" name="vote_blanc_autorise" value="1" @checked(old('vote_blanc_autorise', $edition->vote_blanc_autorise))> Autoriser le vote blanc</label>
                    <label class="case"><input type="checkbox" name="vote_pour_soi_autorise" value="1" @checked(old('vote_pour_soi_autorise', $edition->exists ? $edition->vote_pour_soi_autorise : true))> Autoriser une candidate à voter pour elle-même</label>
                </fieldset>

                <div class="formulaire__groupe">
                    <label class="libelle" for="quorum">Quorum de participation (%)</label>
                    <input id="quorum" name="quorum" type="number" min="1" max="100" step="0.01" class="champ" value="{{ old('quorum', $edition->quorum) }}" placeholder="Aucun quorum">
                    @error('quorum')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>

                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>{{ $estNouvelle ? 'Créer l’édition' : 'Enregistrer l’édition' }}</button>
                    <a href="{{ $estNouvelle ? route('editions.index') : route('editions.show', $edition) }}" class="btn btn-tertiaire">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
