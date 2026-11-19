<x-layouts.app titre="Tâches en échec" :ariane="['Administration', 'Tâches en échec']" actif="taches-echouees">
    <div class="titre-page">
        <div>
            <h1>Tâches en échec</h1>
        </div>
        @if ($totalEchecs > 0)
            <div class="titre-page__actions">
                <button type="button" class="btn btn-primaire" data-ouvrir="modale-relancer-tout"><i data-lucide="rotate-ccw"></i>Relancer toutes les tâches</button>
            </div>
        @endif
    </div>

    <div class="grille-tuiles espace-bloc">
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">En échec</span><span class="tuile__icone"><i data-lucide="circle-alert"></i></span></div>
            <div class="tuile__valeur">{{ $totalEchecs }}</div>
        </div>
        <div class="tuile">
            <div class="tuile__haut"><span class="surtitre">En file d’attente</span><span class="tuile__icone"><i data-lucide="list-ordered"></i></span></div>
            <div class="tuile__valeur">{{ $enAttente }}</div>
        </div>
    </div>

    <div class="carte">
        <x-barre-filtres :total="$taches->total()" libelle="tâche(s)">
            <x-filtre libelle="Recherche" pour="filtre-recherche" large>
                <div class="champ-icone"><i data-lucide="search"></i><input id="filtre-recherche" type="search" name="recherche" class="champ" value="{{ $recherche }}" placeholder="Tâche ou erreur"></div>
            </x-filtre>
            <x-filtre libelle="File" pour="filtre-file">
                <x-select-recherche compact soumettre>
                    <select id="filtre-file" name="file" class="champ">
                        <option value="">Toutes les files</option>
                        @foreach ($files as $nomFile)
                            <option value="{{ $nomFile }}" @selected($file === $nomFile)>{{ $nomFile }}</option>
                        @endforeach
                    </select>
                </x-select-recherche>
            </x-filtre>
            <x-filtre libelle="Jour de l’échec" pour="filtre-jour">
                <input id="filtre-jour" type="date" name="jour" class="champ" value="{{ $jour }}">
            </x-filtre>
        </x-barre-filtres>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Échec</th><th>Tâche</th><th>Erreur</th><th class="actions">Actions</th></tr></thead>
                <tbody>
                    @forelse ($taches as $tache)
                        <tr>
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
        {{ $taches->onEachSide(1)->links('pagination.tresor', ['libelle' => 'tâches']) }}
    </div>

    <x-slot:modales>
        @if ($totalEchecs > 0)
            <x-modale-confirmation id="modale-relancer-tout" :titre="'Relancer les '.$totalEchecs.' tâche(s) en échec ?'" :action="route('taches-echouees.relancer-tout')" bouton="Relancer toutes les tâches" icone="rotate-ccw">
                Toutes les tâches repasseront en file d’attente. Corrigez d’abord la cause de l’échec (configuration du serveur de messagerie, espace disque…), sinon elles échoueront à nouveau.
            </x-modale-confirmation>
        @endif
        @foreach ($taches as $tache)
            <x-modale-confirmation :id="'modale-supprimer-'.$tache->uuid" :titre="'Supprimer la tâche '.$tache->tache.' ?'" :action="route('taches-echouees.supprimer', $tache->uuid)" bouton="Supprimer la tâche" icone="trash-2" :danger="true">
                La tâche ne sera jamais relancée. Pour un procès-verbal, il faudra le régénérer ; pour un e-mail, il ne sera pas envoyé.
            </x-modale-confirmation>
        @endforeach
    </x-slot:modales>
</x-layouts.app>
