import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import formulaireEtapes from './formulaire-etapes';
import saisieCode from './saisie-code';
import selectRecherche from './select-recherche';

// Icônes Lucide (seule bibliothèque autorisée par la charte) : <i data-lucide="vote"></i>
createIcons({ icons, attrs: { 'stroke-width': 2 } });

// Thème clair / sombre : <html data-theme="sombre">, mémorisé par poste
const THEME = 'fm-theme';
try {
    const t = localStorage.getItem(THEME);
    if (t) document.documentElement.dataset.theme = t;
} catch { /* stockage indisponible : thème clair */ }
document.addEventListener('click', (e) => {
    const bouton = e.target.closest('[data-basculer-theme]');
    if (!bouton) return;
    const sombre = document.documentElement.dataset.theme !== 'sombre';
    document.documentElement.dataset.theme = sombre ? 'sombre' : 'clair';
    try { localStorage.setItem(THEME, sombre ? 'sombre' : 'clair'); } catch { /* ignoré */ }
});

// Modales des maquettes : data-ouvrir="id" / data-fermer
document.addEventListener('click', (e) => {
    const fermer = e.target.closest('[data-fermer]');
    if (fermer || e.target.classList.contains('voile')) e.target.closest('.voile')?.setAttribute('hidden', '');
    const ouvrir = e.target.closest('[data-ouvrir]');
    if (ouvrir) document.getElementById(ouvrir.dataset.ouvrir)?.removeAttribute('hidden');
});

// Bouton de menu de l'en-tête (data-basculer-menu) : sur grand écran, il replie la barre latérale en icônes
// (choix mémorisé par poste) ; sur petit écran, il l'ouvre en panneau sur un voile (data-fermer-menu : voile, croix).
const racine = document.documentElement;
const boutonMenu = document.querySelector('[data-basculer-menu]');
const navigation = document.getElementById('navigation');
const grandEcran = window.matchMedia('(min-width: 901px)');
const MENU = 'fm-menu';

function synchroniserBoutonMenu() {
    if (!boutonMenu) return;
    const replie = racine.classList.contains('menu-replie');
    const ouvert = grandEcran.matches ? !replie : racine.classList.contains('menu-ouvert');
    boutonMenu.setAttribute('aria-expanded', String(ouvert));
    boutonMenu.setAttribute('aria-label', grandEcran.matches
        ? (replie ? 'Déplier le menu' : 'Replier le menu')
        : (ouvert ? 'Fermer le menu' : 'Ouvrir le menu'));
}
function basculerReplie() {
    const replie = racine.classList.toggle('menu-replie');
    try { localStorage.setItem(MENU, replie ? 'replie' : 'deplie'); } catch { /* ignoré */ }
    synchroniserBoutonMenu();
}
function basculerMenu(ouvert) {
    if (!boutonMenu || !navigation) return;
    racine.classList.toggle('menu-ouvert', ouvert);
    synchroniserBoutonMenu();
    if (ouvert) (navigation.querySelector('.nav-lien.est-actif') ?? navigation.querySelector('.nav-lien'))?.focus();
    else boutonMenu.focus({ preventScroll: true });
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
synchroniserBoutonMenu();

// Envoi d'un formulaire POST : le bouton passe « en cours » et un second clic n'envoie pas la requête deux fois.
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
// Retour arrière (page restaurée du cache) : les formulaires redeviennent utilisables.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-envoi]').forEach((formulaire) => delete formulaire.dataset.envoi);
    document.querySelectorAll('.est-en-cours').forEach((bouton) => {
        bouton.classList.remove('est-en-cours');
        bouton.removeAttribute('aria-busy');
    });
});

// Tableaux en fiches sur téléphone (.tableau--fiches) : chaque cellule reçoit le libellé de sa colonne,
// repris de l'en-tête, pour l'afficher devant la valeur ; son contenu est regroupé dans un seul bloc
// (.tableau__valeur) pour que badge, texte et aide s'enchaînent normalement (voir composants.css).
document.querySelectorAll('table.tableau--fiches').forEach((tableau) => {
    const libelles = [...tableau.querySelectorAll('thead th')].flatMap((th) => Array(th.colSpan).fill(th.textContent.trim()));
    tableau.querySelectorAll('tbody tr').forEach((ligne) => {
        let colonne = 0;
        [...ligne.cells].forEach((cellule) => {
            if (cellule.colSpan === 1 && libelles[colonne]) {
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

// Impression : data-imprimer
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-imprimer]')) window.print();
});

// Alpine.js : menu de l'avatar, notifications, listes avec recherche, formulaire en étapes.
// Livewire embarque Alpine : à son arrivée, retirer l'import et Alpine.start(), et enregistrer
// les composants sur l'événement « alpine:init ».
window.Alpine = Alpine;
Alpine.data('selectRecherche', selectRecherche);
Alpine.data('formulaireEtapes', formulaireEtapes);
Alpine.data('saisieCode', saisieCode);
Alpine.start();
createIcons({ icons, attrs: { 'stroke-width': 2 } });
