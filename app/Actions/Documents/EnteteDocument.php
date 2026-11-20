<?php

namespace App\Actions\Documents;

/**
 * Images de l'en-tête des documents PDF (ministère, ISO 9001, DGTCP), intégrées en data URI.
 */
class EnteteDocument
{
    public const IMAGES = ['ministere' => 'ministere.png', 'iso' => 'iso-9001.jpg', 'dgtcp' => 'dgtcp.png'];

    /**
     * @return array<string, string>
     */
    public function images(): array
    {
        return once(fn (): array => collect(self::IMAGES)->map(function (string $fichier): string {
            $chemin = resource_path('images/pdf/'.$fichier);
            $type = str_ends_with($fichier, '.jpg') ? 'image/jpeg' : 'image/png';

            return "data:{$type};base64,".base64_encode((string) file_get_contents($chemin));
        })->all());
    }
}
