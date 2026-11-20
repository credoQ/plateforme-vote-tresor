@props(['message', 'ton' => 'succes'])
{{-- Message passager (toast SweetAlert, resources/js/alertes.js), laissé en session avant une redirection.
     Le texte reste aussi dans la page pour les lecteurs d'écran et sans JavaScript. --}}
<p class="sr-only" role="status">{{ $message }}</p>
<script>
    (() => {
        const afficher = () => window.notifier?.(@js($message), @js($ton));
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', afficher) : afficher();
    })();
</script>
