{{-- Coupons des codes d'accès (ImprimerCodesAcces) : deux coupons par ligne, à découper et remettre sous pli.
     Dompdf ne lit pas les variables CSS : couleurs de la charte recopiées. --}}
<x-pdf.document :titre="$titre" type="Codes d’accès · confidentiel" :edite-le="now()" :edite-par="$auteur">
    <style>
        .section { page-break-before: always; }
        .section:first-of-type { page-break-before: auto; }
        .section h2 { font-size: 11pt; margin: 4px 0 8px; color: #0054A0; }
        table.coupons { width: 100%; border-collapse: separate; border-spacing: 8px; }
        table.coupons td { width: 50%; vertical-align: top; }
        .coupon { border: 1px dashed #8a95a1; border-radius: 6px; padding: 8px 10px; page-break-inside: avoid; }
        .coupon__titre { font-size: 7pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #5b6573; }
        .coupon__nom { font-size: 10pt; font-weight: bold; margin-top: 3px; }
        .coupon__info { font-size: 8pt; color: #5b6573; }
        .coupon__code { margin: 6px 0 4px; padding: 5px 0; text-align: center; font-family: "DejaVu Sans Mono", monospace; font-size: 16pt; font-weight: bold; letter-spacing: 2px; background: #EFF5F9; border-radius: 4px; color: #003a70; }
        .coupon__aide { font-size: 7pt; color: #5b6573; }
    </style>

    <h1>{{ $titre }}</h1>
    <p class="sous-titre">À découper et remettre sous pli à chaque personne. Un code est personnel : ne le communiquez à personne.</p>

    @forelse ($sections as $structure => $section)
        <div class="section">
            <h2>{{ $structure }} · {{ $section['nombre'] }} code(s)</h2>
            <table class="coupons">
                @foreach ($section['lignes'] as $ligne)
                    <tr>
                        @foreach ($ligne as $compte)
                            <td>
                                <div class="coupon">
                                    <div class="coupon__titre">Femme modèle du Trésor · code d’accès personnel</div>
                                    <div class="coupon__nom">{{ $compte['nom'] }}</div>
                                    <div class="coupon__info">Matricule ou identifiant : <strong>{{ $compte['login'] }}</strong>@if ($compte['roles']) · {{ $compte['roles'] }}@endif</div>
                                    <div class="coupon__code">{{ $compte['code'] }}</div>
                                    <div class="coupon__aide">Connectez-vous sur {{ $adresse }} avec votre matricule et ce code, valable pour toute l’édition. Coupon perdu : adressez-vous au comité.</div>
                                </div>
                            </td>
                        @endforeach
                        @if ($loop->last && $section['nombre'] % 2 === 1)<td></td>@endif
                    </tr>
                @endforeach
            </table>
        </div>
    @empty
        <p>Aucun code à imprimer : générez d’abord les codes manquants.</p>
    @endforelse
</x-pdf.document>
