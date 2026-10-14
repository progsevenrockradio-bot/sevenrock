<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MasterProgramEmision;
use Illuminate\Console\Command;

final class AuditarHorasEmisiones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emisiones:auditar-horas {--all : Mostrar todas las emisiones, no solo las sospechosas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audita las emisiones semanales y detecta horarios sospechosos con respecto a la hora del programa';

    public function handle(): int
    {
        $showAll = (bool) $this->option('all');

        $emisiones = MasterProgramEmision::query()
            ->with('masterProgram')
            ->get();

        $rows = [];
        $totalSospechosas = 0;

        foreach ($emisiones as $emision) {
            $master = $emision->masterProgram;
            if (! $master) {
                continue;
            }

            $horaEmisionStr = trim((string) $emision->hora_inicio);
            $horaMasterStr  = trim((string) $master->hora_transmision);

            $motivos = [];

            if ($horaEmisionStr !== '' && $horaMasterStr !== '') {
                $minEmision = $this->timeToMinutes($horaEmisionStr);
                $minMaster  = $this->timeToMinutes($horaMasterStr);

                if ($minEmision !== null && $minMaster !== null) {
                    // 1) Difiere exactamente 12 h (±5 min)
                    $diff = abs($minEmision - $minMaster);
                    if ($diff > 720) {
                        $diff = 1440 - $diff;
                    }
                    if (abs($diff - 720) <= 5) {
                        $motivos[] = 'Diferencia de 12h (±5 min)';
                    }

                    // 2) Emisión < 06:00 mientras el programa declare hora >= 13:00
                    if ($minEmision < 360 && $minMaster >= 780) {
                        $motivos[] = 'Madrugada (<06:00) con programa de tarde (≥13:00)';
                    }
                }
            }

            $esSospechosa = ! empty($motivos);
            if ($esSospechosa) {
                $totalSospechosas++;
            }

            if ($showAll || $esSospechosa) {
                $rows[] = [
                    'Programa'      => $master->name,
                    'Día'           => $emision->dia_semana,
                    'Hora Emisión'  => substr($horaEmisionStr, 0, 5),
                    'Hora Programa' => substr($horaMasterStr, 0, 5),
                    'Motivo'        => $esSospechosa ? implode('; ', $motivos) : 'OK',
                ];
            }
        }

        if (empty($rows)) {
            $this->info('No se encontraron emisiones sospechosas (0 filas).');
            return Command::SUCCESS;
        }

        $this->table(
            ['Programa', 'Día', 'Hora Emisión', 'Hora Programa', 'Motivo'],
            $rows
        );

        if (! $showAll) {
            $this->warn(sprintf('Total de emisiones sospechosas: %d', $totalSospechosas));
            foreach ($rows as $r) {
                $this->line(sprintf('⚠ [%s] %s %s vs %s -> %s', $r['Programa'], $r['Día'], $r['Hora Emisión'], $r['Hora Programa'], $r['Motivo']));
            }
        }

        return Command::SUCCESS;
    }

    private function timeToMinutes(string $time): ?int
    {
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        $h = (int) $parts[0];
        $m = (int) $parts[1];

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return ($h * 60) + $m;
    }
}
