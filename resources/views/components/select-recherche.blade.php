@props(['placeholder' => 'Choisir…', 'soumettre' => false, 'compact' => false])
{{-- Liste déroulante avec recherche autour d'un <select> natif (resources/js/select-recherche.js) :
     <x-select-recherche><select id="structure_id" name="structure_id" class="champ">…</select></x-select-recherche>
     soumettre : envoie le formulaire au choix (barres de filtres). compact : hauteur des barres de filtres.
     data-detail sur une <option> : seconde ligne de l'option ; <optgroup> : groupes de la liste. --}}
<div {{ $attributes->class(['select-recherche', 'select-recherche--compact' => $compact]) }}
     x-data="selectRecherche({ placeholder: @js($placeholder), soumettre: @js((bool) $soumettre) })"
     x-on:click.outside="fermer()"
     x-on:scroll.window.capture="if (ouvert && ! $refs.panneau.contains($event.target)) placer()"
     x-on:resize.window="if (ouvert) placer()">
    {{ $slot }}

    <button type="button" x-ref="bouton" x-cloak
            class="champ select-recherche__champ" x-bind:class="{ 'est-erreur': erreur, 'est-ouvert': ouvert }"
            aria-haspopup="listbox" x-bind:aria-expanded="ouvert"
            x-bind:disabled="desactive"
            x-on:click="basculer()" x-on:keydown.arrow-down.prevent="ouvrir()" x-on:keydown.escape="fermer()">
        <span class="select-recherche__valeur">
            <template x-if="multiple && valeurs.length">
                <span class="select-recherche__puces">
                    <template x-for="valeur in valeurs" x-bind:key="valeur">
                        <span class="select-recherche__puce">
                            <span x-text="libelleDe(valeur)"></span>
                            <span role="button" tabindex="-1" class="select-recherche__retirer" x-on:click.stop="retirer(valeur)" x-bind:aria-label="'Retirer ' + libelleDe(valeur)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </span>
                        </span>
                    </template>
                </span>
            </template>
            <span x-show="! multiple || ! valeurs.length" x-bind:class="{ 'est-vide': estVide }" x-text="libelleAffiche"></span>
            <small x-show="choisie && choisie.detail" x-text="choisie?.detail"></small>
        </span>
        <span x-show="effacable" role="button" tabindex="-1" class="select-recherche__effacer" x-on:click.stop="effacer()" aria-label="Effacer le choix">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </span>
        <svg class="select-recherche__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    {{-- Liste flottante (position fixe) : jamais coupée par un tableau défilant ni une fenêtre. --}}
    <div x-ref="panneau" x-show="ouvert" x-cloak x-bind:style="position" class="select-recherche__panneau"
         x-on:keydown.arrow-down.prevent="deplacer(1)" x-on:keydown.arrow-up.prevent="deplacer(-1)"
         x-on:keydown.enter.prevent="choisirActif()" x-on:keydown.tab="fermer()"
         x-on:keydown.escape.prevent.stop="fermer(true)">
        <div class="select-recherche__recherche" x-show="avecRecherche">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input x-ref="saisie" type="text" class="champ" autocomplete="off" placeholder="Rechercher…" aria-label="Rechercher dans la liste" x-model="recherche">
        </div>
        <ul x-ref="liste" role="listbox" tabindex="-1" x-bind:aria-multiselectable="multiple">
            <template x-for="(option, i) in (ouvert ? resultats : [])" x-bind:key="option.valeur">
                <li>
                    <p x-show="nouveauGroupe(i)" class="select-recherche__groupe" x-text="option.groupe"></p>
                    <div role="option" class="select-recherche__option"
                         x-bind:aria-selected="estChoisie(option)" x-bind:aria-disabled="option.desactivee" x-bind:data-actif="i === actif"
                         x-on:mouseenter="actif = i" x-on:mousedown.prevent="choisir(option)">
                        <span>
                            <span x-text="option.libelle"></span>
                            <small x-show="option.detail" x-text="option.detail"></small>
                        </span>
                        <svg x-show="estChoisie(option)" class="select-recherche__coche" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                </li>
            </template>
            <li x-show="total > resultats.length" class="select-recherche__info" x-text="'… et ' + (total - resultats.length) + ' autres : précisez la recherche'"></li>
            <li x-show="total === 0" class="select-recherche__info">Aucun résultat</li>
        </ul>
    </div>
</div>
