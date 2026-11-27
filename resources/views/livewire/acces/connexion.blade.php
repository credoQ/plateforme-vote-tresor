{{-- Une seule racine, toujours présente : Livewire encadre chaque @if de commentaires, qui ne doivent pas se trouver
     hors de la racine du composant. --}}
<div class="connexion-mode">
@if ($codeImprime)
{{-- Mode « code imprimé » : matricule et code personnel du coupon remis par le comité, en une étape, sans e-mail. --}}
<div class="connexion-etapes" x-init="setTimeout(() => $refs.login?.focus(), 60)">
    <section>
        <form wire:submit="seConnecter" novalidate>
            <h2>Connexion</h2>
            <p>Saisissez votre matricule et le code personnel inscrit sur le coupon que le comité vous a remis.</p>

            <div class="formulaire__groupe espace-bloc">
                <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
                <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" x-ref="login" wire:model="login" class="champ champ--grand" placeholder="Ex. 123456" autocomplete="username" required autofocus></div>
                @error('login')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>

            <div class="formulaire__groupe espace-bloc">
                <label class="libelle" for="code-acces">Code personnel <span class="requis">*</span></label>
                <div class="champ-icone"><i data-lucide="key-round"></i><input id="code-acces" wire:model="code" class="champ champ--grand champ--majuscules" placeholder="Ex. K7RM-4XQP" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" required></div>
                @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>

            <div class="connexion__options">
                <label class="case"><input type="checkbox" wire:model="souvenir"> Se souvenir de ce poste</label>
            </div>
            <button type="submit" class="btn btn-primaire btn--lg connexion__bouton" wire:loading.attr="disabled" wire:target="seConnecter"><i data-lucide="log-in"></i>Se connecter</button>

            <div class="connexion__aide"><i data-lucide="info"></i><span>Coupon perdu ou code refusé ? Adressez-vous au comité d’organisation : il vous remettra un nouveau code.</span></div>
        </form>
    </section>
</div>
@else
<div class="connexion-etapes"
     x-effect="const etape = $wire.etape; setTimeout(() => (etape === 'code' ? $root.querySelector('.saisie-code__case') : $refs.login)?.focus(), 60)">
    {{-- Étape 1 : le matricule. Elle sort vers la gauche et revient par la gauche. --}}
    <section wire:ignore.self x-show="$wire.etape === 'matricule'" @if ($etape !== 'matricule') x-cloak @endif
             x-transition:enter="glissement" x-transition:enter-start="glissement--gauche" x-transition:enter-end="glissement--centre"
             x-transition:leave="glissement" x-transition:leave-start="glissement--centre" x-transition:leave-end="glissement--gauche">
        <form wire:submit="envoyer" novalidate>
            <x-etape-connexion :etape="1" />
            <h2>Connexion</h2>
            <p>Saisissez votre matricule : un code à usage unique vous sera envoyé par e-mail.</p>

            <div class="formulaire__groupe espace-bloc">
                <label class="libelle" for="login">Matricule <span class="requis">*</span></label>
                <div class="champ-icone"><i data-lucide="id-card"></i><input id="login" x-ref="login" wire:model="login" class="champ champ--grand" placeholder="Ex. 123456" autocomplete="username" required @if ($etape === 'matricule') autofocus @endif></div>
                @error('login')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>

            <div class="connexion__options">
                <label class="case"><input type="checkbox" wire:model="souvenir"> Se souvenir de ce poste</label>
            </div>
            <button type="submit" class="btn btn-primaire btn--lg connexion__bouton" wire:loading.attr="disabled" wire:target="envoyer"><i data-lucide="send"></i>Recevoir mon code</button>

            <div class="connexion__aide"><i data-lucide="info"></i><span>Pas d’adresse e-mail enregistrée ? Adressez-vous au point focal de votre structure.</span></div>
        </form>
    </section>

    {{-- Étape 2 : le code reçu par e-mail. Elle arrive par la droite et repart par la droite. --}}
    <section wire:ignore.self x-show="$wire.etape === 'code'" @if ($etape !== 'code') x-cloak @endif
             x-transition:enter="glissement" x-transition:enter-start="glissement--droite" x-transition:enter-end="glissement--centre"
             x-transition:leave="glissement" x-transition:leave-start="glissement--centre" x-transition:leave-end="glissement--droite">
        <form wire:submit="verifier" novalidate>
            <x-etape-connexion :etape="2" />
            <h2>Code de connexion</h2>
            <p>Un code à 6 chiffres a été envoyé à l’adresse e-mail de votre compte (matricule {{ $login }}).</p>

            <div class="formulaire__groupe espace-bloc" x-data="saisieCode" x-on:code-efface.window="vider()">
                <p class="libelle">Code reçu par e-mail <span class="requis">*</span></p>
                <div class="champ-icone" x-ref="natif" hidden><i data-lucide="key-round"></i><input id="code" x-ref="code" wire:model="code" class="champ" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 chiffres" required></div>
                <div class="saisie-code" role="group" aria-label="Code reçu par e-mail, 6 chiffres">
                    <template x-for="(chiffre, i) in chiffres" x-bind:key="i">
                        <input type="text" class="saisie-code__case" inputmode="numeric" maxlength="1" x-bind:autocomplete="i === 0 ? 'one-time-code' : 'off'"
                               x-bind:aria-label="'Chiffre ' + (i + 1) + ' sur 6'" x-bind:value="chiffre" x-bind:class="{ 'est-remplie': chiffre !== '' }"
                               x-on:input="saisir(i, $event)" x-on:keydown.backspace="effacer(i, $event)" x-on:keydown.arrow-left.prevent="aller(i - 1)"
                               x-on:keydown.arrow-right.prevent="aller(i + 1)" x-on:paste.prevent="coller($event)" x-on:focus="$event.target.select()">
                    </template>
                </div>
                <p class="aide">Valable {{ $this->validiteMinutes }} minutes. Collez le code : les cases se remplissent seules.</p>
                @error('code')<p class="erreur-champ">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn btn-primaire btn--lg connexion__bouton" wire:loading.attr="disabled" wire:target="verifier"><i data-lucide="log-in"></i>Se connecter</button>
        </form>

        <div class="connexion__liens" wire:ignore x-data="{ attente: {{ $this->attenteRenvoi }}, minuteur: null, demarrer() { clearInterval(this.minuteur); this.minuteur = setInterval(() => { if (this.attente > 0) { this.attente-- } else { clearInterval(this.minuteur) } }, 1000) } }"
             x-init="demarrer()" x-on:code-envoye.window="attente = $event.detail.attente; demarrer()">
            <span class="connexion__attente" x-show="attente > 0" x-cloak>Renvoyer un code dans <strong class="chiffres" x-text="Math.floor(attente / 60) + ':' + String(attente % 60).padStart(2, '0')"></strong></span>
            <button type="button" class="lien lien--bouton" x-show="attente === 0" x-on:click="$wire.renvoyer()">Renvoyer un code</button>
            <button type="button" class="lien lien--bouton" x-on:click="$wire.changerDeMatricule()">Changer de matricule</button>
        </div>

        <div class="connexion__aide"><i data-lucide="mail"></i><span>Rien reçu ? Vérifiez vos courriers indésirables, ou faites enregistrer votre adresse par votre point focal.</span></div>
    </section>
</div>
@endif
</div>
