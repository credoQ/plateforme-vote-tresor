<x-layouts.invite titre="Code de connexion">
    <form method="POST" action="{{ route('login.verifier') }}" novalidate>
        @csrf
        <h2>Code de connexion</h2>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="code">Code reçu par e-mail <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="key-round"></i><input id="code" name="code" class="champ" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 chiffres" required autofocus></div>
            <p class="aide">Matricule {{ $login }} · code valable {{ \App\Actions\Acces\DemanderCodeConnexion::VALIDITE_MINUTES }} minutes. Sans e-mail reçu, vérifiez vos courriers indésirables ou faites enregistrer votre adresse par votre point focal.</p>
        </div>

        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="log-in"></i>Se connecter</button>
    </form>

    <div class="connexion__liens">
        <form method="POST" action="{{ route('login.renvoyer') }}">
            @csrf
            <button type="submit" class="lien lien--bouton">Renvoyer un code</button>
        </form>
        <a href="{{ route('login') }}" class="lien">Changer de matricule</a>
    </div>
</x-layouts.invite>
