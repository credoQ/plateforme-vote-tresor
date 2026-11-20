<x-layouts.invite titre="Connexion" :indexer="true" :edition-en-cours="$editionEnCours">
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-etape-connexion :etape="1" />
        <h2>Connexion</h2>
        <p>Saisissez votre matricule : un code à usage unique vous sera envoyé par e-mail.</p>

        <div class="formulaire__groupe espace-bloc">
            <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" name="login" class="champ champ--grand" value="{{ old('login') }}" placeholder="Ex. 123456" autocomplete="username" required autofocus></div>
        </div>

        <div class="connexion__options">
            <label class="case"><input type="checkbox" name="remember" value="1"> Se souvenir de ce poste</label>
        </div>
        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="send"></i>Recevoir mon code</button>

        <div class="connexion__aide"><i data-lucide="info"></i><span>Pas d’adresse e-mail enregistrée ? Adressez-vous au point focal de votre structure.</span></div>
    </form>
</x-layouts.invite>
