@php
    $activee = $utilisateur->hasEnabledTwoFactorAuthentication();
    $enCours = ! $activee && $utilisateur->two_factor_secret !== null;
@endphp
<x-layouts.app titre="Double authentification" :ariane="['Mon espace', 'Sécurité du compte', 'Double authentification']" actif="securite">
    <div class="titre-page">
        <div>
            <div class="surtitre">Sécurité du compte</div>
            <h1>Double authentification</h1>
            <p>Un code à 6 chiffres, généré par une application d’authentification sur votre téléphone, est demandé après le mot de passe.</p>
        </div>
        <div class="titre-page__actions"><a href="{{ route('securite') }}" class="btn btn-tertiaire"><i data-lucide="arrow-left"></i>Retour</a></div>
    </div>

    @error('code', 'confirmTwoFactorAuthentication')
        <div class="alerte alerte--erreur espace-bloc" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
    @enderror

    <section class="carte">
        <div class="carte__corps">
            @if ($activee)
                <div class="alerte alerte--succes espace-bloc"><i data-lucide="shield-check"></i><div><strong>Double authentification activée.</strong> Conservez vos codes de secours en lieu sûr : chacun permet une connexion si vous perdez votre téléphone.</div></div>
                <div class="codes-secours espace-bloc">
                    @foreach ($utilisateur->recoveryCodes() as $code)<span>{{ $code }}</span>@endforeach
                </div>
                <div class="formulaire__actions">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<button type="submit" class="btn btn-secondaire"><i data-lucide="refresh-cw"></i>Générer de nouveaux codes de secours</button></form>
                    <button type="button" class="btn btn-danger-contour" data-ouvrir="modale-desactiver-2fa"><i data-lucide="shield-off"></i>Désactiver la double authentification</button>
                </div>
            @elseif ($enCours)
                <p><strong>1.</strong> Scannez ce code avec votre application d’authentification, ou saisissez la clé.</p>
                <div class="qr-code espace-bloc">{!! $utilisateur->twoFactorQrCodeSvg() !!}</div>
                <p class="aide espace-bloc">Clé : <span class="secret">{{ decrypt($utilisateur->two_factor_secret) }}</span></p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="formulaire" novalidate>
                    @csrf
                    <div class="formulaire__groupe">
                        <label class="libelle" for="code"><strong>2.</strong> Code affiché par l’application <span class="requis">*</span></label>
                        <input id="code" name="code" class="champ" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
                    </div>
                    <div class="formulaire__actions">
                        <button type="submit" class="btn btn-primaire"><i data-lucide="check"></i>Confirmer l’activation</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-tertiaire">Abandonner</button></form>
            @else
                <p class="espace-bloc">La double authentification n’est pas activée sur votre compte.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="btn btn-primaire"><i data-lucide="shield-check"></i>Activer la double authentification</button>
                </form>
            @endif
        </div>
    </section>

    <x-slot:modales>
        @if ($activee)
            <div class="voile" id="modale-desactiver-2fa" hidden>
                <div class="modale" role="dialog" aria-modal="true" aria-labelledby="titre-desactiver-2fa">
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <div class="modale__entete"><span class="pastille-icone pastille-icone--danger"><i data-lucide="shield-off"></i></span><div><h2 id="titre-desactiver-2fa">Désactiver la double authentification ?</h2></div></div>
                        <div class="modale__corps"><p>Seul le mot de passe sera demandé à la connexion. Vos codes de secours ne seront plus valables.</p></div>
                        <div class="modale__pied">
                            <button type="button" class="btn btn-secondaire" data-fermer>Garder la double authentification</button>
                            <button type="submit" class="btn btn-danger"><i data-lucide="shield-off"></i>Désactiver la double authentification</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-slot:modales>
</x-layouts.app>
