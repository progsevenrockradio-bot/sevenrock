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
        $this->line('Esta herramienta examina la configuración y los registros existentes');
        $this->line('para detectar discrepancias de privacidad y archivos históricos.');
        $this->line('NO se moverá, modificará ni eliminará ningún archivo físico.');
        $this->newLine();

        // 1. Estado de Configuración y Caché del Entorno
        $isConfigCached = app()->configurationIsCached();
        $envPath = app()->environmentFilePath();
        $envExists = file_exists($envPath);

        $this->info('--- 1. CONFIGURACIÓN EFECTIVA Y CACHÉ DEL ENTORNO ---');
        $this->line('Configuración cacheada (bootstrap/cache/config.php): ' . ($isConfigCached ? 'SÍ (ACTIVA - variables .env ignoradas hasta config:clear)' : 'NO (Carga dinámica directa desde .env y config)'));
        $this->line('Archivo .env local/servidor: ' . ($envExists ? 'PRESENTE' : 'NO ENCONTRADO'));
        $this->newLine();

        // 2. Auditoría de Buckets R2 y Aislamiento Privado
        $r2Bucket = (string) config('filesystems.disks.r2.bucket');
        $r2Url = (string) config('filesystems.disks.r2.url');
        $r2PrivateBucket = (string) config('filesystems.disks.r2_private.bucket');
        $r2PrivateUrl = (string) config('filesystems.disks.r2_private.url');
        $r2PrivateVisibility = (string) config('filesystems.disks.r2_private.visibility');

        $fileUploadService = app(\App\Services\FileUploadService::class);
        $isR2Configured = $fileUploadService->isR2Configured();
        $isR2PrivateConfigured = $fileUploadService->isR2PrivateConfigured();

        $this->info('--- 2. AISLAMIENTO Y PRIVACIDAD DE BUCKETS R2 ---');
        $this->line("Disco R2 Público:  Bucket = [{$r2Bucket}], URL pública = [{$r2Url}], Configurado = " . ($isR2Configured ? 'SÍ' : 'NO'));
        $this->line("Disco R2 Privado:  Bucket = [{$r2PrivateBucket}], URL pública = " . ($r2PrivateUrl !== '' ? "[¡ALERTA! {$r2PrivateUrl}]" : '[NINGUNA - Seguro]') . ", Visibilidad = [{$r2PrivateVisibility}], Configurado = " . ($isR2PrivateConfigured ? 'SÍ' : 'NO'));

        if ($r2Bucket !== '' && $r2PrivateBucket !== '') {
            if ($r2Bucket === $r2PrivateBucket) {
                $this->error('¡CRÍTICO! El bucket privado R2_PRIVATE_BUCKET es IDÉNTICO al bucket público CLOUDFLARE_R2_BUCKET. No existe aislamiento físico.');
            } else {
                $this->info("Aislamiento físico verificado: [{$r2PrivateBucket}] es estrictamente distinto de [{$r2Bucket}].");
            }
        } elseif ($r2PrivateBucket === '') {
            $this->warn('R2_PRIVATE_BUCKET no está configurado explícitamente en el entorno.');
        }

        if ($r2PrivateUrl === '') {
            $this->info('Privacidad confirmada: r2_private no tiene base URL pública ni dominio r2.dev configurado.');
        } else {
            $this->warn("Atención: r2_private expone base URL [{$r2PrivateUrl}]. Debe ser nula para evitar acceso público directo.");
        }
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

