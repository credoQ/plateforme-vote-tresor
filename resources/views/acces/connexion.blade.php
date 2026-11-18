<x-layouts.invite titre="Connexion">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <h2>Connexion</h2>

        <div class="formulaire__groupe">
            <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" name="login" class="champ" value="{{ old('login') }}" placeholder="Ex. 123456" autocomplete="username" required autofocus></div>
        </div>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="password">Mot de passe <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="lock"></i><input id="password" name="password" type="password" class="champ" placeholder="Votre mot de passe" autocomplete="current-password" required></div>
            <p class="aide">Première connexion : utilisez le mot de passe remis par votre point focal, puis changez-le.</p>
        </div>

        <div class="connexion__options">
            <label class="case"><input type="checkbox" name="remember" value="1"> Se souvenir de ce poste</label>
            <a href="{{ route('password.request') }}" class="lien">Mot de passe oublié ?</a>
        </div>
        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="log-in"></i>Se connecter</button>
    </form>
</x-layouts.invite>
