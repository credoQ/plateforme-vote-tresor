<?php

namespace App\Livewire\Electeur;

use App\Actions\Scrutins\DesactiverCandidate;
use App\Actions\Scrutins\ReactiverCandidate;
use App\Enums\Editions\StatutEdition;
use App\Enums\Editions\StatutPhase;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Candidate;
use App\Models\Edition;
use App\Models\Inscription;
use App\Models\Scrutin;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Accueil de l'espace électeur : son scrutin de la phase ouverte, ses candidatures, sa qualification et les
 * résultats publiés. L'état du scrutin se rafraîchit seul pendant une phase ouverte ; le retrait et la
 * réactivation d'une candidature (R32 à R34) se font sans recharger la page. Aucun choix de vote n'y transite.
 */
class Accueil extends Component
{
    /**
     * Seul un utilisateur connecté y accède, à chaque requête du composant.
     */
    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function desister(int $candidateId, ?string $motif = null): void
    {
        $candidate = Candidate::findOrFail($candidateId);
        Gate::authorize('desactiver', $candidate);
        $motif = trim((string) $motif);

        if (mb_strlen($motif) > 500) {
            $this->dispatch('notifier', message: 'Le motif ne dépasse pas 500 caractères.', ton: 'erreur');

            return;
        }

        $this->executer(fn () => app(DesactiverCandidate::class)->handle($candidate, $this->utilisateur(), $motif !== '' ? $motif : null),
            'Votre candidature est retirée. Vous restez électrice.');
    }

    public function reactiver(int $candidateId): void
    {
        $candidate = Candidate::findOrFail($candidateId);
        Gate::authorize('reactiver', $candidate);

        $this->executer(fn () => app(ReactiverCandidate::class)->handle($candidate), 'Votre candidature est réactivée.');
    }

    #[Computed]
    public function inscription(): ?Inscription
    {
        $agentId = $this->utilisateur()->agent_id;

        return $agentId === null ? null : Inscription::query()
            ->with('scrutin.phase.edition')
            ->where('agent_id', $agentId)
            ->whereHas('scrutin.phase', fn ($phase) => $phase->where('statut', StatutPhase::Ouverte->value))
            ->first();
    }

    /**
     * Candidatures de l'agent qui acceptent encore un désistement ou une réactivation (R32 à R34).
     *
     * @return Collection<int, Candidate>
     */
    #[Computed]
    public function candidatures(): Collection
    {
        $agentId = $this->utilisateur()->agent_id;

        return $agentId === null ? new Collection : Candidate::query()
            ->with('scrutin.phase')
            ->where('agent_id', $agentId)
            ->whereHas('scrutin', fn ($scrutin) => $scrutin
                ->whereIn('statut', [StatutScrutin::EnPreparation->value, StatutScrutin::ListesArretees->value])
                ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value)))
            ->get();
    }

    public function render(): View
    {
        $utilisateur = $this->utilisateur()->loadMissing('agent');
        $scrutin = $this->inscription?->scrutin;

        return view('livewire.electeur.accueil', [
            'prenom' => $utilisateur->agent ? Str::of($utilisateur->agent->prenoms)->before(' ')->toString() : $utilisateur->name,
            'affectation' => $utilisateur->agent?->affectationLe(),
            'qualification' => $utilisateur->agent_id === null ? null : $this->qualification($utilisateur->agent_id),
            'femmeModele' => $this->femmeModele($utilisateur->agent_id),
            'inscription' => $this->inscription,
            'scrutin' => $scrutin,
            'peutVoter' => $scrutin !== null && ! $this->inscription->a_vote && $scrutin->statut === StatutScrutin::Ouvert && $utilisateur->can('voter'),
            'nbCandidates' => $scrutin?->statut === StatutScrutin::Ouvert ? $scrutin->candidates()->actives()->count() : null,
            'resultatsPublies' => $this->resultatsPublies(),
            'candidatures' => $this->candidatures,
            'echeance' => $this->prochaineEcheance($scrutin),
        ]);
    }

    /**
     * Prochain changement prévu de l'état du scrutin (ouverture, puis fermeture) tant que l'électeur n'a pas voté :
     * la page se rafraîchit une seule fois à ce moment-là, au lieu d'interroger le serveur chaque minute. Le délai est
     * calculé par le serveur (l'horloge du téléphone peut être fausse) ; null s'il n'y a rien à attendre.
     *
     * @return array{horodatage: int, secondes: int}|null
     */
    private function prochaineEcheance(?Scrutin $scrutin): ?array
    {
        if ($scrutin === null || $this->inscription->a_vote) {
            return null;
        }

        $echeance = collect([$scrutin->date_heure_ouverture, $scrutin->date_heure_fermeture])
            ->filter(fn ($date): bool => $date !== null && $date->isFuture())
            ->min();

        return $echeance === null ? null : ['horodatage' => $echeance->getTimestamp(), 'secondes' => (int) now()->diffInSeconds($echeance)];
    }

    private function utilisateur(): User
    {
        /** @var User */
        return Auth::user();
    }

    /**
     * Exécute une action sur une candidature, puis annonce le résultat (ou le refus) par un message passager.
     */
    private function executer(callable $action, string $succes): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->dispatch('notifier', message: collect($exception->errors())->flatten()->first(), ton: 'erreur');

            return;
        }

        unset($this->candidatures);
        $this->dispatch('notifier', message: $succes, ton: 'succes');
    }

    /**
     * Annonces de la plateforme (module 08) : résultats publiés de l'édition en cours, avec leur PDF.
     *
     * @return Collection<int, Scrutin>
     */
    private function resultatsPublies(): Collection
    {
        return Scrutin::query()
            ->with(['phase', 'procesVerbal'])
            ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value))
            ->where('statut', StatutScrutin::ResultatsPublies->value)
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();
    }

    /**
     * Titre de femme modèle du Trésor de l'agent dans l'édition en cours (R53) : année et scrutin, sinon null.
     *
     * @return array{annee: int, scrutin: string}|null
     */
    private function femmeModele(?int $agentId): ?array
    {
        $edition = $agentId === null ? null : Edition::enCours()->first();
        $elue = $edition?->femmesModeles()->firstWhere('agent_id', $agentId);

        return $elue === null ? null : ['annee' => $edition->annee, 'scrutin' => $elue->scrutin->libelle];
    }

    /**
     * Dernière qualification de l'agent dans l'édition en cours (R35, R52), tant que la phase pour laquelle
     * elle est qualifiée n'est pas clôturée : la plateforme l'affiche aussi à celles qui n'ont pas d'adresse e-mail.
     *
     * @return array{scrutin: string, rang: int, phase: string}|null
     */
    private function qualification(int $agentId): ?array
    {
        $laureate = Candidate::query()
            ->with('scrutin.phase')
            ->where('agent_id', $agentId)
            ->where('est_laureate', true)
            ->actives()
            ->whereHas('scrutin', fn ($scrutin) => $scrutin
                ->where('statut', StatutScrutin::ResultatsPublies->value)
                ->whereHas('phase.edition', fn ($edition) => $edition->where('statut', StatutEdition::EnCours->value)))
            ->get()
            ->sortByDesc(fn (Candidate $candidate): int => $candidate->scrutin->phase->numero)
            ->first();

        $phaseSuivante = $laureate?->scrutin->phase->phaseSuivante();

        if ($phaseSuivante === null || $phaseSuivante->statut === StatutPhase::Cloturee) {
            return null;
        }

        return ['scrutin' => $laureate->scrutin->libelle, 'rang' => $laureate->rang, 'phase' => $phaseSuivante->libelle];
    }
}
