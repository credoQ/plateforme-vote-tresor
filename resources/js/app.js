import { createIcons, icons } from 'lucide';
import './alertes';
import filtreBulletin from './filtre-bulletin';
import formulaireEtapes from './formulaire-etapes';
import lecteurVideo from './lecteur-video';
import organisationPhases from './organisation-phases';
import recadragePhoto from './recadrage-photo';
import saisieCode from './saisie-code';
import selectRecherche from './select-recherche';

// Livewire fournit Alpine.js (@livewireScripts dans chaque gabarit) : ne jamais l'importer une seconde fois.
// Une navigation wire:navigate remplace le <body> sans recharger ce fichier : tout ce qui prépare la page
// s'exécute dans preparerPage(), relancée à chaque livewire:navigated et après chaque mise à jour d'un composant.

const racine = document.documentElement;
const THEME = 'fm-theme';
const MENU = 'fm-menu';
const grandEcran = window.matchMedia('(min-width: 901px)');

function lire(cle) {
    try { return localStorage.getItem(cle); } catch { return null; }
}
function ecrire(cle, valeur) {
    try { localStorage.setItem(cle, valeur); } catch { /* stockage indisponible : réglage valable pour la page */ }
}

// Thème clair / sombre (<html data-theme="sombre">) et menu replié (<html class="menu-replie">), mémorisés par poste.
// La navigation wire:navigate remplace les attributs de <html> : on les remet avant l'affichage de la nouvelle page.
function restaurerReglages() {
    racine.dataset.theme = lire(THEME) ?? 'clair';
    racine.classList.toggle('menu-replie', lire(MENU) === 'replie');
}
restaurerReglages();
document.addEventListener('livewire:navigating', (e) => e.detail?.onSwap?.(restaurerReglages));

document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-basculer-theme]')) return;
    const sombre = racine.dataset.theme !== 'sombre';
    racine.dataset.theme = sombre ? 'sombre' : 'clair';
    ecrire(THEME, sombre ? 'sombre' : 'clair');
});

// Modales des maquettes : data-ouvrir="id" / data-fermer. Les confirmations des actions sensibles
// (form.confirmation) s'ouvrent dans SweetAlert (resources/js/alertes.js).
document.addEventListener('click', (e) => {
    const fermer = e.target.closest('[data-fermer]');
    if (fermer || e.target.classList.contains('voile')) e.target.closest('.voile')?.setAttribute('hidden', '');
    const ouvrir = e.target.closest('[data-ouvrir]');
    const cible = ouvrir ? document.getElementById(ouvrir.dataset.ouvrir) : null;
    if (cible && !cible.matches('form.confirmation')) cible.removeAttribute('hidden');
});

// Bouton de menu de l'en-tête (data-basculer-menu) : sur grand écran, il replie la barre latérale en icônes ;
// sur petit écran, il l'ouvre en panneau sur un voile (data-fermer-menu : voile, croix).
function synchroniserBoutonMenu() {
    const bouton = document.querySelector('[data-basculer-menu]');
    if (!bouton) return;
    const replie = racine.classList.contains('menu-replie');
    const ouvert = grandEcran.matches ? !replie : racine.classList.contains('menu-ouvert');
    bouton.setAttribute('aria-expanded', String(ouvert));
    bouton.setAttribute('aria-label', grandEcran.matches
        ? (replie ? 'Déplier le menu' : 'Replier le menu')
        : (ouvert ? 'Fermer le menu' : 'Ouvrir le menu'));
}
function basculerReplie() {
    ecrire(MENU, racine.classList.toggle('menu-replie') ? 'replie' : 'deplie');
    synchroniserBoutonMenu();
}
function basculerMenu(ouvert) {
    const bouton = document.querySelector('[data-basculer-menu]');
    const navigation = document.getElementById('navigation');
    if (!bouton || !navigation) return;
    racine.classList.toggle('menu-ouvert', ouvert);
    synchroniserBoutonMenu();
    if (ouvert) (navigation.querySelector('.nav-lien.est-actif') ?? navigation.querySelector('.nav-lien'))?.focus();
    else bouton.focus({ preventScroll: true });
}
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-basculer-menu]')) grandEcran.matches ? basculerReplie() : basculerMenu(!racine.classList.contains('menu-ouvert'));
    else if (e.target.closest('[data-fermer-menu]')) basculerMenu(false);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && racine.classList.contains('menu-ouvert')) basculerMenu(false);
});
grandEcran.addEventListener('change', () => {
    racine.classList.remove('menu-ouvert');
    synchroniserBoutonMenu();
});
// Sur téléphone, le menu ouvert se referme quand on change de page.
document.addEventListener('livewire:navigating', () => racine.classList.remove('menu-ouvert'));

// Envoi d'un formulaire POST classique : le bouton passe « en cours » et un second clic n'envoie rien.
// Les formulaires Livewire (wire:submit) ont leur propre état de chargement.
document.addEventListener('submit', (e) => {
    const formulaire = e.target;
    if (e.defaultPrevented || formulaire.method.toLowerCase() !== 'post') return;
    if (formulaire.dataset.envoi === 'en-cours') {
        e.preventDefault();
        return;
    }
    formulaire.dataset.envoi = 'en-cours';
    const bouton = e.submitter ?? formulaire.querySelector('[type="submit"]');
    bouton?.classList.add('est-en-cours');
    bouton?.setAttribute('aria-busy', 'true');
});

// Formulaire d'action d'une page de la console (gabarit marqué data-sans-rechargement, confirmations comprises,
// que la fenêtre déplace hors du contenu) : envoyé en arrière-plan, puis la page cible s'ouvre par la navigation
// Livewire, sans rechargement complet ; on reste à la même hauteur si c'est la même page. Un formulaire
// data-rechargement (déconnexion) part normalement. Le serveur répond { redirection } (middleware
// EnvoiSansRechargement) ; le message de succès ou d'erreur est en session.
document.addEventListener('submit', async (e) => {
    const formulaire = e.target;
    if (e.defaultPrevented || formulaire.method.toLowerCase() !== 'post') return;
    if (!document.querySelector('[data-sans-rechargement]') || formulaire.closest('[data-rechargement]')) return;
    e.preventDefault();

    const finir = () => {
        delete formulaire.dataset.envoi;
        formulaire.querySelectorAll('.est-en-cours').forEach((bouton) => {
            bouton.classList.remove('est-en-cours');
            bouton.removeAttribute('aria-busy');
        });
    };

    let reponse;
    try {
        reponse = await fetch(formulaire.action, {
            method: 'POST',
            body: new FormData(formulaire, e.submitter),
            headers: { 'X-Envoi-Sans-Rechargement': '1', Accept: 'text/html' },
            credentials: 'same-origin',
        });
    } catch {
        finir();
        window.signalerErreur?.('La plateforme ne répond pas. Vérifiez votre connexion, puis recommencez.');
        return;
    }

    const redirection = reponse.ok && reponse.headers.get('Content-Type')?.includes('application/json')
        ? (await reponse.json()).redirection
        : null;
    if (!redirection) {
        // Réponse inattendue (droits, session expirée…) : la page est rechargée pour afficher son état réel.
        window.location.reload();
        return;
    }

    const memePage = new URL(redirection, window.location.href).pathname === window.location.pathname;
    const hauteur = window.scrollY;
    if (memePage) {
        document.addEventListener('livewire:navigated', () => requestAnimationFrame(() => window.scrollTo(0, hauteur)), { once: true });
    }
    window.Livewire.navigate(redirection);
});
// Retour arrière (page restaurée du cache) : les formulaires redeviennent utilisables.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-envoi]').forEach((formulaire) => delete formulaire.dataset.envoi);
    document.querySelectorAll('.est-en-cours').forEach((bouton) => {
        bouton.classList.remove('est-en-cours');
        bouton.removeAttribute('aria-busy');
    });
});

// Tableaux en fiches sur téléphone (.tableau--fiches) : chaque cellule reçoit le libellé de sa colonne, repris
// de l'en-tête, et son contenu est regroupé dans un bloc .tableau__valeur. Une cellule déjà traitée est ignorée :
// la fonction peut être relancée après chaque mise à jour d'un composant Livewire.
function preparerTableaux() {
    document.querySelectorAll('table.tableau--fiches').forEach((tableau) => {
        const libelles = [...tableau.querySelectorAll('thead th')].flatMap((th) => Array(th.colSpan).fill(th.textContent.trim()));
        tableau.querySelectorAll('tbody tr').forEach((ligne) => {
            let colonne = 0;
            [...ligne.cells].forEach((cellule) => {
                if (cellule.colSpan === 1 && libelles[colonne] && !cellule.querySelector(':scope > .tableau__valeur')) {
                    cellule.dataset.libelle = libelles[colonne];
                    if (cellule.textContent.trim() === '' && !cellule.querySelector('svg, i, img, button, a, form')) cellule.dataset.vide = '';
                    const valeur = document.createElement('div');
                    valeur.className = 'tableau__valeur';
                    valeur.append(...cellule.childNodes);
                    cellule.append(valeur);
                }
                colonne += cellule.colSpan;
            });
        });
    });
}

// Impression : data-imprimer
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-imprimer]')) window.print();
});

// Icônes Lucide (seule bibliothèque autorisée par la charte) : <i data-lucide="vote"></i>
function preparerPage() {
    createIcons({ icons, attrs: { 'stroke-width': 2 } });
    preparerTableaux();
    synchroniserBoutonMenu();
}
document.addEventListener('livewire:navigated', preparerPage);

// Composants Alpine : menu de l'avatar, notifications, listes avec recherche, formulaire en étapes, saisie du code.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('selectRecherche', selectRecherche);
    window.Alpine.data('formulaireEtapes', formulaireEtapes);
    window.Alpine.data('filtreBulletin', filtreBulletin);
    window.Alpine.data('organisationPhases', organisationPhases);
    window.Alpine.data('saisieCode', saisieCode);
    window.Alpine.data('lecteurVideo', lecteurVideo);
    window.Alpine.data('recadragePhoto', recadragePhoto);
});

// Après chaque mise à jour d'un composant Livewire, les icônes et les tableaux renvoyés par le serveur sont repréparés.
// Un îlot (@island, rafraîchi seul, comme celui du tableau de bord) signale sa mise à jour par « island.morphed ».
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', () => requestAnimationFrame(preparerPage));
    window.Livewire.hook('island.morphed', () => requestAnimationFrame(preparerPage));
});
