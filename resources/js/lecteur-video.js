// Lecteur des vidéos de présentation de la page de connexion (composant Alpine). Une carte ou un lien envoie
// l'événement « ouvrir-video » avec { titre, src, apercu } ; la vidéo démarre, et s'arrête à la fermeture
// (croix, Échap, clic hors de la vidéo). Le focus revient sur l'élément qui a ouvert le lecteur.

export default function lecteurVideo() {
    return {
        ouvert: false,
        video: { titre: '', src: '', apercu: '' },
        declencheur: null,

        ouvrir(video) {
            this.declencheur = document.activeElement;
            this.video = video;
            this.ouvert = true;
            this.$nextTick(() => {
                this.$refs.fermer.focus();
                this.$refs.video.play().catch(() => { /* lecture automatique refusée : le bouton lecture reste disponible */ });
            });
        },

        fermer() {
            if (!this.ouvert) return;
            this.$refs.video.pause();
            this.ouvert = false;
            this.declencheur?.focus();
        },
    };
}
