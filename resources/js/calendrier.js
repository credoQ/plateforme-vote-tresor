// Calendrier des champs de date et de date-heure (Flatpickr, habillé aux couleurs de la charte dans composants.css).
// Tous les <input type="date"> et <input type="datetime-local"> en profitent sans changement des vues : le champ
// d'origine garde son nom, son wire:model et sa valeur au format du serveur (2026-11-26, 2026-11-26T08:00) ; un
// second champ affiche la date à la française (26/11/2026 08:00). Les bornes min et max sont respectées.
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';

const SELECTEUR = 'input[type="date"], input[type="datetime-local"], input[data-calendrier]';

// Le champ d'origine annonce le changement : wire:model, le récapitulatif des formulaires en étapes et les
// formulaires classiques lisent sa valeur.
function annoncer(champ) {
    champ.dispatchEvent(new Event('input', { bubbles: true }));
    champ.dispatchEvent(new Event('change', { bubbles: true }));
}

function boutonPied(libelle, classe, action) {
    const bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = `btn ${classe} btn--sm`;
    bouton.textContent = libelle;
    bouton.addEventListener('click', action);
    return bouton;
}

function equiper(champ) {
    const avecHeure = champ.type === 'datetime-local';
    champ.dataset.calendrier = champ.type;

    const calendrier = flatpickr(champ, {
        locale: French,
        enableTime: avecHeure,
        time_24hr: true,
        minuteIncrement: 5,
        dateFormat: avecHeure ? 'Y-m-d\\TH:i' : 'Y-m-d',
        altInput: true,
        altFormat: avecHeure ? 'd/m/Y H:i' : 'd/m/Y',
        altInputClass: `${champ.className} champ-calendrier`,
        allowInput: true,
        disableMobile: true,
        minDate: champ.min || null,
        maxDate: champ.max || null,
        onChange: () => annoncer(champ),
        onReady: (_dates, _texte, instance) => {
            const pied = document.createElement('div');
            pied.className = 'flatpickr-pied';
            pied.append(boutonPied('Effacer', 'btn-tertiaire', () => { instance.clear(); instance.close(); }));
            if (avecHeure) {
                const fuseau = document.createElement('span');
                fuseau.className = 'flatpickr-fuseau';
                fuseau.textContent = 'Heure du Bénin';
                pied.append(fuseau);
            }
            pied.append(boutonPied(avecHeure ? 'Maintenant' : 'Aujourd’hui', 'btn-secondaire', () => { instance.setDate(new Date(), true); }));
            instance.calendarContainer.append(pied);
        },
    });

    // Le champ affiché reprend l'identité du champ d'origine pour son libellé et sa validation.
    const affiche = calendrier.altInput;
    if (affiche) {
        affiche.placeholder = avecHeure ? 'jj/mm/aaaa hh:mm' : 'jj/mm/aaaa';
        affiche.required = champ.required;
        affiche.disabled = champ.disabled;
        if (champ.id) {
            affiche.id = `${champ.id}-affiche`;
            document.querySelector(`label[for="${champ.id}"]`)?.setAttribute('for', affiche.id);
        }
    }

    return calendrier;
}

// Équipe les champs de la page ; après une mise à jour Livewire qui a remplacé le champ affiché, le calendrier est
// recréé (le champ d'origine est revenu à son type date).
export function preparerCalendriers(racine = document) {
    racine.querySelectorAll(SELECTEUR).forEach((champ) => {
        if (champ._flatpickr && champ._flatpickr.altInput?.isConnected) return;
        // La mise à jour Livewire a pu vider le champ redessiné : la date retenue par l'ancien calendrier est reprise.
        const retenues = champ._flatpickr?.selectedDates ?? [];
        champ._flatpickr?.destroy();
        if (champ.dataset.calendrier) champ.type = champ.dataset.calendrier;
        const calendrier = equiper(champ);
        if (champ.value === '' && retenues.length > 0) calendrier.setDate(retenues, false);
    });
}
