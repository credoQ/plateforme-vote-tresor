<?php

namespace App\Http\Controllers\Suivi;

use App\Enums\Acces\CodeRole;
use App\Enums\Scrutins\StatutScrutin;
use App\Http\Controllers\Controller;
use App\Models\Scrutin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports des listes électorales et des résultats publiés (module 08), en CSV, Excel (.xlsx) ou PDF
 * selon le paramètre « format » (CSV par défaut).
 */
class ExportController extends Controller
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * Liste électorale d'un scrutin : comité, observateur, ou point focal limité à sa structure (R4). Sans l'émargement.
     */
    public function listeElectorale(Request $request, Scrutin $scrutin): StreamedResponse|Response
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

        return $this->exporter($request, "liste-electorale-{$scrutin->code}", 'Liste électorale · '.$scrutin->libelle,
            ['Matricule', 'Nom', 'Prénoms', 'Sexe', 'Structure'], $lignes);
    }

    /**
     * Résultats d'un scrutin, uniquement après leur publication (R46, R54).
     */
    public function resultats(Request $request, Scrutin $scrutin): StreamedResponse|Response
    {
        abort_unless($scrutin->statut === StatutScrutin::ResultatsPublies, 404);

        $lignes = $scrutin->candidates()->with('agent')->orderByRaw('rang nulls last')->get()
            ->map(fn ($candidate): array => [
                $candidate->rang ?? '', $candidate->agent->matricule, $candidate->agent->nomComplet(),
                $candidate->nb_voix ?? '', $candidate->pourcentage === null ? '' : (float) $candidate->pourcentage,
                $candidate->est_laureate ? 'Lauréate' : '', $candidate->statut->libelle(),
            ]);

        return $this->exporter($request, "resultats-{$scrutin->code}", 'Résultats · '.$scrutin->libelle,
            ['Rang', 'Matricule', 'Candidate', 'Voix', '%', 'Résultat', 'Statut'], $lignes);
    }

    /**
     * @param  list<string>  $entete
     * @param  iterable<int, list<string|int|float>>  $lignes
     */
    private function exporter(Request $request, string $nom, string $titre, array $entete, iterable $lignes): StreamedResponse|Response
    {
        $format = in_array($request->query('format'), self::FORMATS, true) ? $request->query('format') : 'csv';
        $fichier = Str::slug($nom).'.'.$format;

        return match ($format) {
            'xlsx' => response()->streamDownload(function () use ($entete, $lignes): void {
                $classeur = new Writer;
                $classeur->openToFile('php://output');
                $classeur->addRow(Row::fromValuesWithStyle($entete, (new Style)->withFontBold(true)));
                foreach ($lignes as $ligne) {
                    $classeur->addRow(Row::fromValues($ligne));
                }
                $classeur->close();
            }, $fichier, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
            'pdf' => response(Pdf::loadView('exports.tableau', [
                'titre' => $titre,
                'entete' => $entete,
                'lignes' => collect($lignes)->all(),
            ])->setPaper('a4')->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fichier.'"',
            ]),
            default => response()->streamDownload(function () use ($entete, $lignes): void {
                $sortie = fopen('php://output', 'w');
                fwrite($sortie, "\xEF\xBB\xBF");
                fputcsv($sortie, $entete, ';', '"', '');
                foreach ($lignes as $ligne) {
                    fputcsv($sortie, array_map('strval', $ligne), ';', '"', '');
                }
                fclose($sortie);
            }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']),
        };
    }
}
