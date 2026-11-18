<x-layouts.app titre="Sécurité du compte" :ariane="['Mon espace', 'Sécurité du compte']" actif="securite">
    <div class="titre-page">
        <div>
            <div class="surtitre">Mon espace</div>
            <h1>Sécurité du compte</h1>
            <p>Identifiant : {{ $utilisateur->login }}{{ $utilisateur->email ? ' · e-mail : '.$utilisateur->email : ' · aucune adresse e-mail (le mot de passe oublié se réinitialise auprès du point focal)' }}</p>
        </div>
    </div>

    @if (session('status') === 'password-updated')
        <div class="alerte alerte--succes espace-bloc" role="status"><i data-lucide="circle-check"></i>
            <div>Votre mot de passe a été changé. <a class="lien" href="{{ route('espace') }}">Aller à l’accueil</a></div>
        </div>
    @elseif ($obligatoire)
        <div class="alerte alerte--attente espace-bloc" role="status"><i data-lucide="key-round"></i>
            <div><strong>Première connexion.</strong> Remplacez le mot de passe provisoire remis par votre point focal avant de continuer.</div>
        </div>
    @endif

    <section class="carte espace-bloc">
        <div class="carte__entete"><h2>Changer mon mot de passe</h2></div>
        <div class="carte__corps">
            <form method="POST" action="{{ route('user-password.update') }}" class="formulaire" novalidate>
                @csrf
                @method('PUT')
                <p class="aide">Au moins 10 caractères, avec des lettres et des chiffres.</p>
                @foreach ([['current_password', 'Mot de passe actuel', 'current-password'], ['password', 'Nouveau mot de passe', 'new-password'], ['password_confirmation', 'Confirmation du nouveau mot de passe', 'new-password']] as [$champ, $libelle, $autocomplete])
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
    </section>

    @unless ($obligatoire)
        <section class="carte">
            <div class="carte__entete">
                <h2>Double authentification</h2>
                @if ($utilisateur->hasEnabledTwoFactorAuthentication())
                    <span class="badge badge--succes">Activée</span>
                @else
                    <span class="badge">Désactivée</span>
                @endif
            </div>
            <div class="carte__corps">
                <p>En plus du mot de passe, un code à 6 chiffres généré par une application d’authentification (Google Authenticator, Microsoft Authenticator…) est demandé à chaque connexion. Recommandée pour les comptes du comité, des points focaux et de l’administration.</p>
                <div class="formulaire__actions">
                    <a href="{{ route('securite.double-authentification') }}" class="btn btn-secondaire"><i data-lucide="shield-check"></i>Gérer la double authentification</a>
                </div>
            </div>
        </section>
    @endunless
</x-layouts.app>
