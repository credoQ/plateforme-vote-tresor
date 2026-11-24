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
        <livewire:suivi.liste-taches-echouees />
    </div>

    <x-slot:modales>
        @if ($totalEchecs > 0)
            <x-modale-confirmation id="modale-relancer-tout" :titre="'Relancer les '.$totalEchecs.' tâche(s) en échec ?'" :action="route('taches-echouees.relancer-tout')" bouton="Relancer toutes les tâches" icone="rotate-ccw">
                Toutes les tâches repasseront en file d’attente. Corrigez d’abord la cause de l’échec (configuration du serveur de messagerie, espace disque…), sinon elles échoueront à nouveau.
            </x-modale-confirmation>
        @endif
    </x-slot:modales>
</x-layouts.app>
