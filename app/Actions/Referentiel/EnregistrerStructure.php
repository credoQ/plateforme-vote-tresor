<?php

namespace App\Actions\Referentiel;

use App\Enums\Referentiel\CodeTypeStructure;
use App\Models\Structure;
use App\Models\TypeStructure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crée ou met à jour une structure du référentiel en appliquant les règles R5, R8 et R9.
 */
class EnregistrerStructure
{
    /**
     * @param  array{code: string, sigle?: string|null, libelle: string, niveau?: int, actif?: bool, type_structure_id: int, departement_id?: int|null, structure_parente_id?: int|null}  $donnees
     *
     * @throws ValidationException
     */
    public function handle(array $donnees, ?Structure $structure = null): Structure
    {
        $donnees = Validator::make($donnees, [
            'code' => ['required', 'string', 'max:30', Rule::unique('structures', 'code')->ignore($structure?->id)],
            'sigle' => ['nullable', 'string', 'max:20'],
            'libelle' => ['required', 'string', 'max:160'],
            'niveau' => ['sometimes', 'integer', Rule::in([Structure::NIVEAU_STRUCTURE_ELECTORALE, Structure::NIVEAU_SERVICE])],
            'actif' => ['sometimes', 'boolean'],
            'type_structure_id' => ['required', 'integer', 'exists:types_structure,id'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'structure_parente_id' => ['nullable', 'integer', 'exists:structures,id'],
        ], [
            'code.unique' => 'Ce code de structure est déjà utilisé.',
            'required' => 'Le champ :attribute est obligatoire.',
            'exists' => 'La valeur du champ :attribute est inconnue.',
        ])->validate();

        $this->verifierRattachements($donnees, $structure);

        $structure ??= new Structure;
        $structure->fill($donnees)->save();

        return $structure;
    }

    /**
     * @param  array<string, mixed>  $donnees
     *
     * @throws ValidationException
     */
    private function verifierRattachements(array $donnees, ?Structure $structure): void
    {
        $type = TypeStructure::findOrFail($donnees['type_structure_id'])->code;
        $niveau = (int) ($donnees['niveau'] ?? Structure::NIVEAU_STRUCTURE_ELECTORALE);
        $departementId = $donnees['departement_id'] ?? null;
        $parente = isset($donnees['structure_parente_id'])
            ? Structure::with('typeStructure')->find($donnees['structure_parente_id'])
            : null;

        // R9 : une TD ou une Trésorerie communale est située dans un département.
        if ($type->exigeUnDepartement() && $departementId === null) {
            $this->echouer('departement_id', 'Une Trésorerie départementale ou communale doit être située dans un département.');
        }

        if ($parente !== null && $structure !== null && $parente->is($structure)) {
            $this->echouer('structure_parente_id', 'Une structure ne peut pas être rattachée à elle-même.');
        }

        if ($parente !== null && $structure !== null && $this->estDescendante($parente, $structure)) {
            $this->echouer('structure_parente_id', 'Une structure ne peut pas être rattachée à l’une de ses structures rattachées.');
        }

        if ($parente !== null && ! $parente->actif && $parente->id !== $structure?->structure_parente_id) {
            $this->echouer('structure_parente_id', 'La structure de rattachement est désactivée.');
        }

        // Un service ne porte pas lui-même de structures rattachées (R8, R10).
        if ($niveau === Structure::NIVEAU_SERVICE && $structure?->structuresFilles()->exists()) {
            $this->echouer('niveau', 'Cette structure a des structures rattachées : elle ne peut pas devenir un service.');
        }

        // R8 : un service est rattaché à une structure électorale.
        if ($niveau === Structure::NIVEAU_SERVICE
            && ($parente === null || $parente->niveau !== Structure::NIVEAU_STRUCTURE_ELECTORALE)) {
            $this->echouer('structure_parente_id', 'Un service doit être rattaché à une structure électorale de niveau 1.');
        }

        // R8 : une Trésorerie communale est rattachée à la Trésorerie départementale de son département.
        if ($type === CodeTypeStructure::TresorerieCommunale) {
            if ($parente === null || $parente->typeStructure->code !== CodeTypeStructure::TresorerieDepartementale) {
                $this->echouer('structure_parente_id', 'Une Trésorerie communale doit être rattachée à une Trésorerie départementale.');
            }

            if ($parente->departement_id !== $departementId) {
                $this->echouer('departement_id', 'Une Trésorerie communale doit être dans le même département que sa Trésorerie départementale.');
            }
        }
    }

    /**
     * Vrai si $candidate figure sous $structure dans la hiérarchie : la rattacher à elle créerait une boucle.
     */
    private function estDescendante(Structure $candidate, Structure $structure): bool
    {
        $vues = [];
        $courante = $candidate;

        while ($courante->structure_parente_id !== null && ! in_array($courante->id, $vues, true)) {
            if ($courante->structure_parente_id === $structure->id) {
                return true;
            }

            $vues[] = $courante->id;
            $courante = Structure::find($courante->structure_parente_id);

            if ($courante === null) {
                return false;
            }
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    private function echouer(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}
