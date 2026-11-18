<x-layouts.invite titre="Double authentification">
    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
        @csrf
        <h2>Double authentification</h2>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="code">Code de l’application d’authentification</label>
            <input id="code" name="code" class="champ" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus>
        </div>

        <details class="espace-bloc">
            <summary class="lien">Utiliser un code de secours</summary>
            <div class="formulaire__groupe">
                <label class="libelle" for="recovery_code">Code de secours</label>
                <input id="recovery_code" name="recovery_code" class="champ" autocomplete="off">
            </div>
        </details>

        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="shield-check"></i>Valider</button>
    </form>
</x-layouts.invite>
