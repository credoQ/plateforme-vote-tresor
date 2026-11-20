// Formulaire en plusieurs étapes (composant Alpine) : un seul <form>, envoyé à la dernière étape, dont les
// panneaux [data-etape] s'affichent l'un après l'autre. « Suivant » vérifie les champs de l'étape ; après un
// renvoi du serveur, le formulaire rouvre la première étape qui porte une erreur. Le récapitulatif lit les
// champs avec valeur('nom') et coche('nom'). Sans JavaScript, toutes les étapes restent visibles.

function formaterDate(valeur) {
    const [annee, mois, jour] = valeur.split('-');
    return jour && mois && annee ? `${jour}/${mois}/${annee}` : valeur;
}

export default function formulaireEtapes() {
    return {
        etape: 1,
        nombre: 1,
        panneaux: [],
        version: 0,

        init() {
            this.panneaux = [...this.$root.querySelectorAll('[data-etape]')];
            this.nombre = this.panneaux.length;
            const enErreur = this.panneaux.findIndex((p) => p.querySelector('.erreur-champ, .alerte--erreur'));
            this.etape = enErreur >= 0 ? enErreur + 1 : 1;
            this.$root.dataset.pret = '';
        },

        estAffichee(numero) {
            return this.etape === numero;
        },
        etat(numero) {
            if (numero < this.etape) return 'est-faite';
            return numero === this.etape ? 'est-en-cours' : '';
        },
        get estDerniere() {
            return this.etape === this.nombre;
        },

        etapeValide() {
            const champs = this.panneaux[this.etape - 1].querySelectorAll('input, select, textarea');
            const invalide = [...champs].find((champ) => !champ.checkValidity());
            invalide?.reportValidity();
            return !invalide;
        },
        allerA(numero) {
            if (numero > this.etape && !this.etapeValide()) return;
            this.etape = Math.min(Math.max(numero, 1), this.nombre);
            this.version++;
            this.$nextTick(() => {
                this.$root.scrollIntoView({ block: 'start', behavior: 'smooth' });
                this.panneaux[this.etape - 1].querySelector('input:not([type="hidden"]), button, select')?.focus({ preventScroll: true });
            });
        },
        suivante() {
            this.allerA(this.etape + 1);
        },
        precedente() {
            this.allerA(this.etape - 1);
        },
        // Entrée dans un champ : passe à l'étape suivante au lieu d'envoyer le formulaire avant la fin.
        entree(e) {
            if (this.estDerniere || e.defaultPrevented || ['TEXTAREA', 'BUTTON', 'A'].includes(e.target.tagName)) return;
            e.preventDefault();
            this.suivante();
        },

        champ(nom) {
            return this.$root.querySelector('form')?.elements.namedItem(nom) ?? null;
        },
        valeur(nom) {
            void this.version;
            const champ = this.champ(nom);
            if (!champ) return '';
            if (champ.tagName === 'SELECT') return champ.selectedOptions[0]?.textContent.trim() ?? '';
            if (champ.type === 'checkbox') return champ.checked ? 'Oui' : 'Non';
            if (champ.value === '') return 'Non renseigné';
            return champ.type === 'date' ? formaterDate(champ.value) : champ.value;
        },
        brut(nom) {
            void this.version;
            return this.champ(nom)?.value ?? '';
        },
        coche(nom) {
            void this.version;
            return this.champ(nom)?.checked ?? false;
        },
    };
}
