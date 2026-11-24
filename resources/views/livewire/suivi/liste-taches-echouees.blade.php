{{-- Tâches en échec : recherche, file, jour et pagination sans recharger la page. Les fenêtres de suppression
     suivent les lignes affichées. --}}
<div>
    <div class="barre-filtres">
        <x-filtre libelle="Recherche" pour="filtre-recherche" large>
            <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" class="champ" wire:model.live.debounce.400ms="recherche" placeholder="Tâche ou erreur"></div>
        </x-filtre>
        <x-filtre libelle="File" pour="filtre-file">
            <x-select-recherche compact wire:ignore>
                <select id="filtre-file" class="champ" wire:model.live="file">
                    <option value="">Toutes les files</option>
                    @foreach ($this->files as $nomFile)
                        <option value="{{ $nomFile }}" @selected($file === $nomFile)>{{ $nomFile }}</option>
                    @endforeach
                </select>
            </x-select-recherche>
        </x-filtre>
        <x-filtre libelle="Jour de l’échec" pour="filtre-jour">
            <input id="filtre-jour" type="date" class="champ" wire:model.live="jour">
        </x-filtre>
        <x-fin-filtres :total="$this->taches->total()" libelle="tâche(s)" :actifs="$filtresActifs" :reinitialiser="route('taches-echouees.index')" />
    </div>
    <div class="tableau-defilant" wire:loading.class="est-en-chargement">
        <table class="tableau tableau--fiches">
            <thead><tr><th>Échec</th><th>Tâche</th><th>Erreur</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                @forelse ($this->taches as $tache)
                    <tr wire:key="tache-{{ $tache->uuid }}">
                        <td>{{ $tache->echec->timezone('Africa/Porto-Novo')->format('d/m/Y H:i') }}<div class="aide">file {{ $tache->file }}</div></td>
                        <td>{{ $tache->tache }}<div class="aide">{{ \Illuminate\Support\Str::limit($tache->uuid, 13, '') }}</div></td>
                        <td class="details-audit">
                            {{ $tache->erreur }}
                            <details><summary class="lien">Trace complète</summary><pre class="details-audit">{{ $tache->trace }}</pre></details>
                        </td>
                        <td class="actions">
                            <form method="POST" action="{{ route('taches-echouees.relancer', $tache->uuid) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondaire btn--sm"><i data-lucide="rotate-ccw"></i>Relancer</button>
                            </form>
                            <button type="button" class="btn btn-discret btn--sm" data-ouvrir="modale-supprimer-{{ $tache->uuid }}"><i data-lucide="trash-2"></i>Supprimer</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="etat-vide"><i data-lucide="circle-check"></i><p>Aucune tâche en échec.</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->taches->onEachSide(1)->links('pagination.tresor-livewire', ['libelle' => 'tâches']) }}
    <div hidden>
        @foreach ($this->taches as $tache)
            <x-modale-confirmation :id="'modale-supprimer-'.$tache->uuid" :titre="'Supprimer la tâche '.$tache->tache.' ?'" :action="route('taches-echouees.supprimer', $tache->uuid)" bouton="Supprimer la tâche" icone="trash-2" :danger="true">
                La tâche ne sera jamais relancée. Pour un procès-verbal, il faudra le régénérer ; pour un e-mail, il ne sera pas envoyé.
            </x-modale-confirmation>
        @endforeach
    </div>
</div>
