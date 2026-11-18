<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · Femme modèle du Trésor</title>
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="connexion">
    <section class="connexion__panneau">
        <div class="connexion__marque">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="" class="connexion__embleme">
            <div><strong>Direction générale du Trésor</strong><small>et de la Comptabilité publique</small></div>
        </div>
        <div class="connexion__accroche">
            <div class="surtitre">Plateforme de vote</div>
            <h1>Élisez la Femme modèle du Trésor</h1>
            <p>Un agent, une voix par phase. Votre vote est secret : la plateforme enregistre que vous avez voté, jamais pour qui.</p>
        </div>
        <div class="connexion__mention">République du Bénin · Ministère de l'Économie et des Finances</div>
    </section>

    <section class="connexion__formulaire">
        <form class="connexion__boite" method="POST" action="{{ route('connexion.store') }}" novalidate>
            @csrf
            <h2>Connexion</h2>
            <p>Identifiez-vous avec votre matricule d'agent.</p>

            @if ($errors->any())
                <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $errors->first() }}</div></div>
            @endif

            <div class="formulaire__groupe">
                <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
                <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" name="login" class="champ" value="{{ old('login') }}" placeholder="Ex. 123456" autocomplete="username" required autofocus></div>
            </div>

            <div class="formulaire__groupe espace-bloc">
                <label class="libelle" for="password">Mot de passe <span class="requis">*</span></label>
                <div class="champ-icone"><i data-lucide="lock"></i><input id="password" name="password" type="password" class="champ" placeholder="Votre mot de passe" autocomplete="current-password" required></div>
                <p class="aide">Première connexion : utilisez le mot de passe remis par votre point focal, puis changez-le. Mot de passe oublié : adressez-vous à votre point focal.</p>
            </div>

            <div class="connexion__options">
                <label class="case"><input type="checkbox" name="se_souvenir" value="1"> Se souvenir de ce poste</label>
            </div>
            <button type="submit" class="btn btn-primaire btn--lg" style="width:100%"><i data-lucide="log-in"></i>Se connecter</button>
            <div class="bande-tricolore" style="margin-top:32px;border-radius:2px"></div>
        </form>
    </section>
</div>
</body>
</html>
