<x-mail::message>
# Votre vote est enregistré

Bonjour {{ $inscription->agent->prenoms }} {{ $inscription->agent->nom }}, merci d’avoir participé : {{ $scrutin->phase->libelle }}, {{ $scrutin->phase->edition->libelle }}.

<table class="recapitulatif" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td class="recapitulatif-libelle">Accusé n°</td><td class="recapitulatif-valeur">{{ $inscription->numero_accuse }}</td></tr>
<tr><td class="recapitulatif-libelle">Scrutin</td><td class="recapitulatif-valeur">{{ $scrutin->libelle }}</td></tr>
<tr><td class="recapitulatif-libelle">Date et heure</td><td class="recapitulatif-valeur">{{ $inscription->creneauDeVote() }}</td></tr>
</table>

Votre accusé de vote est joint à ce message, au format PDF. Il atteste votre participation et ne mentionne pas votre choix, qui reste secret.

Cordialement,<br>
**Le comité d’organisation de la Femme modèle du Trésor**
</x-mail::message>
