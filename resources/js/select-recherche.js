// Liste déroulante avec recherche (composant Alpine).
// Il enrichit le <select> natif placé dans <x-select-recherche> : le formulaire envoie toujours ce <select>,
// qui reste utilisable sans JavaScript. Les options, leurs groupes (<optgroup>) et un détail facultatif
// (data-detail sur <option>) sont lus dans le <select> ; la recherche ignore les accents et la casse.
// Une option de valeur vide (« Toutes les structures ») est un choix comme les autres et sert à effacer.

const LIMITE = 150;
const SEUIL_RECHERCHE = 7;

function plier(texte) {
    return String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

export default function selectRecherche({ placeholder = 'Choisir…', soumettre = false } = {}) {
    return {
        // Déclaré ici : une propriété créée seulement dans init() serait rangée par Alpine sur le composant parent
        // le plus extérieur, et toutes les listes d'un même formulaire partageraient alors le même <select>.
        select: null,
        placeholder,
        options: [],
        resultats: [],
        total: 0,
        valeurs: [],
        multiple: false,
        recherche: '',
        ouvert: false,
        actif: 0,
        position: {},
        erreur: false,
        desactive: false,
        avecRecherche: false,
        aUneOptionVide: false,

        init() {
            this.select = this.$root.querySelector('select');
            this.multiple = this.select.multiple;
            this.lireOptions();
            this.lireValeurs();

            // Le bouton prend la place du <select> pour son libellé (<label for>) et sa description.
            const bouton = this.$refs.bouton;
            if (this.select.id) {
                bouton.id = this.select.id;
                this.select.removeAttribute('id');
            }
            for (const attribut of ['aria-label', 'aria-describedby', 'aria-invalid']) {
                if (this.select.hasAttribute(attribut)) bouton.setAttribute(attribut, this.select.getAttribute(attribut));
            }
            this.erreur = this.select.classList.contains('est-erreur');
            this.select.classList.add('select-recherche__natif');
            this.select.tabIndex = -1;
            this.select.setAttribute('aria-hidden', 'true');

            // Champ obligatoire vide : le navigateur ne peut pas montrer le <select> masqué, on ouvre la liste.
            this.select.addEventListener('invalid', (e) => {
                e.preventDefault();
                this.erreur = true;
                bouton.focus();
            });
            this.select.addEventListener('change', () => this.lireValeurs());

            // Un <select> désactivé par la page (attribut disabled) désactive aussi le bouton qui le remplace.
            this.desactive = this.select.disabled;
            new MutationObserver(() => {
                this.desactive = this.select.disabled;
                if (this.desactive) this.fermer();
            }).observe(this.select, { attributes: true, attributeFilter: ['disabled'] });

            this.$watch('recherche', () => {
                this.actif = 0;
                this.filtrer();
            });
            this.filtrer();
        },

        lireOptions() {
            this.options = [...this.select.options].filter((o) => !o.hidden).map((o) => ({
                valeur: o.value,
                libelle: o.textContent.trim(),
                groupe: o.parentElement.tagName === 'OPTGROUP' ? o.parentElement.label : null,
                detail: o.dataset.detail ?? null,
                desactivee: o.disabled,
                cle: plier(`${o.textContent} ${o.parentElement.label ?? ''} ${o.dataset.detail ?? ''}`),
            }));
            this.aUneOptionVide = this.options.some((o) => o.valeur === '');
            this.avecRecherche = this.options.length >= SEUIL_RECHERCHE;
        },

        lireValeurs() {
            this.valeurs = [...this.select.selectedOptions].map((o) => o.value);
        },

        filtrer() {
            const terme = plier(this.recherche.trim());
            const trouvees = terme === '' ? this.options : this.options.filter((o) => o.cle.includes(terme));
            this.total = trouvees.length;
            this.resultats = trouvees.slice(0, LIMITE);
        },

        get choisie() {
            return this.multiple ? null : (this.options.find((o) => o.valeur === this.valeurs[0]) ?? null);
        },
        get estVide() {
            return this.multiple ? this.valeurs.length === 0 : (this.choisie === null || this.choisie.valeur === '');
        },
        get libelleAffiche() {
            if (this.multiple) return this.valeurs.length ? '' : this.placeholder;
            return this.choisie?.libelle || this.placeholder;
        },
        get effacable() {
            return this.multiple ? this.valeurs.length > 0 : (this.aUneOptionVide && !this.estVide);
        },
        libelleDe(valeur) {
            return this.options.find((o) => o.valeur === valeur)?.libelle ?? valeur;
        },
        estChoisie(option) {
            return this.valeurs.includes(option.valeur);
        },
        nouveauGroupe(i) {
            return this.resultats[i].groupe !== null && (i === 0 || this.resultats[i].groupe !== this.resultats[i - 1].groupe);
        },

        placer() {
            const r = this.$refs.bouton.getBoundingClientRect();
            const enHaut = innerHeight - r.bottom < 320 && r.top > innerHeight - r.bottom;
            this.position = {
                left: `${r.left}px`,
                width: `${Math.max(r.width, 280)}px`,
                top: enHaut ? 'auto' : `${r.bottom + 4}px`,
                bottom: enHaut ? `${innerHeight - r.top + 4}px` : 'auto',
            };
        },
        ouvrir() {
            if (this.select.disabled) return;
            this.placer();
            this.ouvert = true;
            this.recherche = '';
            this.filtrer();
            const i = this.resultats.findIndex((o) => this.estChoisie(o));
            this.actif = i < 0 ? 0 : i;
            // Après l'affichage de la liste, et après le clic qui l'a ouverte : sinon le bouton garde la main.
            this.$nextTick(() => requestAnimationFrame(() => {
                (this.avecRecherche ? this.$refs.saisie : this.$refs.liste)?.focus();
                this.defiler();
            }));
        },
        fermer(rendreLaMain = false) {
            this.ouvert = false;
            this.recherche = '';
            if (rendreLaMain) this.$refs.bouton.focus();
        },
        basculer() {
            this.ouvert ? this.fermer() : this.ouvrir();
        },

        appliquer(valeurs) {
            for (const option of this.select.options) option.selected = valeurs.includes(option.value);
            this.valeurs = valeurs;
            this.erreur = false;
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
            if (soumettre) this.$nextTick(() => this.select.form?.requestSubmit());
        },
        choisir(option) {
            if (option.desactivee) return;
            if (this.multiple) {
                this.appliquer(this.estChoisie(option) ? this.valeurs.filter((v) => v !== option.valeur) : [...this.valeurs, option.valeur]);
                return;
            }
            this.appliquer([option.valeur]);
            this.fermer(true);
        },
        retirer(valeur) {
            this.appliquer(this.valeurs.filter((v) => v !== valeur));
        },
        effacer() {
            this.appliquer(this.multiple ? [] : ['']);
            this.fermer(true);
        },

        deplacer(pas) {
            const nombre = this.resultats.length;
            if (!nombre) return;
            this.actif = (this.actif + pas + nombre) % nombre;
            this.$nextTick(() => this.defiler());
        },
        defiler() {
            this.$refs.liste?.querySelector('[data-actif="true"]')?.scrollIntoView({ block: 'nearest' });
        },
        choisirActif() {
            if (this.resultats[this.actif]) this.choisir(this.resultats[this.actif]);
        },
    };
}
