<x-layouts.invite titre="Mot de passe oublié">
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <h2>Mot de passe oublié</h2>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="email">Adresse e-mail <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="mail"></i><input id="email" name="email" type="email" class="champ" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
            <p class="aide">Pas d’adresse e-mail sur votre compte ? Demandez à votre point focal ou à l’administrateur de réinitialiser votre mot de passe.</p>
        </div>

        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="send"></i>Recevoir le lien</button>
        <p class="aide connexion__retour"><a href="{{ route('login') }}" class="lien">Retour à la connexion</a></p>
    </form>
</x-layouts.invite>
