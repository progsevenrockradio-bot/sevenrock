<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TalentMedia;
use App\Models\TrackSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TalentAuditMediaStorageCommand extends Command
{
    protected $signature = 'talent:audit-media-storage {--format=table : Formato de salida (table|json)}';

    protected $description = 'Auditoría en MODO LECTURA del almacenamiento y URLs de medios de talentos (sin modificar ni eliminar archivos).';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  AUDITORÍA EN MODO LECTURA: ALMACENAMIENTO DE MEDIOS DE TALENTOS');
        $this->info('================================================================');
        $this->line('Esta herramienta examina los registros existentes para detectar');
        $this->line('archivos históricos que pudieran tener URLs públicas previas.');
        $this->line('NO se moverá, modificará ni eliminará ningún archivo físico.');
        $this->newLine();

        try {
            $allMedia = TalentMedia::query()->with('talent')->get();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar con la base de datos: ' . $e->getMessage());
            return self::FAILURE;
        }
        $total = $allMedia->count();

        $this->info("Total de registros en talent_media: {$total}");

        if ($total === 0) {
            $this->comment('No hay registros de medios de talentos en la base de datos.');
            return self::SUCCESS;
        }

        // Distribución por disco
        $byDisk = $allMedia->groupBy(fn ($m) => $m->disk ?: 'sin_disco_explicito');
        $this->line('Distribución por disco registrado:');
        foreach ($byDisk as $disk => $group) {
            $this->line("  - {$disk}: {$group->count()} archivos");
        }
        $this->newLine();

        // Distribución por estado
        $byStatus = $allMedia->groupBy('status');
        $this->line('Distribución por estado editorial:');
        foreach ($byStatus as $status => $group) {
            $this->line("  - {$status}: {$group->count()} archivos");
        }
        $this->newLine();

        // Análisis de claves compartidas (para evitar borrados accidentales de objetos compartidos)
        $keyCounts = TalentMedia::query()
            ->select('backblaze_key', DB::raw('count(*) as count'))
            ->whereNotNull('backblaze_key')
            ->where('backblaze_key', '!=', '')
            ->groupBy('backblaze_key')
            ->having('count', '>', 1)
            ->pluck('count', 'backblaze_key')
            ->toArray();

        $sharedKeysCount = count($keyCounts);
        if ($sharedKeysCount > 0) {
            $this->warn("¡ATENCIÓN! Se detectaron {$sharedKeysCount} claves de almacenamiento compartidas entre múltiples registros.");
        } else {
            $this->info('No se detectaron claves de almacenamiento compartidas entre múltiples registros.');
        }
        $this->newLine();

        // Detección de objetos que requieren privacidad pero están en disco público o tienen URL pública histórica
        $affected = [];

        foreach ($allMedia as $item) {
            $rawUrl = (string) $item->getRawOriginal('url');
            $isPublicDisk = $item->disk === 'r2' || empty($item->disk);
            $hasPublicDirectUrl = str_contains($rawUrl, 'r2.dev')
                || str_contains($rawUrl, 'backblazeb2.com')
                || str_contains($rawUrl, 'storage.')
                || str_contains($rawUrl, 'cdn.');

            $isRestricted = $item->is_exclusive
                || $item->type === 'document'
                || $item->status !== 'approved'
                || ($item->talent && ! $item->talent->isPubliclyVisible());

            if (($isPublicDisk || $hasPublicDirectUrl) && $isRestricted) {
                $reasons = [];
                if ($item->is_exclusive) {
                    $reasons[] = 'exclusivo';
                }
                if ($item->type === 'document') {
                    $reasons[] = 'documento_privado';
                }
                if ($item->status !== 'approved') {
                    $reasons[] = "estado_{$item->status}";
                }
                if ($item->talent && ! $item->talent->isPubliclyVisible()) {
                    $reasons[] = 'banda_oculta_o_inactiva';
                }

                $isShared = isset($keyCounts[$item->backblaze_key]);

                $affected[] = [
                    'id' => $item->id,
                    'talent_id' => $item->talent_id,
                    'band_name' => $item->talent?->band_name ?? 'N/D',
                    'type' => $item->type,
                    'status' => $item->status,
                    'disk' => $item->disk ?: 'NULL',
                    'key' => $item->backblaze_key,
                    'shared' => $isShared ? 'SÍ (' . ($keyCounts[$item->backblaze_key] ?? 2) . ' refs)' : 'No',
                    'reasons' => implode(', ', $reasons),
                    'raw_url' => $rawUrl,
                ];
            }
        }

        $this->info("Objetos que requieren regularización segura: " . count($affected));

        if (count($affected) > 0) {
            if ($this->option('format') === 'json') {
                $this->line(json_encode($affected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $tableData = array_map(fn ($row) => [
                    'ID' => $row['id'],
                    'Banda' => $row['band_name'],
                    'Tipo' => $row['type'],
                    'Estado' => $row['status'],
                    'Disco' => $row['disk'],
                    'Compartido' => $row['shared'],
                    'Motivo de restricción' => $row['reasons'],
                ], $affected);

                $this->table(['ID', 'Banda', 'Tipo', 'Estado', 'Disco', 'Compartido', 'Motivo de restricción'], $tableData);
            }

            $this->newLine();
            $this->comment('PASOS PARA LA REGULARIZACIÓN SEGURA (PLANIFICACIÓN):');
            $this->line('1. Copiar cada objeto listado desde r2 hacia r2_private preservando su clave.');
            $this->line('2. Verificar la integridad y tamaño del objeto en r2_private.');
            $this->line('3. Actualizar el registro en talent_media: disk = "r2_private" y url = route("talents.media.stream", ...).');
            $this->line('4. Eliminar el objeto antiguo en r2 únicamente si la columna Compartido es "No".');
            $this->line('5. Si la columna Compartido es "SÍ", conservar el objeto en r2 mientras existan otros registros que dependan de él.');
        } else {
            $this->info('Todos los archivos restringidos se encuentran debidamente alojados en r2_private.');
        }

        return self::SUCCESS;
    }
}

