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
