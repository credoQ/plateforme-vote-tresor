import { createIcons, icons } from 'lucide';

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

// Menu repliable des petits écrans : data-ouvrir-menu (bouton de l'en-tête) / data-fermer-menu (voile, croix)
const boutonMenu = document.querySelector('[data-ouvrir-menu]');
const navigation = document.getElementById('navigation');
function basculerMenu(ouvert) {
    if (!boutonMenu || !navigation) return;
    document.documentElement.classList.toggle('menu-ouvert', ouvert);
    boutonMenu.setAttribute('aria-expanded', String(ouvert));
    if (ouvert) (navigation.querySelector('.nav-lien.est-actif') ?? navigation.querySelector('.nav-lien'))?.focus();
    else boutonMenu.focus({ preventScroll: true });
}
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-ouvrir-menu]')) basculerMenu(true);
    else if (e.target.closest('[data-fermer-menu]')) basculerMenu(false);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.documentElement.classList.contains('menu-ouvert')) basculerMenu(false);
});
window.matchMedia('(min-width: 901px)').addEventListener('change', (e) => {
    if (e.matches) {
        document.documentElement.classList.remove('menu-ouvert');
        boutonMenu?.setAttribute('aria-expanded', 'false');
    }
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

// Impression (accusé de vote) : data-imprimer
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-imprimer]')) window.print();
});
