<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StorageInventory\StorageInventoryService;
use Illuminate\Console\Command;

class HostingerStorageInventoryCommand extends Command
{
    protected $signature = 'hostinger:inventory
                            {--json : Output inventory in JSON format}
                            {--path= : Base directory path to inspect (defaults to storage/app)}
                            {--deep-verify : Download R2 objects in streaming to verify SHA-256 checksum identity}
                            {--min-age-hours=24 : Minimum age in hours before a temporary file is considered idle}';

    protected $description = 'Read-only inventory of stored assets, podcasts, temp files and cloud backups';

    public function handle(StorageInventoryService $inventoryService): int
    {
        $basePath = (string) ($this->option('path') ?: storage_path('app'));
        $asJson = (bool) $this->option('json');
        $deepVerify = (bool) $this->option('deep-verify');
        $minAgeHours = max(1, (int) $this->option('min-age-hours'));

        if (! is_dir($basePath)) {
            $this->error("Target path does not exist: {$basePath}");

            return self::FAILURE;
        }

        $report = $inventoryService->run($basePath, $deepVerify, $minAgeHours);

        if ($asJson) {
            $this->output->writeln((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $meta = $report['meta'];
        $summary = $report['summary'];
        $items = $report['items'];

        $this->info('=== INVENTARIO DE ALMACENAMIENTO (MODO SOLO LECTURA ESTRICTO) ===');
        $this->line('Fecha generación : ' . $meta['generated_at']);
        $this->line('Servidor / Host  : ' . $meta['hostname'] . ' (' . $meta['app_env'] . ')');
        $this->line('Base inspeccionada: ' . $meta['base_path']);
        $this->line('R2 configurado   : ' . ($meta['r2_configured'] ? 'SÍ (bucket: ' . $meta['r2_bucket'] . ')' : 'NO'));
        $this->line('Verificación SHA256: ' . ($meta['deep_verify'] ? 'ACTIVA (--deep-verify)' : 'INACTIVA (solo HEAD/tamaño)'));
        $this->line('Antigüedad mín. tmp: ' . $meta['min_age_hours'] . ' horas');
        $this->line(sprintf('Total analizado  : %d archivos (%s MB)', $summary['total']['files'], $summary['total']['mb']));
        $this->line(sprintf('Recuperable verif.: %s MB (Categorías A + B)', $summary['recuperable_verificado']['mb']));

        if (! empty($meta['warnings'])) {
            $this->newLine();
            foreach ($meta['warnings'] as $warning) {
                $this->warn('AVISO: ' . $warning);
            }
        }

        if (! empty($meta['skipped_symlinks'])) {
            $this->newLine();
            $this->warn('Enlaces simbólicos omitidos (' . count($meta['skipped_symlinks']) . '): ' . implode(', ', $meta['skipped_symlinks']));
        }

        $this->newLine();

        $byCategory = [
            StorageInventoryService::CAT_A => [],
            StorageInventoryService::CAT_B => [],
            StorageInventoryService::CAT_C => [],
            StorageInventoryService::CAT_D => [],
        ];

        foreach ($items as $item) {
            $byCategory[$item['category']][] = $item;
        }

        $this->info(sprintf(
            'A. Duplicados verificados en R2 sin referencias locales (%d archivos, %s MB):',
            $summary[StorageInventoryService::CAT_A]['files'],
            $summary[StorageInventoryService::CAT_A]['mb']
        ));
        $this->renderTable($byCategory[StorageInventoryService::CAT_A]);

        $this->info(sprintf(
            'B. Temporales propios sin consumidores ni trabajos pendientes (%d archivos, %s MB):',
            $summary[StorageInventoryService::CAT_B]['files'],
            $summary[StorageInventoryService::CAT_B]['mb']
        ));
        $this->renderTable($byCategory[StorageInventoryService::CAT_B]);

        $this->info(sprintf(
            'C. Archivos necesarios que deben conservarse (%d archivos, %s MB):',
            $summary[StorageInventoryService::CAT_C]['files'],
            $summary[StorageInventoryService::CAT_C]['mb']
        ));
        $this->renderTable($byCategory[StorageInventoryService::CAT_C]);

        $this->info(sprintf(
            'D. Archivos inciertos o sin correspondencia comprobada (%d archivos, %s MB):',
            $summary[StorageInventoryService::CAT_D]['files'],
            $summary[StorageInventoryService::CAT_D]['mb']
        ));
        $this->renderTable($byCategory[StorageInventoryService::CAT_D]);

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function renderTable(array $items): void
    {
        if ($items === []) {
            $this->line('   (Ninguno)');
            $this->newLine();

            return;
        }

        $rows = array_map(fn (array $i): array => [
            $i['relative_path'],
            $i['role'],
            round(((int) $i['size_bytes']) / 1048576, 2) . ' MB',
            $i['associated_record'] ?? 'Ninguno',
            $i['r2_key'] ?? '-',
            $i['reason'],
        ], array_slice($items, 0, 25));

        $this->table(['Ruta Relativa', 'Rol', 'Tamaño', 'Registro Asociado', 'Clave R2', 'Motivo'], $rows);
        if (count($items) > 25) {
            $this->line(sprintf('   ... y %d archivos más.', count($items) - 25));
        }
        $this->newLine();
    }
}
