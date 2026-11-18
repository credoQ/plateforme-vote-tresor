<x-layouts.app titre="Mot de passe" :ariane="['Mon espace', 'Mot de passe']">
    <div class="titre-page">
        <div>
            <h1>Changer mon mot de passe</h1>
            <p>Au moins 10 caractères, avec des lettres et des chiffres.</p>
        </div>
    </div>

    @if ($obligatoire)
        <div class="alerte alerte--attente espace-bloc" role="status"><i data-lucide="key-round"></i>
            <div><strong>Première connexion.</strong> Remplacez le mot de passe provisoire remis par votre point focal avant de continuer.</div>
        </div>
    @endif

    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ route('mot-de-passe.update') }}" class="formulaire" novalidate>
                @csrf
                @method('PUT')
                @foreach ([['mot_de_passe_actuel', 'Mot de passe actuel', 'current-password'], ['mot_de_passe', 'Nouveau mot de passe', 'new-password'], ['mot_de_passe_confirmation', 'Confirmation du nouveau mot de passe', 'new-password']] as [$champ, $libelle, $autocomplete])
                    <div class="formulaire__groupe">
                        <label class="libelle" for="{{ $champ }}">{{ $libelle }} <span class="requis">*</span></label>
                        <input id="{{ $champ }}" name="{{ $champ }}" type="password" class="champ" autocomplete="{{ $autocomplete }}" required>
                        @error($champ)<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="formulaire__actions">
                    <button type="submit" class="btn btn-primaire"><i data-lucide="save"></i>Enregistrer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
