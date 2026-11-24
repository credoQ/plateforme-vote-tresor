// Filtre des candidates sur le bulletin de vote (composant Alpine) : la recherche par nom (sans tenir compte des
// accents ni des majuscules) et le choix d'une structure masquent les cartes qui ne correspondent pas, sans
// recharger la page. Rien n'est envoyé au serveur : le choix ne transite que dans la requête de vote. La candidate
// cochée reste toujours visible, pour ne pas perdre de vue le choix déjà fait.

const normaliser = (texte) => (texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

export default function filtreBulletin() {
    return {
        recherche: '',
        structure: '',

        correspond(carte) {
            if (carte.querySelector('input[type="radio"]')?.checked) return true;
            const nom = normaliser(carte.dataset.nom);
            const terme = normaliser(this.recherche);
            return (terme === '' || nom.includes(terme)) && (this.structure === '' || carte.dataset.structure === this.structure);
        },

        get nombreVisibles() {
            // Lu par x-text : se recalcule à chaque saisie ou changement de structure.
            void this.recherche; void this.structure;
            return [...this.$root.querySelectorAll('[data-candidate]')].filter((carte) => this.correspond(carte)).length;
        },
    };
}
