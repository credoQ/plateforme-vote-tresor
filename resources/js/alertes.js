// SweetAlert2 aux couleurs de la charte : notifier(message, ton) pour les messages passagers,
// confirmer({ titre, texte, bouton, danger }) pour une question, et les confirmations des actions sensibles.
// Tons : succes, erreur, attente, info.
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const ICONES = { succes: 'success', erreur: 'error', attente: 'warning', info: 'info' };

const classes = (danger = false) => ({
    container: 'fm-swal-conteneur',
    popup: 'fm-swal',
    confirmButton: danger ? 'btn btn-danger' : 'btn btn-primaire',
    cancelButton: 'btn btn-secondaire',
    actions: 'fm-swal-actions',
    validationMessage: 'fm-swal-validation',
});

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    closeButtonAriaLabel: 'Fermer le message',
    timer: 6000,
    timerProgressBar: true,
    customClass: { container: 'fm-swal-conteneur fm-swal-conteneur--toast', popup: 'fm-swal fm-swal-toast' },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

export function notifier(message, ton = 'succes') {
    if (!message) return;
    Toast.fire({ icon: ICONES[ton] ?? 'success', title: message });
}

export function confirmer({ titre, texte = '', html = null, bouton = 'Confirmer', annuler = 'Annuler', danger = false, preConfirm = undefined } = {}) {
    return Swal.fire({
        icon: danger ? 'warning' : 'question',
        title: titre,
        text: html ? undefined : texte,
        html: html ?? undefined,
        showCancelButton: true,
        confirmButtonText: bouton,
        cancelButtonText: annuler,
        reverseButtons: true,      // Annuler à gauche, l'action à droite, comme dans les fenêtres de l'application
        focusCancel: danger,       // une action destructrice n'est jamais validée par un simple Entrée
        buttonsStyling: false,
        customClass: classes(danger),
        preConfirm,
    }).then((resultat) => resultat.isConfirmed);
}

/**
 * Confirmation avec un motif facultatif (au plus 500 caractères) : renvoie { confirme, motif }.
 */
export async function demanderMotif({ titre, texte = '', bouton = 'Confirmer', annuler = 'Annuler', danger = true } = {}) {
    const resultat = await Swal.fire({
        icon: danger ? 'warning' : 'question',
        title: titre,
        text: texte,
        input: 'textarea',
        inputLabel: 'Motif (facultatif)',
        inputAttributes: { maxlength: '500' },
        showCancelButton: true,
        confirmButtonText: bouton,
        cancelButtonText: annuler,
        reverseButtons: true,
        focusCancel: danger,
        buttonsStyling: false,
        customClass: { ...classes(danger), input: 'champ', inputLabel: 'libelle' },
    });

    return { confirme: resultat.isConfirmed, motif: resultat.isConfirmed ? resultat.value : null };
}

window.notifier = notifier;
window.confirmer = confirmer;
window.demanderMotif = demanderMotif;

// Message envoyé par un composant Livewire : $this->dispatch('notifier', message: '…', ton: 'succes').
window.addEventListener('notifier', (e) => notifier(e.detail?.message, e.detail?.ton));

/**
 * Actions sensibles : <x-modale-confirmation> produit un formulaire masqué (form.confirmation) que le bouton
 * data-ouvrir="id" déclenche. La confirmation s'ouvre dans SweetAlert avec le titre, le texte et, s'il y en a,
 * les champs du formulaire (motif…), déplacés dans la fenêtre le temps de la saisie puis remis dans le formulaire.
 */
export async function demanderConfirmation(formulaire) {
    const champs = formulaire.querySelector('.confirmation__champs');
    const avecChamps = champs !== null && champs.children.length > 0;
    const contenu = document.createElement('div');
    contenu.className = 'fm-swal-contenu';
    const texte = document.createElement('p');
    texte.textContent = formulaire.querySelector('.confirmation__texte')?.textContent.trim() ?? '';
    contenu.append(texte);
    if (avecChamps) contenu.append(champs);

    const confirme = await confirmer({
        titre: formulaire.dataset.titre,
        html: contenu,
        bouton: formulaire.dataset.bouton,
        danger: formulaire.dataset.danger !== undefined,
        preConfirm: () => {
            const invalide = avecChamps ? champs.querySelector(':invalid') : null;
            if (invalide) {
                Swal.showValidationMessage(invalide.validationMessage || 'Renseignez ce champ.');
                invalide.focus();
                return false;
            }
            return true;
        },
    });

    if (avecChamps) formulaire.append(champs);
    if (confirme) formulaire.requestSubmit();
}

document.addEventListener('click', (e) => {
    const declencheur = e.target.closest('[data-ouvrir]');
    const cible = declencheur ? document.getElementById(declencheur.dataset.ouvrir) : null;
    if (cible?.matches('form.confirmation')) {
        e.preventDefault();
        // Une seule fenêtre pour toutes les lignes d'une liste : le bouton donne l'adresse et le titre de sa ligne.
        if (declencheur.dataset.action) cible.action = declencheur.dataset.action;
        if (declencheur.dataset.titre) cible.dataset.titre = declencheur.dataset.titre;
        demanderConfirmation(cible);
    }
});
