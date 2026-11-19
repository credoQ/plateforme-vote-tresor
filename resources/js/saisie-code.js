// Saisie du code de connexion en six cases (composant Alpine). Le champ « code » d'origine reste dans le
// formulaire, masqué, et reçoit les six chiffres : c'est lui qui est envoyé. Coller le code remplit les cases.

const LONGUEUR = 6;

export default function saisieCode() {
    return {
        chiffres: Array(LONGUEUR).fill(''),

        init() {
            this.$refs.natif.hidden = true;
            this.$refs.code.removeAttribute('autofocus');
            this.repartir(this.$refs.code.value);
            this.$nextTick(() => this.aller(Math.min(this.$refs.code.value.length, LONGUEUR - 1)));
        },

        cases() {
            return [...this.$root.querySelectorAll('.saisie-code__case')];
        },
        aller(i) {
            this.cases()[Math.max(0, Math.min(i, LONGUEUR - 1))]?.focus();
        },
        synchroniser() {
            this.$refs.code.value = this.chiffres.join('');
        },
        repartir(texte, depuis = 0) {
            const chiffres = String(texte).replace(/\D/g, '').slice(0, LONGUEUR - depuis).split('');
            chiffres.forEach((chiffre, k) => { this.chiffres[depuis + k] = chiffre; });
            this.synchroniser();
            return chiffres.length;
        },

        saisir(i, e) {
            const valeur = e.target.value.replace(/\D/g, '');
            if (valeur.length > 1) {
                this.aller(i + this.repartir(valeur, i));
                return;
            }
            this.chiffres[i] = valeur;
            e.target.value = valeur;
            this.synchroniser();
            if (valeur !== '') this.aller(i + 1);
        },
        effacer(i, e) {
            if (this.chiffres[i] !== '') return;
            e.preventDefault();
            this.chiffres[i - 1] = '';
            this.synchroniser();
            this.aller(i - 1);
        },
        coller(e) {
            this.chiffres = Array(LONGUEUR).fill('');
            const nombre = this.repartir(e.clipboardData?.getData('text') ?? '');
            this.aller(nombre);
        },
    };
}
