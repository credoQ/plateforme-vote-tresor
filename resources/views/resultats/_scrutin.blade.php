@php
    use App\Enums\Scrutins\StatutScrutin;
    $pv = $scrutin->procesVerbal;
    $voitResultats = $pv !== null && ($scrutin->statut === StatutScrutin::ResultatsPublies || $utilisateur->can('piloter') || $utilisateur->can('observer'));
    $nonValide = $scrutin->statut === StatutScrutin::Clos;
    $actives = $candidates->filter->estActive()->sortBy([['rang', 'asc']]);
    $rangEnBalance = $pv?->egalite_a_departager ? $actives->where('est_laureate', false)->min('rang') : null;
    $exAequo = $rangEnBalance === null ? collect() : $actives->where('rang', $rangEnBalance);
    $placesRestantes = $scrutin->phase->nb_qualifiees - $actives->where('est_laureate', true)->count();
    $fermetureDepassee = $scrutin->statut === StatutScrutin::Ouvert && $scrutin->date_heure_fermeture?->isPast();
@endphp
@if ($pilote && ($fermetureDepassee || ($nonValide && $pv === null)))
    <section class="carte espace-bloc" id="resultats">
        <div class="carte__entete"><h2>Dépouillement</h2></div>
        <div class="carte__corps">
            @if ($fermetureDepassee)
                <p>L’heure de fermeture est passée ; le planificateur fermera le scrutin à sa prochaine minute.</p>
                <form method="POST" action="{{ route('scrutins.fermer', $scrutin) }}">@csrf<button type="submit" class="btn btn-secondaire"><i data-lucide="lock"></i>Fermer le scrutin maintenant</button></form>
            @else
                <p>Le scrutin est clos. Le dépouillement contrôle l’urne (chaîne des empreintes, cohérence avec l’émargement) puis calcule les résultats.</p>
                <form method="POST" action="{{ route('resultats.depouiller', $scrutin) }}">@csrf<button type="submit" class="btn btn-primaire"><i data-lucide="calculator"></i>Dépouiller le scrutin</button></form>
            @endif
        </div>
    </section>
@endif

@if ($voitResultats)
    <section class="carte espace-bloc" id="resultats">
        <div class="carte__entete">
            <h2>Résultats {{ $scrutin->statut === StatutScrutin::ResultatsPublies ? 'publiés' : ($pv->numero ? 'validés' : 'provisoires') }}</h2>
            @if ($pv->numero)<span class="badge badge--primaire">PV n° {{ $pv->numero }}</span>@endif
        </div>
        <div class="carte__corps">
            <p>
                {{ $pv->nb_inscrits }} inscrits · {{ $pv->nb_votants }} votants · {{ $pv->nb_blancs }} blanc(s) · {{ $pv->nb_exprimes }} suffrages exprimés ·
                participation {{ number_format((float) $pv->taux_participation, 2, ',', ' ') }} %
                @if ($scrutin->quorum !== null) · quorum de {{ number_format((float) $scrutin->quorum, 0, ',', ' ') }} % {{ $pv->quorum_atteint ? 'atteint' : 'non atteint' }} @endif
            </p>
            @if ($pv->motif_departage)<p class="aide">Départage par le comité : {{ $pv->motif_departage }}</p>@endif
        </div>
        <div class="tableau-defilant">
            <table class="tableau">
                <thead><tr><th class="nombre">Rang</th><th>Candidate</th><th class="nombre">Voix</th><th class="nombre">%</th><th>Résultat</th></tr></thead>
                <tbody>
                    @foreach ($actives as $candidate)
                        <tr>
                            <td class="nombre">{{ $candidate->rang }}</td>
                            <td>{{ $candidate->agent->nomComplet() }}<div class="aide">{{ $candidate->agent->matricule }}</div></td>
                            <td class="nombre">{{ $candidate->nb_voix }}</td>
                            <td class="nombre">{{ number_format((float) $candidate->pourcentage, 2, ',', ' ') }}</td>
                            <td>
                                @if ($candidate->est_laureate)<span class="badge badge--accent">Lauréate</span>@endif
                                @if ($candidate->rang === $rangEnBalance)<span class="badge badge--attente">Ex-æquo</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($pilote)
            <div class="carte__corps">
                @if ($nonValide && $pv->egalite_a_departager)
                    <div class="alerte alerte--attente espace-bloc"><i data-lucide="scale"></i>
                        <div><strong>Égalité à départager.</strong> Retenez {{ $placesRestantes }} candidate(s) parmi les ex-æquo, selon la modalité arrêtée par le comité, et motivez la décision.</div>
                    </div>
                    <form method="POST" action="{{ route('resultats.trancher', $scrutin) }}" class="formulaire">
                        @csrf
                        <div class="formulaire__cases">
                            @foreach ($exAequo as $candidate)
                                <label class="case"><input type="checkbox" name="retenues[]" value="{{ $candidate->id }}"> {{ $candidate->agent->nomComplet() }}</label>
                            @endforeach
                        </div>
                        <div class="formulaire__groupe">
                            <label class="libelle" for="motif-departage">Motif du départage <span class="requis">*</span></label>
                            <textarea id="motif-departage" name="motif" class="champ" maxlength="1000" required placeholder="Ex. : second tour du 20 novembre entre les ex-æquo"></textarea>
                        </div>
                        <div class="formulaire__actions"><button type="submit" class="btn btn-primaire"><i data-lucide="scale"></i>Enregistrer le départage</button></div>
                    </form>
                @endif

                @if ($nonValide && ! $pv->quorum_atteint)
                    <form method="POST" action="{{ route('resultats.prolonger', $scrutin) }}" class="formulaire-ligne">
                        @csrf
                        <div class="formulaire__groupe">
                            <label class="libelle" for="prolongation">Prolonger jusqu’au (heure du Bénin)</label>
                            <input id="prolongation" name="fermeture" type="datetime-local" class="champ" required>
                            @error('fermeture')<p class="erreur-champ">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-secondaire"><i data-lucide="clock-plus"></i>Prolonger le scrutin</button>
                    </form>
                @endif

                <div class="formulaire__actions">
                    @if ($nonValide)
                        <form method="POST" action="{{ route('resultats.depouiller', $scrutin) }}">@csrf<button type="submit" class="btn btn-tertiaire"><i data-lucide="refresh-cw"></i>Relancer le dépouillement</button></form>
                        @unless ($pv->egalite_a_departager)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-valider"><i data-lucide="badge-check"></i>Valider les résultats</button>
                        @endunless
                    @elseif ($scrutin->statut === StatutScrutin::ResultatsValides)
                        @if ($pv->fichier)
                            <button type="button" class="btn btn-primaire" data-ouvrir="modale-publier"><i data-lucide="megaphone"></i>Publier les résultats</button>
                        @else
                            <p class="aide">Le procès-verbal est en cours de génération ; la publication sera possible ensuite.</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        @if ($pv->fichier)
            <div class="carte__corps formulaire__actions">
                <a href="{{ route('resultats.proces-verbal', $scrutin) }}" class="btn btn-secondaire" target="_blank" rel="noopener"><i data-lucide="file-text"></i>Ouvrir le procès-verbal</a>
                @if ($scrutin->statut === StatutScrutin::ResultatsPublies)
                    <a href="{{ route('exports.resultats', $scrutin) }}" class="btn btn-tertiaire"><i data-lucide="download"></i>Exporter les résultats</a>
                @endif
            </div>
        @endif
    </section>
@endif

@if ($pilote && $pv !== null)
    @if ($nonValide && ! $pv->egalite_a_departager)
        <x-modale-confirmation id="modale-valider" :titre="'Valider les résultats de « '.$scrutin->libelle.' » ?'" :action="route('resultats.valider', $scrutin)" bouton="Valider les résultats" icone="badge-check">
            Le procès-verbal recevra son numéro et sera généré. Les résultats ne pourront plus être recalculés ni le scrutin prolongé.
        </x-modale-confirmation>
    @elseif ($scrutin->statut === StatutScrutin::ResultatsValides && $pv->fichier)
        <x-modale-confirmation id="modale-publier" :titre="'Publier les résultats de « '.$scrutin->libelle.' » ?'" :action="route('resultats.publier', $scrutin)" bouton="Publier les résultats" icone="megaphone">
            Les résultats et le procès-verbal n° {{ $pv->numero }} deviendront définitifs et visibles de tous. Les inscrits seront prévenus et les lauréates qualifiées pour la phase suivante.
        </x-modale-confirmation>
    @endif
@endif
