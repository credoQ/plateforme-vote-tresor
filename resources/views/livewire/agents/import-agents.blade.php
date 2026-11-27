{{-- Import des agents par fichier : dépôt, import en file d'attente, puis bilan (lignes rejetées avec leurs motifs). --}}
<div>
    <section class="carte">
        <div class="carte__entete"><h2>Fichier des agents</h2></div>
        <form wire:submit="importer" class="carte__corps formulaire" novalidate>
            <p class="aide">
                Classeur Excel (.xlsx, première feuille) ou fichier CSV, avec une ligne d’en-tête. Colonnes obligatoires :
                <strong>matricule</strong>, <strong>nom</strong>, <strong>prénom</strong> (ou prénoms), <strong>sexe</strong> (F ou M) et
                <strong>structure</strong> (code du référentiel : DIA, DR…). Colonnes facultatives : e-mail, NPI, date de naissance, date de début d’affectation.
            </p>
            <p class="aide">Chaque agent est créé ou mis à jour d’après son matricule, reçoit son compte électeur (identifiant = matricule) et est affecté à sa structure. Une ligne incomplète est rejetée sans bloquer les autres.</p>
            <div class="formulaire formulaire--deux-colonnes">
                <div class="formulaire__groupe">
                    <label class="libelle" for="fichier">Fichier <span class="requis">*</span></label>
                    <input id="fichier" type="file" class="champ" wire:model="fichier" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                    <p class="aide" wire:loading wire:target="fichier">Envoi du fichier…</p>
                    @error('fichier')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
                <div class="formulaire__groupe">
                    <label class="libelle" for="dateEffet">Début des affectations sans date</label>
                    <input id="dateEffet" type="date" class="champ" wire:model="dateEffet">
                    <p class="aide">Pour les lignes sans date de début ; aujourd’hui si vide.</p>
                    @error('dateEffet')<p class="erreur-champ">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="formulaire__actions">
                <button type="submit" class="btn btn-primaire" wire:loading.attr="disabled" wire:target="importer,fichier" @disabled(($this->import['etat'] ?? null) === 'en_cours')>
                    <i data-lucide="upload"></i>Importer
                </button>
            </div>
        </form>
    </section>

    @if ($this->import)
        <section class="carte espace-bloc">
            <div class="carte__entete"><h2>Dernier import · {{ $this->import['fichier'] }}</h2></div>
            <div class="carte__corps">
                @if ($this->import['etat'] === 'en_cours')
                    <div class="alerte alerte--info" wire:poll.3s><i data-lucide="loader-circle"></i><div><strong>Import en cours :</strong> <span class="chiffres">{{ $this->import['lues'] }}</span> ligne(s) lue(s). Vous pouvez quitter cette page et revenir plus tard.</div></div>
                @elseif ($this->import['etat'] === 'echec')
                    <div class="alerte alerte--erreur"><i data-lucide="circle-alert"></i><div><strong>Import impossible.</strong> {{ $this->import['erreur'] }}</div></div>
                @else
                    <div class="grille-tuiles grille-tuiles--trois espace-bloc">
                        <div class="tuile">
                            <div class="tuile__haut"><span class="surtitre">Agents créés</span><span class="tuile__icone"><i data-lucide="user-plus"></i></span></div>
                            <div class="tuile__valeur">{{ number_format($this->import['crees'], 0, ',', ' ') }}</div>
                        </div>
                        <div class="tuile">
                            <div class="tuile__haut"><span class="surtitre">Agents mis à jour</span><span class="tuile__icone"><i data-lucide="user-check"></i></span></div>
                            <div class="tuile__valeur">{{ number_format($this->import['mis_a_jour'], 0, ',', ' ') }}</div>
                        </div>
                        <div class="tuile">
                            <div class="tuile__haut"><span class="surtitre">Lignes rejetées</span><span class="tuile__icone"><i data-lucide="circle-alert"></i></span></div>
                            <div class="tuile__valeur">{{ number_format($this->import['nombre_rejets'], 0, ',', ' ') }}</div>
                        </div>
                    </div>
                    <p class="aide">{{ $this->import['affectations'] }} nouvelle(s) affectation(s). Les comptes électeurs des nouveaux agents sont ouverts : générez leurs codes d’accès si la connexion se fait par code imprimé.</p>
                @endif
            </div>

            @if (($this->import['rejets'] ?? []) !== [])
                <div class="tableau-defilant">
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
                    <p class="aide carte__corps">Seules les {{ count($this->import['rejets']) }} premières lignes rejetées sont affichées.</p>
                @endif
            @endif

            @if ($this->import['etat'] !== 'en_cours')
                <div class="carte__corps formulaire__actions">
                    <button type="button" class="btn btn-tertiaire" wire:click="fermerBilan"><i data-lucide="x"></i>Effacer ce bilan</button>
                </div>
            @endif
        </section>
    @endif
</div>
