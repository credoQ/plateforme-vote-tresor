{{-- Photo d'une candidate : la photo choisie se recadre directement dans la carte du bulletin (recadrage-photo.js),
     puis part au serveur, déjà découpée. Rien n'est envoyé avant « Enregistrer la photo ». --}}
<div x-data="recadragePhoto" x-on:photo-candidate-ouverte.window="liberer()" x-on:photo-candidate-modifiee.window="liberer()">
    {{-- La fenêtre est ouverte tant que le composant a une candidate (candidateId) ; « Annuler » la referme sans requête. --}}
    <div class="voile" x-show="$wire.candidateId !== null" x-cloak x-on:keydown.escape.window="$wire.candidateId !== null && fermer()" x-on:click.self="fermer()">
        @if ($this->candidate)
            <div class="modale modale--photo" role="dialog" aria-modal="true" aria-labelledby="titre-photo">
                <div class="modale__entete">
                    <span class="pastille-icone"><i data-lucide="image-plus"></i></span>
                    <div>
                        <h2 id="titre-photo">Photo de {{ $this->candidate['candidate']->agent->nomComplet() }}</h2>
                        <p>Elle apparaît sur le bulletin. Faites glisser la photo pour centrer le visage dans la carte.</p>
                    </div>
                    <button type="button" class="icone-bouton modale__fermer" aria-label="Fermer" x-on:click="fermer()"><i data-lucide="x"></i></button>
                </div>

                <div class="modale__corps recadrage-photo">
                    <div class="carte-photo recadrage-photo__carte" x-ref="carte" x-bind:class="{ 'est-deplacable': image }"
                         x-on:pointerdown="commencer($event)" x-on:pointermove="deplacer($event)" x-on:pointerup="terminer()" x-on:pointercancel="terminer()"
                         x-on:keydown="clavier($event)" x-bind:tabindex="image ? 0 : -1" aria-label="Cadrage de la photo : flèches pour la déplacer">
                        <template x-if="image">
                            <img class="carte-photo__image--libre" x-bind:src="source" x-bind:style="style" alt="" draggable="false">
                        </template>
                        @if ($this->candidate['candidate']->photo)
                            <img class="carte-photo__image" src="{{ $this->candidate['candidate']->urlPhoto() }}" alt="" x-show="! image">
                        @else
                            <span class="carte-photo__initiales" aria-hidden="true" x-show="! image">{{ $this->candidate['initiales'] }}</span>
                        @endif
                        <span class="carte-photo__legende">
                            <span class="carte-candidate__nom">{{ $this->candidate['candidate']->agent->nomComplet() }}</span>
                            <span class="carte-candidate__structure">{{ $this->candidate['structure'] }}</span>
                        </span>
                    </div>

                    <div class="recadrage-photo__outils">
                        <div class="recadrage-photo__depot" x-bind:class="{ 'est-survole': survole }"
                             x-on:dragover.prevent="survole = true" x-on:dragleave="survole = false" x-on:drop.prevent="choisir($event.dataTransfer.files[0])">
                            <i data-lucide="upload"></i>
                            <strong x-text="image ? 'Une autre photo ?' : 'Déposez une photo ici'"></strong>
                            <button type="button" class="btn btn-secondaire btn--sm" x-on:click="$refs.fichier.click()"><i data-lucide="folder-open"></i>Choisir un fichier</button>
                            <input type="file" class="sr-only" x-ref="fichier" accept="image/jpeg,image/png,image/webp" x-on:change="choisir($event.target.files[0])" aria-label="Choisir une photo">
                            <span class="aide">JPG, PNG ou WebP. Une photo de face, de préférence en portrait.</span>
                        </div>
                        <div class="recadrage-photo__zoom" x-show="image" x-cloak>
                            <i data-lucide="zoom-out"></i>
                            <input type="range" min="1" x-bind:max="zoomMaximal" step="0.01" x-bind:value="zoom" x-on:input="zoomer(+$event.target.value)" aria-label="Zoom">
                            <i data-lucide="zoom-in"></i>
                        </div>
                        @error('photo')<p class="erreur-champ">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="modale__pied">
                    @if ($this->candidate['candidate']->photo)
                        <button type="button" class="btn btn-tertiaire modale__pied-gauche" x-on:click="retirer()" x-bind:disabled="envoi"><i data-lucide="trash-2"></i>Retirer la photo</button>
                    @endif
                    <button type="button" class="btn btn-secondaire" x-on:click="fermer()">Annuler</button>
                    <button type="button" class="btn btn-primaire" x-on:click="envoyer()" x-bind:disabled="! image || envoi" x-bind:class="{ 'est-en-cours': envoi }"><i data-lucide="save"></i>Enregistrer la photo</button>
                </div>
            </div>
        @endif
    </div>
</div>
