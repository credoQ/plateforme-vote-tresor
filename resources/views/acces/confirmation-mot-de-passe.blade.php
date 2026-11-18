<x-layouts.app titre="Confirmer le mot de passe" :ariane="['Mon espace', 'Sécurité', 'Confirmation']" actif="securite">
    <div class="titre-page">
        <div>
            <h1>Confirmez votre mot de passe</h1>
            <p>Cette opération touche à la sécurité de votre compte : confirmez d’abord votre mot de passe.</p>
        </div>
    </div>
    <div class="carte">
        <div class="carte__corps">
            <form method="POST" action="{{ route('password.confirm.store') }}" class="formulaire" novalidate>
                @csrf
                <div class="formulaire__groupe">
                    <label class="libelle" for="password">Mot de passe <span class="requis">*</span></label>
                    <input id="password" name="password" type="password" class="champ" autocomplete="current-password" required autofocus>
                    @error('password')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__actions"><button type="submit" class="btn btn-primaire"><i data-lucide="lock-keyhole"></i>Confirmer le mot de passe</button></div>
            </form>
        </div>
    </div>
</x-layouts.app>
