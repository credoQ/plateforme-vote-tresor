<?php

namespace App\Http\Controllers;

use App\Actions\Documents\LignesExport;
use App\Enums\Scrutins\StatutScrutin;
use App\Models\Inscription;
use App\Models\ProcesVerbal;
use App\Models\Scrutin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Vérification en ligne d'un document PDF à partir de son QR code (lien signé, page publique).
 * Aucune donnée personnelle des listes électorales n'est affichée ; les résultats ne le sont qu'après publication (R46).
 */
class VerificationController extends Controller
{
    public function procesVerbal(string $numero): View
    {
        $pv = ProcesVerbal::with(['scrutin.phase.edition', 'scrutin.laureates.agent'])->where('numero', $numero)->firstOrFail();
        $publie = $pv->scrutin->statut === StatutScrutin::ResultatsPublies;

        return view('verification.document', [
            'conforme' => true,
            'type' => 'Procès-verbal n° '.$pv->numero,
            'scrutin' => $pv->scrutin,
            'lignes' => array_filter([
                'Validé le' => $pv->date_validation?->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i'),
                'Publication' => $publie ? 'le '.$pv->date_publication->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i') : 'résultats non encore publiés',
                'Inscrits' => $publie ? (string) $pv->nb_inscrits : null,
                'Votants' => $publie ? (string) $pv->nb_votants : null,
                'Lauréate(s)' => $publie ? $pv->scrutin->laureates->map(fn ($c) => $c->agent->nomComplet())->implode(', ') : null,
            ]),
        ]);
    }

    /**
     * Accusé de vote : la page confirme qu'un vote est enregistré sous ce numéro, sans nommer l'électeur (R43).
     */
    public function accuseDeVote(string $numero): View
    {
        $inscription = Inscription::with('scrutin.phase.edition')->where('numero_accuse', $numero)->where('a_vote', true)->firstOrFail();

        return view('verification.document', [
            'conforme' => true,
            'type' => 'Accusé de vote n° '.$inscription->numero_accuse,
            'scrutin' => $inscription->scrutin,
            'lignes' => ['Vote enregistré le' => $inscription->creneauDeVote()],
        ]);
    }

    public function export(Request $request, LignesExport $lignesExport): View
    {
        $type = $request->query('type');
        abort_unless(in_array($type, ['liste', 'resultats'], true), 404);
        $scrutin = Scrutin::with('phase.edition')->findOrFail((int) $request->query('scrutin'));
        abort_if($type === 'resultats' && $scrutin->statut !== StatutScrutin::ResultatsPublies, 404);

        $perimetre = $request->filled('perimetre') ? array_map('intval', explode('-', (string) $request->query('perimetre'))) : null;
        $contenu = $type === 'liste' ? $lignesExport->listeElectorale($scrutin, $perimetre) : $lignesExport->resultats($scrutin);
        $edition = Carbon::createFromFormat('YmdHi', (string) $request->query('edition'), 'UTC');

        return view('verification.document', [
            'conforme' => hash_equals((string) $request->query('empreinte'), $lignesExport->empreinte($contenu['lignes'])),
            'type' => $type === 'liste' ? 'Liste électorale'.($perimetre !== null ? ' (extrait d’une structure)' : '') : 'Résultats',
            'scrutin' => $scrutin,
            'lignes' => [
                'Édité le' => $edition->timezone('Africa/Porto-Novo')->format('d/m/Y à H:i'),
                'Nombre de lignes' => (string) $request->integer('lignes'),
            ],
        ]);
    }
}
