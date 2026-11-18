<x-layouts.invite titre="Nouveau mot de passe">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <h2>Nouveau mot de passe</h2>
        <p>Au moins 10 caractères, avec des lettres et des chiffres.</p>

        <div class="formulaire__groupe">
            <label class="libelle" for="email">Adresse e-mail <span class="requis">*</span></label>
            <input id="email" name="email" type="email" class="champ" value="{{ old('email', $request->query('email')) }}" autocomplete="email" required>
        </div>
        <div class="formulaire__groupe">
            <label class="libelle" for="password">Nouveau mot de passe <span class="requis">*</span></label>
            <input id="password" name="password" type="password" class="champ" autocomplete="new-password" required autofocus>
        </div>
        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="password_confirmation">Confirmation <span class="requis">*</span></label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="champ" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="save"></i>Enregistrer le mot de passe</button>
    </form>
</x-layouts.invite>
