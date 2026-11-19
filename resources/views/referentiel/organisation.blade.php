<x-layouts.app titre="Types et départements" :ariane="['Administration', 'Structures', 'Types et départements']" actif="structures">
    <div class="titre-page">
        <div>
            <h1>Structures</h1>
        </div>
    </div>

    <div class="carte espace-bloc">
        <div class="onglets">
            <a href="{{ route('structures.index') }}" class="onglet">Structures</a>
            <a href="{{ route('structures.organisation') }}" class="onglet est-actif">Types et départements</a>
        </div>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Pôle électoral</th><th>Type de structure</th><th>Code</th><th class="nombre">Structures actives</th></tr></thead>
                <tbody>
                    @foreach ($poles as $pole)
                        @foreach ($pole->typesStructure as $type)
                            <tr>
                                <td>{{ $pole->code->libelle() }}</td>
                                <td>{{ $type->code->libelle() }}</td>
                                <td class="reference">{{ $type->code->value }}</td>
                                <td class="nombre"><a href="{{ route('structures.index', ['type' => $type->id]) }}">{{ number_format($type->structures_count, 0, ',', ' ') }}</a></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <section class="carte">
        <div class="carte__entete"><h2>Départements</h2></div>
        <div class="tableau-defilant">
            <table class="tableau tableau--fiches">
                <thead><tr><th>Code</th><th>Département</th><th>Trésorerie départementale</th><th class="nombre">Trésoreries communales</th></tr></thead>
                <tbody>
                    @foreach ($departements as $departement)
                        <tr>
                            <td class="reference">{{ $departement->code }}</td>
                            <td>{{ $departement->libelle }}</td>
                            <td>
                                @if ($departement->tresorerieDepartementale)
                                    <a href="{{ route('structures.show', $departement->tresorerieDepartementale) }}">{{ $departement->tresorerieDepartementale->code }}</a>
                                @else
                                    <span class="badge badge--attente">Aucune</span>
                                @endif
                            </td>
                            <td class="nombre"><a href="{{ route('structures.index', ['departement' => $departement->id]) }}">{{ $departement->tresoreries_communales_count }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
