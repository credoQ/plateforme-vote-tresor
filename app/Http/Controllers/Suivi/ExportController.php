<?php

namespace App\Http\Controllers\Suivi;

use App\Actions\Documents\CodeQr;
use App\Actions\Documents\LienVerification;
use App\Actions\Documents\LignesExport;
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
 * selon le paramètre « format » (CSV par défaut). Le PDF porte un QR code de vérification en ligne.
 */
class ExportController extends Controller
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function __construct(
        private LignesExport $lignesExport,
        private CodeQr $codeQr,
        private LienVerification $lien,
    ) {}

    /**
     * Liste électorale d'un scrutin : comité, observateur, ou point focal limité à sa structure (R4). Sans l'émargement.
     */
    public function listeElectorale(Request $request, Scrutin $scrutin): StreamedResponse|Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur->can('observer') || $utilisateur->can('controler-listes'), 403);
        $perimetre = $utilisateur->aLeRole(CodeRole::Comite, CodeRole::Observateur, CodeRole::Administrateur) ? null : $utilisateur->perimetre();

        ['entete' => $entete, 'lignes' => $lignes] = $this->lignesExport->listeElectorale($scrutin, $perimetre);

        return $this->exporter($request, 'liste', $scrutin, "liste-electorale-{$scrutin->code}", 'Liste électorale', $entete, $lignes, $perimetre);
    }

    /**
     * Résultats d'un scrutin, uniquement après leur publication (R46, R54).
     */
    public function resultats(Request $request, Scrutin $scrutin): StreamedResponse|Response
    {
        abort_unless($scrutin->statut === StatutScrutin::ResultatsPublies, 404);

        ['entete' => $entete, 'lignes' => $lignes] = $this->lignesExport->resultats($scrutin);

        return $this->exporter($request, 'resultats', $scrutin, "resultats-{$scrutin->code}", 'Résultats', $entete, $lignes);
    }

    /**
     * @param  'liste'|'resultats'  $type
     * @param  list<string>  $entete
     * @param  list<list<string|int|float>>  $lignes
     * @param  list<int>|null  $perimetre
     */
    private function exporter(Request $request, string $type, Scrutin $scrutin, string $nom, string $titre, array $entete, array $lignes, ?array $perimetre = null): StreamedResponse|Response
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
            'pdf' => $this->pdf($request, $type, $scrutin, $fichier, $titre, $entete, $lignes, $perimetre),
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

    /**
     * @param  'liste'|'resultats'  $type
     * @param  list<string>  $entete
     * @param  list<list<string|int|float>>  $lignes
     * @param  list<int>|null  $perimetre
     */
    private function pdf(Request $request, string $type, Scrutin $scrutin, string $fichier, string $titre, array $entete, array $lignes, ?array $perimetre): Response
    {
        $edition = now();
        $verification = $this->lien->export($type, $scrutin, $this->lignesExport->empreinte($lignes), count($lignes), $edition, $perimetre);

        $pdf = Pdf::loadView('exports.tableau', [
            'titre' => $titre,
            'sousTitre' => $scrutin->phase->libelle.' · '.$scrutin->libelle,
            'type' => $titre,
            'entete' => $entete,
            'lignes' => $lignes,
            'qr' => $this->codeQr->dataUri($verification),
            'editeLe' => $edition,
            'editePar' => $request->user()->name,
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fichier.'"',
        ]);
    }
}
