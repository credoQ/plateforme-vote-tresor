// Phases et collèges du formulaire d'édition (composant Alpine) : les phases s'ajoutent une à une, et le collège
// de toute la DGTCP reste seul dans sa phase. Le choisir met les deux pôles sur « Aucun scrutin » ; choisir un
// pôle remet la DGTCP sur « Aucun scrutin ». Le serveur refuse de toute façon une phase qui les combine.

const AUCUN = 'AUCUN';
const DGTCP = 'DGTCP';

export default function organisationPhases(phases = 1) {
    return {
        phases,

        collegeExclusif(select) {
            if (select.value === AUCUN) return;
            const dgtcp = select.dataset.college === DGTCP;
            for (const autre of select.closest('tbody').querySelectorAll('select[data-college]')) {
                if (autre === select || autre.value === AUCUN || (!dgtcp && autre.dataset.college !== DGTCP)) continue;
                autre.value = AUCUN;
                autre.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    };
}
