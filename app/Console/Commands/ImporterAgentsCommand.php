<?php

namespace App\Console\Commands;

use App\Actions\Agents\ImporterAgents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

#[Signature('agents:importer {fichier : Chemin du fichier CSV des agents} {--date= : Date de début des affectations sans date (AAAA-MM-JJ, aujourd\'hui par défaut)}')]
#[Description('Importe les agents et leurs affectations depuis un fichier CSV (R11 à R13)')]
class ImporterAgentsCommand extends Command
{
    public function handle(ImporterAgents $importer): int
    {
        try {
            $date = $this->option('date') ? Carbon::createFromFormat('!Y-m-d', $this->option('date')) : null;
            $rapport = $importer->handle($this->argument('fichier'), $date);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Agents créés', (string) $rapport->agentsCrees);
        $this->components->twoColumnDetail('Agents mis à jour', (string) $rapport->agentsMisAJour);
        $this->components->twoColumnDetail('Nouvelles affectations', (string) $rapport->affectationsCreees);
        $this->components->twoColumnDetail('Lignes rejetées', (string) count($rapport->rejets));

        if ($rapport->rejets !== []) {
            $this->table(['Ligne', 'Motifs'], collect($rapport->rejets)
                ->map(fn (array $motifs, int $ligne): array => [$ligne, implode(' ', $motifs)])
                ->values()
                ->all());
        }

        return $rapport->rejets === [] ? self::SUCCESS : self::FAILURE;
    }
}
