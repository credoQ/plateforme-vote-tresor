<?php

namespace App\Http\Controllers\Suivi;

use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Scrutin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CSV (séparateur « ; », lisible par Excel) des listes électorales et des résultats publiés (module 08).
 */
class ExportController extends Controller
{
    /**
     * Liste électorale d'un scrutin : comité, observateur, ou point focal limité à sa structure (R4). Sans l'émargement.
     */
    public function listeElectorale(Request $request, Scrutin $scrutin): StreamedResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
        $perimetre = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();

        $lignes = $scrutin->inscriptions()
            ->with(['agent', 'structure'])
            ->when($perimetre !== null, fn ($q) => $q->whereIn('structure_id', $perimetre))
            ->join('agents', 'agents.id', '=', 'inscriptions.agent_id')
            ->orderBy('agents.nom')->orderBy('agents.prenoms')
            ->select('inscriptions.*')
            ->lazy()
            ->map(fn ($inscription): array => [
                $inscription->agent->matricule, $inscription->agent->nom, $inscription->agent->prenoms,
                $inscription->agent->sexe->value, $inscription->structure->code,
            ]);

        return $this->csv("liste-electorale-{$scrutin->code}", ['matricule', 'nom', 'prenoms', 'sexe', 'structure'], $lignes);
    }

    /**
     * Résultats d'un scrutin, uniquement après leur publication (R46, R54).
     */
    public function resultats(Scrutin $scrutin): StreamedResponse
    {
        abort_unless($scrutin->statut === StatutScrutin::ResultatsPublies, 404);

        $lignes = $scrutin->candidates()->with('agent')->orderByRaw('rang nulls last')->get()
            ->map(fn ($candidate): array => [
                $candidate->rang ?? '', $candidate->agent->matricule, $candidate->agent->nomComplet(),
                $candidate->nb_voix ?? '', $candidate->pourcentage ?? '', $candidate->est_laureate ? 'oui' : 'non',
                $candidate->statut->libelle(),
            ]);

        return $this->csv("resultats-{$scrutin->code}", ['rang', 'matricule', 'candidate', 'voix', 'pourcentage', 'laureate', 'statut'], $lignes);
    }

    /**
     * @param  list<string>  $entete
     * @param  iterable<int, list<string|int|float>>  $lignes
     */
    private function csv(string $nom, array $entete, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entete, $lignes): void {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entete, ';', '"', '');
            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map('strval', $ligne), ';', '"', '');
            }
            fclose($sortie);
        }, Str::slug($nom).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
