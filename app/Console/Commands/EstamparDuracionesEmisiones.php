<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MasterProgramEmision;
use App\Models\RadioProgram;
use Illuminate\Console\Command;

final class EstamparDuracionesEmisiones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emisiones:estampar-duraciones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recupera las duraciones reales (⏱) de las emisiones a partir de los episodios de radio_programs';

    public function handle(): int
    {
        $this->info('Buscando episodios con duración registrada...');

        $episodes = RadioProgram::query()
            ->with('masterProgram')
            ->whereNotNull('fecha_emision')
            ->where('duration_seconds', '>', 0)
            ->whereNotNull('master_program_id')
            ->orderBy('fecha_emision')
            ->get();

        $actualizadas = 0;

        foreach ($episodes as $episode) {
            $master = $episode->masterProgram;
            if (! $master) {
                continue;
            }

            $fechaEmision = $episode->fecha_emision ? $episode->fecha_emision->format('Y-m-d') : null;
            $durationSeconds = (int) $episode->duration_seconds;

            $emision = MasterProgramEmision::syncRealDurationForMaster($master, $fechaEmision, $durationSeconds);
            if ($emision) {
                $actualizadas++;
                $this->line(sprintf(
                    '[%s] %s (%s) -> %d seg (%s)',
                    $master->name,
                    $episode->titulo_programa ?? 'Episodio #' . $episode->numero_episodio,
                    $fechaEmision,
                    $durationSeconds,
                    $emision->duracionRealFormateada() ?? ''
                ));
            }
        }

        $this->info(sprintf('Proceso finalizado. Total de emisiones actualizadas: %d', $actualizadas));

        return Command::SUCCESS;
    }
}
