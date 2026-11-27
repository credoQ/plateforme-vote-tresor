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

    <section class="carte espace-bloc">
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
            <button type="button" class="btn btn-secondaire" wire:click="preparerArchive" wire:loading.attr="disabled" wire:target="preparerArchive" @disabled(($this->archive['etat'] ?? null) === 'en_cours')><i data-lucide="folder-archive"></i>Un fichier par personne (ZIP par structure)</button>
        </div>
        @if ($this->archive)
            <div class="carte__corps">
                @if ($this->archive['etat'] === 'en_cours')
                    <div class="alerte alerte--info" wire:poll.3s><i data-lucide="loader-circle"></i><div><strong>Préparation de l’archive{{ $this->archive['selection'] ? ' de la sélection' : '' }} :</strong> <span class="chiffres">{{ $this->archive['fait'] }} / {{ $this->archive['total'] }}</span> fichier(s). Vous pouvez quitter cette page et revenir plus tard.</div></div>
                @elseif ($this->archive['etat'] === 'prete')
                    <div class="alerte alerte--succes"><i data-lucide="circle-check"></i><div><strong>Archive prête :</strong> {{ $this->archive['total'] }} coupon(s), un dossier par structure. Elle est supprimée du serveur dès son téléchargement.
                        <div class="mt-2"><a href="{{ route('codes-acces.archive') }}" class="btn btn-secondaire btn--sm" x-on:click="setTimeout(() => $wire.$refresh(), 2000)"><i data-lucide="download"></i>Télécharger l’archive</a></div></div></div>
                @else
                    <div class="alerte alerte--erreur"><i data-lucide="circle-alert"></i><div><strong>La préparation de l’archive a échoué.</strong> Relancez-la ; si l’échec se répète, consultez l’écran « Tâches en échec ».</div></div>
                @endif
            </div>
        @endif
        <div class="carte__corps pile-aide">
            <p class="aide">L’archive ZIP range un PDF par personne dans un dossier par structure. Elle se prépare en arrière-plan : comptez quelques minutes pour plusieurs centaines de comptes.</p>
            <p class="aide">Les coupons sont confidentiels : imprimez-les, découpez-les et remettez-les sous pli à chaque personne. Chaque impression est inscrite au journal d’audit.</p>
        </div>
    </section>

    <section class="carte">
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
            <x-filtre libelle="Structure" pour="filtre-structure">
                <x-select-recherche compact wire:ignore>
                    <select id="filtre-structure" class="champ" wire:model.live="structure">
                        <option value="">Toutes les structures</option>
                        @foreach ($this->structures as $choix)
                            <option value="{{ $choix->id }}" data-detail="{{ $choix->libelle }}" @selected($structure === (string) $choix->id)>{{ $choix->code }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-fin-filtres :total="$this->comptes->total()" libelle="compte(s)" :actifs="$filtresActifs" :reinitialiser="route('codes-acces.index')" />
        </div>

        {{-- Actions sur les comptes cochés (la sélection est conservée d'une page à l'autre). --}}
        <div class="barre-selection" @if ($selection === []) hidden @endif>
            <span><strong class="chiffres">{{ count($selection) }}</strong> compte(s) sélectionné(s)</span>
            @if (count($selection) < $this->comptes->total())
                <button type="button" class="lien lien--bouton" wire:click="selectionnerTout">Sélectionner les {{ $this->comptes->total() }} comptes filtrés</button>
            @endif
            <div class="barre-selection__actions">
                <button type="button" class="btn btn-secondaire btn--sm" wire:loading.attr="disabled" wire:target="genererSelection"
                        x-on:click="window.confirmer({ titre: 'Générer les codes des comptes sélectionnés ?', texte: 'Seuls les comptes sans code en reçoivent un ; les codes existants ne changent pas.', bouton: 'Générer les codes' }).then((ok) => ok && $wire.genererSelection())">
                    <i data-lucide="wand-sparkles"></i>Générer leurs codes
                </button>
                <button type="button" class="btn btn-primaire btn--sm" wire:click="imprimerSelection" wire:loading.attr="disabled" wire:target="imprimerSelection"><i data-lucide="printer"></i>Imprimer leurs coupons</button>
                <button type="button" class="btn btn-secondaire btn--sm" wire:click="preparerArchive(true)" wire:loading.attr="disabled" wire:target="preparerArchive"><i data-lucide="folder-archive"></i>Un fichier par personne (ZIP)</button>
                <button type="button" class="btn btn-tertiaire btn--sm" wire:click="viderSelection"><i data-lucide="x"></i>Vider la sélection</button>
            </div>
        </div>

        <div class="tableau-defilant" wire:loading.class="est-en-chargement">
            <table class="tableau tableau--fiches">
                <thead><tr>
                    <th class="colonne-case"><input type="checkbox" class="case-tableau" aria-label="Cocher les comptes de la page" @checked($pageCochee) wire:click="basculerPage"></th>
                    <th>Compte</th><th>Rôles</th><th>Code</th><th class="actions">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->comptes as $compte)
                        <tr wire:key="compte-{{ $compte->id }}">
                            <td class="colonne-case"><input type="checkbox" class="case-tableau" value="{{ $compte->id }}" wire:model.live="selection" aria-label="Cocher {{ $compte->name }}"></td>
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
                        <tr><td colspan="5"><div class="etat-vide"><i data-lucide="users"></i><p>Aucun compte ne correspond.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->comptes->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'comptes']) }}
    </section>
</div>
