<?php

namespace App\Http\Controllers\Acces;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Page de connexion sans mot de passe (R42) et déconnexion. Les deux étapes de la connexion (matricule, puis code
 * reçu par e-mail) sont dans le composant Livewire Acces\Connexion.
 */
class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('acces.connexion', [
            'editionEnCours' => Edition::anneeEnCours(),
            'videos' => $this->videos(),
        ]);
    }

    /**
     * Vidéos de présentation proposées sur la page de connexion (config/videos.php).
     *
     * @return list<array{titre: string, detail: string, src: string, apercu: string}>
     */
    private function videos(): array
    {
        return collect(config('videos'))
            ->map(fn (array $video): array => [
                'titre' => $video['titre'],
                'detail' => $video['detail'],
                'src' => asset("videos/{$video['fichier']}.mp4"),
                'apercu' => asset("videos/{$video['fichier']}.jpg"),
            ])
            ->all();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
