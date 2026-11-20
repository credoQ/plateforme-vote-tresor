<x-mail::message>
# Bonjour,

Voici votre code de connexion à la plateforme de vote de la Femme modèle du Trésor.

<x-mail::code :validite="'Valable '.$minutes.' minutes, pour une seule connexion.'">
{{ $code }}
</x-mail::code>

Ne communiquez ce code à personne : aucun agent du Trésor ne vous le demandera.

Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.

Cordialement,<br>
**Le comité d’organisation de la Femme modèle du Trésor**
</x-mail::message>
