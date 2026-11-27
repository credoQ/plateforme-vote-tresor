{{-- Codes d'accès du mode « code imprimé » : les codes ne s'affichent jamais ici, seulement sur les coupons PDF. --}}
<div>
    @if ($codeImprime)
        <div class="alerte alerte--info espace-bloc"><i data-lucide="key-round"></i><div><strong>Connexion par code imprimé active.</strong> Chaque personne se connecte avec son matricule et le code de son coupon ; la plateforme n’envoie aucun e-mail.</div></div>
    @else
        <div class="alerte alerte--attente espace-bloc"><i data-lucide="mail"></i><div><strong>Connexion actuelle : code envoyé par e-mail.</strong> Les codes préparés ici ne serviront qu’une fois le mode « code imprimé » activé sur le serveur (CONNEXION_MODE=code_imprime).</div></div>
    @endif

    <div class="grille-tuiles grille-tuiles--trois espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Comptes actifs</span><span class="tuile__icone"><i data-lucide="users"></i></span></div>
            <div class="tuile__valeur">{{ number_format($this->bilan['comptes'], 0, ',', ' ') }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Avec un code</span><span class="tuile__icone"><i data-lucide="key-round"></i></span></div>
            <div class="tuile__valeur">{{ number_format($this->bilan['avecCode'], 0, ',', ' ') }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">Sans code</span><span class="tuile__icone"><i data-lucide="circle-alert"></i></span></div>
            <div class="tuile__valeur">{{ number_format($this->bilan['sansCode'], 0, ',', ' ') }}</div>
        </div>
    </div>

    <section class="carte">
        <div class="carte__entete">
            <h2>Coupons à imprimer</h2>
        </div>
        <div class="carte__corps formulaire__actions">
            <button type="button" class="btn btn-primaire" wire:loading.attr="disabled" wire:target="genererManquants"
                    x-on:click="window.confirmer({ titre: 'Générer les codes manquants ?', texte: @js($this->bilan['sansCode'].' compte(s) actif(s) recevront un code personnel. Les codes existants ne changent pas.'), bouton: 'Générer les codes' }).then((ok) => ok && $wire.genererManquants())">
                <i data-lucide="wand-sparkles"></i>Générer les codes manquants
            </button>
            <a href="{{ route('codes-acces.lot', 'electeurs') }}" class="btn btn-secondaire"><i data-lucide="file-down"></i>Coupons des électeurs (par structure)</a>
            <a href="{{ route('codes-acces.lot', 'autres') }}" class="btn btn-secondaire"><i data-lucide="file-down"></i>Coupons des comptes de gestion</a>
        </div>
        <p class="aide carte__corps">Les coupons sont confidentiels : imprimez-les, découpez-les et remettez-les sous pli à chaque personne. Chaque impression est inscrite au journal d’audit.</p>
    </section>

    <section class="carte espace-bloc">
        <div class="barre-filtres">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Nom ou matricule"></div>
            </x-filtre>
            <x-filtre libelle="Code" pour="filtre-etat">
                <x-select-recherche compact wire:ignore>
                    <select id="filtre-etat" class="champ" wire:model.live="etat">
                        <option value="">Tous les comptes</option>
                        <option value="avec" @selected($etat === 'avec')>Avec un code</option>
                        <option value="sans" @selected($etat === 'sans')>Sans code</option>
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-fin-filtres :total="$this->comptes->total()" libelle="compte(s)" :actifs="trim($recherche) !== '' || $etat !== ''" :reinitialiser="route('codes-acces.index')" />
        </div>

        <div class="tableau-defilant" wire:loading.class="est-en-chargement">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Compte</th><th>Rôles</th><th>Code</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($this->comptes as $compte)
                        <tr wire:key="compte-{{ $compte->id }}">
                            <td>{{ $compte->name }}<div class="aide">{{ $compte->login }}</div></td>
                            <td>{{ $compte->roles->map(fn ($role) => $role->code->libelle())->implode(', ') }}</td>
                            <td>
                                @if ($compte->code_acces_genere_le)
                                    <span class="badge badge--succes">Généré</span>
                                    <div class="aide">le {{ $compte->code_acces_genere_le->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') }}</div>
                                @else
                                    <span class="badge">Aucun code</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($compte->code_acces_genere_le)
                                    <a href="{{ route('codes-acces.coupon', $compte) }}" class="btn btn-tertiaire btn--sm"><i data-lucide="printer"></i>Coupon</a>
                                @endif
                                <button type="button" class="btn btn-tertiaire btn--sm" wire:loading.attr="disabled"
                                        x-on:click="window.confirmer({ titre: @js(($compte->code_acces_genere_le ? 'Régénérer le code de ' : 'Créer un code pour ').$compte->name.' ?'), texte: @js($compte->code_acces_genere_le ? 'L’ancien code cessera aussitôt de fonctionner. Il faudra remettre le nouveau coupon.' : 'Un code personnel sera créé pour ce compte.'), bouton: @js($compte->code_acces_genere_le ? 'Régénérer le code' : 'Créer le code'), danger: @js((bool) $compte->code_acces_genere_le) }).then((ok) => ok && $wire.regenerer({{ $compte->id }}))">
                                    <i data-lucide="rotate-ccw"></i>{{ $compte->code_acces_genere_le ? 'Régénérer' : 'Créer un code' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="etat-vide"><i data-lucide="users"></i><p>Aucun compte ne correspond.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->comptes->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'comptes']) }}
    </section>
</div>
