<?php

namespace App\Actions\Documents;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code des documents PDF, en SVG intégré (data URI) : dompdf l'affiche sans accès réseau ni extension d'image.
 */
class CodeQr
{
    public function dataUri(string $contenu, int $taille = 160): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($taille, 1), new SvgImageBackEnd)))->writeString($contenu);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
