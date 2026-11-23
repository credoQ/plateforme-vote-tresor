@props(['message'])
{{-- Action refusée par le serveur : fenêtre d'erreur SweetAlert (resources/js/alertes.js), affichée après la redirection.
     Le texte reste aussi dans la page pour les lecteurs d'écran, et visible sans JavaScript. --}}
<div class="alerte alerte--erreur espace-bloc alerte--fenetre" role="alert"><i data-lucide="circle-alert"></i><div>{{ $message }}</div></div>
<script>
    (() => {
        const afficher = () => window.signalerErreur?.(@js($message));
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', afficher) : afficher();
    })();
</script>
