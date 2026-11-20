<!DOCTYPE html>
<html lang="fr" data-theme="clair">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · Femme modèle du Trésor</title>
    <script>try { const t = localStorage.getItem('fm-theme'); if (t) { document.documentElement.dataset.theme = t; } } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="connexion">
    <section class="connexion__panneau">
        <div style="display:flex;align-items:center;gap:14px;position:relative">
            <img src="{{ asset('images/embleme-tresor.png') }}" alt="" style="width:56px;height:56px;background:#fff;border-radius:50%;padding:3px">
            <div>
                <div style="font-weight:700;font-size:15px">Direction générale du Trésor</div>
                <div style="font-size:12px;opacity:.8">et de la Comptabilité publique</div>
            </div>
        </div>
        <div style="position:relative;max-width:440px">
            <div class="surtitre" style="color:var(--tr-accent-sur-primaire)">Édition 2026</div>
            <h1 style="font-size:32px;line-height:40px;font-weight:700;margin:8px 0 12px">Élisez la Femme modèle du Trésor</h1>
            <p style="opacity:.85">Un agent, une voix par phase. Votre vote est secret : la plateforme enregistre que vous avez voté, jamais pour qui.</p>
            <div style="display:flex;gap:24px;margin-top:28px">
                <div><div style="font-size:24px;font-weight:700">3</div><div style="font-size:12px;opacity:.75">phases</div></div>
                <div><div style="font-size:24px;font-weight:700">12</div><div style="font-size:12px;opacity:.75">départements</div></div>
                <div><div style="font-size:24px;font-weight:700">1</div><div style="font-size:12px;opacity:.75">lauréate nationale</div></div>
            </div>
        </div>
        <div style="position:relative;font-size:12px;opacity:.7">République du Bénin · Ministère de l'Économie et des Finances</div>
    </section>

    <section class="connexion__formulaire">
        <form class="connexion__boite" action="{{ route('maquettes.page', 'vote') }}">
            <h2 style="font-size:24px;line-height:32px;font-weight:700">Connexion</h2>
            <p style="color:var(--tr-texte-2);margin:4px 0 28px">Identifiez-vous avec votre matricule d'agent.</p>

            <label class="libelle" for="matricule">Matricule <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="id-card"></i><input id="matricule" class="champ" placeholder="Ex. 123456" autocomplete="username"></div>

            <div style="height:16px"></div>
            <label class="libelle" for="mdp">Mot de passe <span class="requis">*</span></label>
            <div class="champ-icone"><i data-lucide="lock"></i><input id="mdp" type="password" class="champ" placeholder="Votre mot de passe" autocomplete="current-password"></div>
            <p class="aide">Première connexion : utilisez le mot de passe remis par votre point focal, puis changez-le.</p>

            <div style="display:flex;justify-content:space-between;align-items:center;margin:20px 0 24px;font-size:13px">
                <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" style="accent-color:var(--tr-primaire);width:16px;height:16px"> Se souvenir de ce poste</label>
                <a href="#" class="lien">Mot de passe oublié ?</a>
            </div>
            <button class="btn btn-primaire btn--lg" style="width:100%"><i data-lucide="log-in"></i>Se connecter</button>

            <div class="alerte alerte--info" style="margin-top:24px"><i data-lucide="info"></i>
                <div><strong>Phase 1 ouverte</strong> jusqu'au 28 novembre à 18 h. Vous votez dans la circonscription de votre poste d'affectation.</div>
            </div>
            <div class="bande-tricolore" style="margin-top:32px;border-radius:2px"></div>
        </form>
    </section>
</div>
@livewireScripts
</body>
</html>
