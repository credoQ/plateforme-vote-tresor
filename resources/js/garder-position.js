// Onglets, pagination et barres de filtres d'une page : la page est rechargée (wire:navigate ou formulaire GET),
// mais elle reste là où l'utilisateur l'avait laissée au lieu de repartir en haut. La position est mémorisée au
// moment du clic ou de l'envoi, puis rétablie à l'arrivée si la nouvelle page a le même chemin.

const CLE = 'fm-position';
const VALIDITE = 15000;
const DUREE_PLACEMENT = 600;
const DECLENCHEURS = '.onglets a, .pagination a';

function memoriser() {
    try {
        sessionStorage.setItem(CLE, JSON.stringify({ chemin: location.pathname, y: window.scrollY, quand: Date.now() }));
    } catch {
        // Stockage indisponible (navigation privée) : la page repart en haut, comme avant.
    }
}

function lirePosition() {
    try {
        const position = JSON.parse(sessionStorage.getItem(CLE) ?? 'null');
        sessionStorage.removeItem(CLE);
        return position;
    } catch {
        return null;
    }
}

document.addEventListener('click', (evenement) => {
    const lien = evenement.target.closest(DECLENCHEURS);
    if (lien && !evenement.ctrlKey && !evenement.metaKey && !evenement.shiftKey && lien.target !== '_blank') memoriser();
}, true);

document.addEventListener('submit', (evenement) => {
    if (evenement.target.matches('form.barre-filtres')) memoriser();
}, true);

/**
 * À appeler à l'arrivée sur une page (livewire:navigated) : rétablit la position mémorisée pour ce chemin.
 * La page n'a pas tout de suite sa hauteur finale (icônes, listes avec recherche, ancre #candidates suivie par le
 * navigateur) : le placement est repris toutes les 16 ms pendant un court instant, et s'arrête dès que l'utilisateur
 * fait défiler lui-même la page.
 */
export function retablirPosition() {
    const position = lirePosition();
    if (!position || position.chemin !== location.pathname || Date.now() - position.quand > VALIDITE) return;

    const fin = performance.now() + DUREE_PLACEMENT;
    let interrompu = false;
    const interrompre = () => { interrompu = true; };
    const evenements = ['wheel', 'touchstart', 'keydown', 'mousedown'];
    evenements.forEach((nom) => window.addEventListener(nom, interrompre, { once: true, passive: true }));

    const placer = () => {
        if (interrompu) return;
        if (Math.abs(window.scrollY - position.y) > 1) window.scrollTo({ top: position.y, behavior: 'instant' });
        if (performance.now() < fin) {
            setTimeout(placer, 16);
        } else {
            evenements.forEach((nom) => window.removeEventListener(nom, interrompre));
        }
    };
    placer();
}
