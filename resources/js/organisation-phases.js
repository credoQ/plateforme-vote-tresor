// Phases et collèges du formulaire d'édition (composant Alpine) : les phases s'ajoutent une à une, et le collège
// de toute la DGTCP reste seul dans sa phase. Le choisir met les deux pôles sur « Aucun scrutin » ; choisir un
// pôle remet la DGTCP sur « Aucun scrutin ». Le serveur refuse de toute façon une phase qui les combine.
// Un collège sans scrutin a ses candidates et son nombre d'élues grisés : le serveur l'ignore.

const AUCUN = 'AUCUN';
const DGTCP = 'DGTCP';

export default function organisationPhases(phases = 1) {
    return {
        phases,

        init() {
            for (const select of this.$root.querySelectorAll('select[data-college]')) this.griserSansScrutin(select);
        },

        choisirDecoupage(select) {
            this.griserSansScrutin(select);
            this.collegeExclusif(select);
        },

        griserSansScrutin(select) {
            const sansScrutin = select.value === AUCUN;
            for (const champ of select.closest('tr').querySelectorAll('[data-parametre-college]')) champ.disabled = sansScrutin;
        },

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
