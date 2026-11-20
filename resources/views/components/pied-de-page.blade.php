{{-- Pied de page institutionnel des écrans : ministère de tutelle et copyright de l'année (config/app.php). --}}
<footer {{ $attributes->class('pied-de-page') }}>
    <span>République du Bénin · {{ config('app.ministere') }}</span>
    <span>© {{ date('Y') }} {{ config('app.copyright') }}</span>
</footer>
