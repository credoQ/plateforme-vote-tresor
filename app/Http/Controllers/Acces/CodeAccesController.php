<?php

namespace App\Http\Controllers\Acces;

use App\Actions\Acces\ImprimerCodesAcces;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Codes d'accès du mode « code imprimé » (comité et administrateur) : l'écran (liste, génération, régénération dans
 * le composant Livewire Acces\CodesAcces) et les coupons en PDF, par lot ou pour un seul compte.
 */
class CodeAccesController extends Controller
{
    public function index(): View
    {
        return view('acces.codes-acces');
    }

    public function lot(Request $request, string $lot, ImprimerCodesAcces $imprimer): Response
    {
        return $this->telecharger($imprimer->handle($lot, $request->user()));
    }

    public function coupon(Request $request, User $utilisateur, ImprimerCodesAcces $imprimer): Response
    {
        abort_if($utilisateur->code_acces === null, 404);

        return $this->telecharger($imprimer->coupon($utilisateur, $request->user()));
    }

    /**
     * @param  array{pdf: string, nom: string, nombre: int}  $document
     */
    private function telecharger(array $document): Response
    {
        return response($document['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document['nom'].'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
