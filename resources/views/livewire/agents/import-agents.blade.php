{{-- Fenêtre d'import des agents : dépôt du fichier, import en file d'attente, progression puis bilan.
     Ouverte par l'événement « ouvrir-import-agents » (bouton de la liste des agents) ; ouverte d'office si un import est en cours. --}}
<div x-on:ouvrir-import-agents.window="$wire.ouvert = true">
    <div class="voile" x-show="$wire.ouvert" x-cloak x-on:keydown.escape.window="$wire.ouvert = false" x-on:click.self="$wire.ouvert = false">
        <div @class(['modale', 'modale--import']) role="dialog" aria-modal="true" aria-labelledby="titre-import-agents">
            <div class="modale__entete">
                <span class="pastille-icone"><i data-lucide="upload"></i></span>
                <div>
                    <h2 id="titre-import-agents">Importer des agents</h2>
                    <p>Chaque agent est créé ou mis à jour d’après son matricule, reçoit son compte électeur et est affecté à sa structure.</p>
                </div>
                <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="$wire.ouvert = false"><i data-lucide="x"></i></button>
            </div>

            @if (($this->import['etat'] ?? null) === null)
                <form wire:submit="importer" novalidate>
                    <div class="modale__corps formulaire">
                        <div class="depot-fichier" x-data="{ survole: false }" x-bind:class="{ 'est-survole': survole }"
                             x-on:dragover.prevent="survole = true" x-on:dragleave="survole = false"
                             x-on:drop.prevent="survole = false; $refs.fichier.files = $event.dataTransfer.files; $refs.fichier.dispatchEvent(new Event('change'))">
                            <i data-lucide="file-spreadsheet"></i>
                            @if ($fichier)
                                <strong>{{ $fichier->getClientOriginalName() }}</strong>
                            @else
                                <strong>Déposez le fichier ici</strong>
                            @endif
                            <button type="button" class="btn btn-secondaire btn--sm" x-on:click="$refs.fichier.click()"><i data-lucide="folder-open"></i>{{ $fichier ? 'Choisir un autre fichier' : 'Choisir un fichier' }}</button>
                            <input id="fichier-agents" type="file" class="sr-only" x-ref="fichier" wire:model="fichier" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-label="Choisir le fichier des agents">
                            <span class="aide" wire:loading wire:target="fichier">Envoi du fichier…</span>
                            <span class="aide" wire:loading.remove wire:target="fichier">Classeur Excel (.xlsx) ou CSV, 10 Mo au plus, avec une ligne d’en-tête.</span>
                        </div>
                        @error('fichier')<p class="erreur-champ">{{ $message }}</p>@enderror

                        <div>
                            <p class="libelle">Colonnes attendues</p>
                            <ul class="colonnes-import">
                                @foreach (['Matricule', 'Nom', 'Prénom', 'Sexe (F ou M)', 'Structure (code)'] as $colonne)
                                    <li>{{ $colonne }}</li>
                                @endforeach
                                @foreach (['E-mail', 'NPI', 'Date de naissance', 'Date de début'] as $colonne)
                                    <li class="est-facultative">{{ $colonne }}</li>
                                @endforeach
                            </ul>
                            <p class="aide">Les cinq premières sont obligatoires, les autres facultatives. Une ligne incomplète est rejetée sans bloquer les autres.</p>
                        </div>

                        <div class="formulaire__groupe">
                            <label class="libelle" for="date-effet-import">Début des affectations sans date</label>
                            <input id="date-effet-import" type="date" class="champ" wire:model="dateEffet">
                            <p class="aide">Pour les lignes sans date de début ; aujourd’hui si vide.</p>
                            @error('dateEffet')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="modale__pied">
                        <button type="button" class="btn btn-secondaire" x-on:click="$wire.ouvert = false">Annuler</button>
                        <button type="submit" class="btn btn-primaire" wire:loading.attr="disabled" wire:target="importer,fichier"><i data-lucide="upload"></i>Importer</button>
                    </div>
                </form>
            @else
                <div class="modale__corps">
                    <p class="aide espace-bloc">Fichier : <strong>{{ $this->import['fichier'] }}</strong></p>

                    @if ($this->import['etat'] === 'en_cours')
                        <div class="alerte alerte--info" wire:poll.3s="suivre"><span class="roue alerte__roue" aria-hidden="true"></span><div><strong>Import en cours :</strong> <span class="chiffres">{{ $this->import['lues'] }}</span> ligne(s) lue(s). Vous pouvez fermer cette fenêtre : l’import continue.</div></div>
                    @elseif ($this->import['etat'] === 'echec')
                        <div class="alerte alerte--erreur"><i data-lucide="circle-alert"></i><div><strong>Import impossible.</strong> {{ $this->import['erreur'] }}</div></div>
                    @else
                        <div class="grille-tuiles grille-tuiles--trois espace-bloc">
                            <div class="tuile">
                                <div class="tuile__haut"><span class="surtitre">Agents créés</span><span class="tuile__icone"><i data-lucide="user-plus"></i></span></div>
                                <div class="tuile__valeur">{{ number_format($this->import['crees'], 0, ',', ' ') }}</div>
                            </div>
                            <div class="tuile">
                                <div class="tuile__haut"><span class="surtitre">Mis à jour</span><span class="tuile__icone"><i data-lucide="user-check"></i></span></div>
                                <div class="tuile__valeur">{{ number_format($this->import['mis_a_jour'], 0, ',', ' ') }}</div>
                            </div>
                            <div class="tuile">
                                <div class="tuile__haut"><span class="surtitre">Rejetées</span><span class="tuile__icone"><i data-lucide="circle-alert"></i></span></div>
                                <div class="tuile__valeur">{{ number_format($this->import['nombre_rejets'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                        <p class="aide">{{ $this->import['affectations'] }} nouvelle(s) affectation(s). Les comptes électeurs des nouveaux agents sont ouverts : générez leurs codes d’accès si la connexion se fait par code imprimé.</p>
                    @endif
                </div>

                @if (($this->import['rejets'] ?? []) !== [])
                    <div class="tableau-defilant modale__tableau">
                        <table class="tableau">
                            <thead><tr><th class="nombre">Ligne</th><th>Motif du rejet</th></tr></thead>
                            <tbody>
                                @foreach ($this->import['rejets'] as $ligne => $motifs)
                                    <tr wire:key="rejet-{{ $ligne }}"><td class="nombre chiffres">{{ $ligne }}</td><td>{{ implode(' ', $motifs) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($this->import['nombre_rejets'] > count($this->import['rejets']))
                        <p class="aide modale__corps">Seules les {{ count($this->import['rejets']) }} premières lignes rejetées sont affichées.</p>
                    @endif
                @endif

                <div class="modale__pied">
                    @if ($this->import['etat'] !== 'en_cours')
                        <button type="button" class="btn btn-secondaire modale__pied-gauche" wire:click="nouvelImport"><i data-lucide="upload"></i>Importer un autre fichier</button>
                    @endif
                    <button type="button" class="btn btn-primaire" x-on:click="$wire.ouvert = false">Fermer</button>
                </div>
            @endif
        </div>
    </div>
</div>
