<x-layouts.invite titre="Connexion" :indexer="true">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <h2>Connexion</h2>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" name="login" class="champ" value="{{ old('login') }}" placeholder="Ex. 123456" autocomplete="username" required autofocus></div>
            <p class="aide">Un code de connexion sera envoyé à l’adresse e-mail de votre compte.</p>
        </div>

        <div class="connexion__options">
            <label class="case"><input type="checkbox" name="remember" value="1"> Se souvenir de ce poste</label>
        </div>
        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="send"></i>Recevoir mon code</button>
    </form>
</x-layouts.invite>
