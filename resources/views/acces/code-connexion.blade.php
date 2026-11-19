<x-layouts.invite titre="Code de connexion" :edition-en-cours="$editionEnCours">
    <form method="POST" action="{{ route('login.verifier') }}" novalidate>
        @csrf
        <x-etape-connexion :etape="2" />
        <h2>Code de connexion</h2>
        <p>Un code à 6 chiffres a été envoyé à l’adresse e-mail de votre compte (matricule {{ $login }}).</p>

        <div class="formulaire__groupe espace-bloc" x-data="saisieCode">
            <label class="libelle" for="code">Code reçu par e-mail <span class="requis">*</span></label>
            {{-- Sans JavaScript : un seul champ. Avec : six cases, remplies d'un coup quand on colle le code. --}}
            <div class="champ-icone" x-ref="natif"><i data-lucide="key-round"></i><input id="code" name="code" x-ref="code" class="champ" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 chiffres" required autofocus></div>
            <div class="saisie-code" role="group" aria-label="Code reçu par e-mail, 6 chiffres" x-cloak>
                <template x-for="(chiffre, i) in chiffres" x-bind:key="i">
                    <input type="text" class="saisie-code__case" inputmode="numeric" maxlength="1" x-bind:autocomplete="i === 0 ? 'one-time-code' : 'off'"
                           x-bind:aria-label="'Chiffre ' + (i + 1) + ' sur 6'" x-bind:value="chiffre" x-bind:class="{ 'est-remplie': chiffre !== '' }"
                           x-on:input="saisir(i, $event)" x-on:keydown.backspace="effacer(i, $event)" x-on:keydown.arrow-left.prevent="aller(i - 1)"
                           x-on:keydown.arrow-right.prevent="aller(i + 1)" x-on:paste.prevent="coller($event)" x-on:focus="$event.target.select()">
                </template>
            </div>
            <p class="aide">Valable {{ $validiteMinutes }} minutes. Collez le code : les cases se remplissent seules.</p>
            @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn btn-primaire btn--lg connexion__bouton"><i data-lucide="log-in"></i>Se connecter</button>
    </form>

    <div class="connexion__liens" x-data="{ attente: {{ $attenteRenvoi }} }" x-init="const minuteur = setInterval(() => { if (attente > 0) { attente-- } else { clearInterval(minuteur) } }, 1000)">
        <span class="connexion__attente" x-show="attente > 0" x-cloak>Renvoyer un code dans <strong class="chiffres" x-text="Math.floor(attente / 60) + ':' + String(attente % 60).padStart(2, '0')"></strong></span>
        <form method="POST" action="{{ route('login.renvoyer') }}" x-show="attente === 0">
            @csrf
            <button type="submit" class="lien lien--bouton">Renvoyer un code</button>
        </form>
        <a href="{{ route('login') }}" class="lien">Changer de matricule</a>
    </div>

    <div class="connexion__aide"><i data-lucide="mail"></i><span>Rien reçu ? Vérifiez vos courriers indésirables, ou faites enregistrer votre adresse par votre point focal.</span></div>
</x-layouts.invite>
