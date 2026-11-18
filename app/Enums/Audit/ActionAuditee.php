<?php

namespace App\Enums\Audit;

/**
 * Opérations sensibles enregistrées au journal d'audit (R56). Le vote lui-même n'y figure jamais (R43, R57).
 */
enum ActionAuditee: string
{
    case Connexion = 'CONNEXION';
    case ConnexionEchouee = 'CONNEXION_ECHOUEE';
    case Deconnexion = 'DECONNEXION';
    case CompteEnregistre = 'COMPTE_ENREGISTRE';
    case MotDePasseReinitialise = 'MOT_DE_PASSE_REINITIALISE';
    case ComptesGeneres = 'COMPTES_GENERES';
    case ImportAgents = 'IMPORT_AGENTS';
    case EditionLancee = 'EDITION_LANCEE';
    case PhaseOuverte = 'PHASE_OUVERTE';
    case PhaseCloturee = 'PHASE_CLOTUREE';
    case ScrutinsGeneres = 'SCRUTINS_GENERES';
    case ListesArretees = 'LISTES_ARRETEES';
    case CandidateDesactivee = 'CANDIDATE_DESACTIVEE';
    case CandidateReactivee = 'CANDIDATE_REACTIVEE';
    case ScrutinOuvert = 'SCRUTIN_OUVERT';
    case ScrutinAnnule = 'SCRUTIN_ANNULE';
    case ScrutinFerme = 'SCRUTIN_FERME';
    case ScrutinProlonge = 'SCRUTIN_PROLONGE';
    case ScrutinDepouille = 'SCRUTIN_DEPOUILLE';
    case EgaliteTranchee = 'EGALITE_TRANCHEE';
    case ResultatsValides = 'RESULTATS_VALIDES';
    case ResultatsPublies = 'RESULTATS_PUBLIES';

    public function libelle(): string
    {
        return match ($this) {
            self::Connexion => 'Connexion',
            self::ConnexionEchouee => 'Connexion échouée',
            self::Deconnexion => 'Déconnexion',
            self::CompteEnregistre => 'Compte enregistré',
            self::MotDePasseReinitialise => 'Mot de passe réinitialisé',
            self::ComptesGeneres => 'Comptes électeurs générés',
            self::ImportAgents => 'Import des agents',
            self::EditionLancee => 'Édition lancée',
            self::PhaseOuverte => 'Phase ouverte',
            self::PhaseCloturee => 'Phase clôturée',
            self::ScrutinsGeneres => 'Scrutins générés',
            self::ListesArretees => 'Listes arrêtées',
            self::CandidateDesactivee => 'Candidate désactivée',
            self::CandidateReactivee => 'Candidate réactivée',
            self::ScrutinOuvert => 'Scrutin ouvert',
            self::ScrutinAnnule => 'Scrutin annulé',
            self::ScrutinFerme => 'Scrutin fermé',
            self::ScrutinProlonge => 'Scrutin prolongé',
            self::ScrutinDepouille => 'Scrutin dépouillé',
            self::EgaliteTranchee => 'Égalité départagée',
            self::ResultatsValides => 'Résultats validés',
            self::ResultatsPublies => 'Résultats publiés',
        };
    }
}
