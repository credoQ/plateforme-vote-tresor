// Recadrage de la photo d'une candidate (composant Alpine, fenêtre Livewire Scrutins\PhotoCandidate) : la photo
// choisie se place dans la carte du bulletin, on la fait glisser et on règle le zoom, puis elle est découpée au
// format exact de la carte et envoyée en JPEG. Rien n'est envoyé avant « Enregistrer la photo ».

// Taille de la photo enregistrée, au format de la carte du bulletin (4 × 4,6).
const LARGEUR = 600;
const HAUTEUR = 690;
const ZOOM_MAXIMAL = 4;

export default function recadragePhoto() {
    return {
        source: null,
        image: null,
        base: 0,
        zoom: 1,
        x: 0,
        y: 0,
        glisse: null,
        survole: false,
        envoi: false,
        zoomMaximal: ZOOM_MAXIMAL,

        // La fenêtre s'affiche tant que le composant Livewire a une candidate : la refermer, c'est l'oublier.
        fermer() {
            this.$wire.candidateId = null;
            this.liberer();
        },
        liberer() {
            this.envoi = false;
            if (this.source) URL.revokeObjectURL(this.source);
            this.source = null;
            this.image = null;
            this.zoom = 1;
            if (this.$refs.fichier) this.$refs.fichier.value = '';
        },

        choisir(fichier) {
            this.survole = false;
            if (!fichier) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(fichier.type)) {
                window.signalerErreur?.('La photo doit être au format JPG, PNG ou WebP.');
                return;
            }
            this.liberer();
            this.base = 0;
            this.source = URL.createObjectURL(fichier);
            const image = new Image();
            image.onload = () => {
                if (image.naturalWidth < 200 || image.naturalHeight < 200) {
                    window.signalerErreur?.('La photo est trop petite (200 pixels de côté au moins).');
                    this.liberer();
                    return;
                }
                this.image = image;
                this.$nextTick(() => this.cadrer());
            };
            image.onerror = () => {
                window.signalerErreur?.('Cette photo ne peut pas être lue. Essayez un autre fichier.');
                this.liberer();
            };
            image.src = this.source;
        },

        cadre() {
            return this.$refs.carte.getBoundingClientRect();
        },
        get echelle() {
            return this.base * this.zoom;
        },
        // Photo à la taille de la carte (elle la couvre entièrement), centrée, le haut du visage privilégié.
        cadrer(essais = 10) {
            const cadre = this.cadre();
            // Carte pas encore affichée (fenêtre en cours d'ouverture) : on recommence à l'image suivante.
            if (cadre.width === 0) {
                if (essais > 0) requestAnimationFrame(() => this.cadrer(essais - 1));
                return;
            }
            this.base = Math.max(cadre.width / this.image.naturalWidth, cadre.height / this.image.naturalHeight);
            this.zoom = 1;
            this.x = (cadre.width - this.image.naturalWidth * this.base) / 2;
            this.y = (cadre.height - this.image.naturalHeight * this.base) * 0.2;
            this.borner();
        },
        // La photo couvre toujours la carte : pas de bande vide.
        borner() {
            if (!this.image) return;
            const cadre = this.cadre();
            this.x = Math.min(0, Math.max(cadre.width - this.image.naturalWidth * this.echelle, this.x));
            this.y = Math.min(0, Math.max(cadre.height - this.image.naturalHeight * this.echelle, this.y));
        },
        get style() {
            if (!this.image) return '';
            return `width:${this.image.naturalWidth}px;height:${this.image.naturalHeight}px;transform:translate(${this.x}px,${this.y}px) scale(${this.echelle})`;
        },

        // Zoom autour du centre de la carte.
        zoomer(valeur) {
            if (!this.image || this.base === 0) return;
            const cadre = this.cadre();
            const avant = this.echelle;
            this.zoom = Math.min(Math.max(valeur, 1), ZOOM_MAXIMAL);
            const rapport = this.echelle / avant;
            this.x = cadre.width / 2 - (cadre.width / 2 - this.x) * rapport;
            this.y = cadre.height / 2 - (cadre.height / 2 - this.y) * rapport;
            this.borner();
        },

        commencer(e) {
            if (!this.image) return;
            this.glisse = { px: e.clientX, py: e.clientY, x: this.x, y: this.y };
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        deplacer(e) {
            if (!this.glisse) return;
            this.x = this.glisse.x + e.clientX - this.glisse.px;
            this.y = this.glisse.y + e.clientY - this.glisse.py;
            this.borner();
        },
        terminer() {
            this.glisse = null;
        },
        // Flèches du clavier : la photo se déplace de 10 pixels.
        clavier(e) {
            const pas = { ArrowLeft: [10, 0], ArrowRight: [-10, 0], ArrowUp: [0, 10], ArrowDown: [0, -10] }[e.key];
            if (!this.image || !pas) return;
            e.preventDefault();
            this.x += pas[0];
            this.y += pas[1];
            this.borner();
        },

        // Découpe la partie visible dans la carte, au format du bulletin, puis l'envoie au composant Livewire.
        envoyer() {
            if (!this.image || this.base === 0 || this.envoi) return;
            this.envoi = true;
            const cadre = this.cadre();
            const facteur = LARGEUR / cadre.width;
            const toile = document.createElement('canvas');
            toile.width = LARGEUR;
            toile.height = HAUTEUR;
            const contexte = toile.getContext('2d');
            contexte.imageSmoothingQuality = 'high';
            contexte.drawImage(this.image, this.x * facteur, this.y * facteur, this.image.naturalWidth * this.echelle * facteur, this.image.naturalHeight * this.echelle * facteur);

            toile.toBlob((donnees) => {
                if (!donnees) {
                    this.envoi = false;
                    window.signalerErreur?.('La photo n’a pas pu être préparée. Essayez un autre fichier.');
                    return;
                }
                const fichier = new File([donnees], 'photo.jpg', { type: 'image/jpeg' });
                this.$wire.upload('photo', fichier,
                    () => this.$wire.enregistrer().finally(() => { this.envoi = false; }),
                    () => {
                        this.envoi = false;
                        window.signalerErreur?.('La photo n’a pas pu être envoyée. Vérifiez votre connexion, puis recommencez.');
                    });
            }, 'image/jpeg', 0.9);
        },

        async retirer() {
            const confirme = await window.confirmer({
                titre: 'Retirer la photo ?',
                texte: 'Un avatar remplacera la photo sur le bulletin. Une autre photo pourra être ajoutée jusqu’à l’ouverture du scrutin.',
                bouton: 'Retirer la photo',
                danger: true,
            });
            if (confirme) this.$wire.retirer();
        },
    };
}
