<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RadioPlayerService;
use App\Support\RadioPlayerStateStore;
use Illuminate\Console\Command;

final class PlayerDumpStateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'player:dump-state {--json : Imprimir solamente la salida en formato JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra el estado en crudo guardado, el diagnóstico de coincidencia de programa y el JSON final de /api/player/status';

    public function handle(RadioPlayerService $playerService, RadioPlayerStateStore $stateStore): int
    {
        $rawState = $stateStore->read();
        $status = $playerService->resolve();
        $diagnosis = $playerService->getLastDiagnosis();

        if ($this->option('json')) {
            $this->line(json_encode([
                'raw_state' => $rawState,
                'diagnosis' => $diagnosis,
                'status' => $status,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('================================================================');
        $this->info(' 1. ESTADO CRUDO GUARDADO (nowplaying.json)');
        $this->info('================================================================');
        if (empty($rawState)) {
            $this->warn('(El archivo de estado está vacío o no existe aún)');
        } else {
            $this->line(json_encode($rawState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        $this->info('================================================================');
        $this->info(' 2. DIAGNÓSTICO DE COINCIDENCIA DE PROGRAMA');
        $this->info('================================================================');

        $matchFound = ! empty($diagnosis['match_found']);
        $matchVia = $diagnosis['match_via'] ?? 'ninguna';

        $this->table(
            ['Campo', 'Valor'],
            [
                ['¿Programa empatado?', $matchFound ? '<fg=green;options=bold>SÍ</>' : '<fg=yellow;options=bold>NO</>'],
                ['Vía de coincidencia', $matchFound ? "<fg=cyan;options=bold>{$matchVia}</>" : 'ninguna'],
                ['Programa empatado', ($diagnosis['matched_program'] ?? 'null') . ($diagnosis['matched_program_id'] ? " (ID: {$diagnosis['matched_program_id']})" : '')],
                ['Episodio empatado', ($diagnosis['matched_episode'] ?? 'null') . ($diagnosis['matched_episode_id'] ? " (ID: {$diagnosis['matched_episode_id']})" : '')],
                ['Bloque programa activo', ! empty($status['es_bloque_programa']) ? '<fg=green>true</>' : '<fg=gray>false</>'],
                ['Track Title resultante', $status['track']['title'] ?? 'null'],
                ['Track Artist resultante', $status['track']['artist'] ?? 'null'],
            ]
        );

        $this->newLine();
        $this->comment('--- Detalle de Vías Probadas ---');
        $vias = $diagnosis['vias'] ?? [];
        $this->table(
            ['Vía', 'Entrada evaluada y resultado'],
            [
                ['Fichero (file/filename/song)', $vias['fichero'] ?? 'ninguno'],
                ['Carátula (cover/artwork)', $vias['caratula'] ?? 'ninguna'],
                ['Álbum (album)', $vias['album'] ?? 'ninguno'],
                ['Texto (title/artist/host)', $vias['texto'] ?? 'ninguno'],
                ['Horario (emisión/ventana)', $vias['horario'] ?? 'ninguno'],
            ]
        );

        $this->newLine();
        $this->info('================================================================');
        $this->info(' 3. JSON FINAL DE /api/player/status');
        $this->info('================================================================');
        $this->line(json_encode([
            'success' => true,
            'data' => $status,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->newLine();

        return Command::SUCCESS;
    }
}
