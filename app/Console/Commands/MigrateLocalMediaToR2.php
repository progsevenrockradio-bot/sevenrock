<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackblazeService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MigrateLocalMediaToR2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-to-r2
                            {--dry-run : Muestra el inventario pero no realiza la migracion}
                            {--delete-local : Elimina el fichero local tras confirmar la subida (HTTP 200)}
                            {--table=talent_media : Tabla objetivo de la migracion (por defecto talent_media)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra ficheros locales de public/storage a Cloudflare R2 usando BackblazeService.';

    /**
     * Execute the console command.
     */
    public function handle(BackblazeService $backblazeService)
    {
        $targetPrefix = 'https://sevenrockradio.com/storage/';
        $targetTable = $this->option('table');
        $isDryRun = $this->option('dry-run');
        $deleteLocal = $this->option('delete-local');

        $this->info("=== PASO 1: Inventario ===");

        // Buscar tablas que tengan columna `url`
        $tablesWithUrl = [];
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::connection()->getDatabaseName();
        $tableKey = 'Tables_in_' . $dbName;
        
        foreach ($tables as $tableRow) {
            $tableName = (array) $tableRow;
            $tableName = array_values($tableName)[0];
            
            if (Schema::hasColumn($tableName, 'url')) {
                $count = DB::table($tableName)->where('url', 'like', $targetPrefix . '%')->count();
                if ($count > 0) {
                    $tablesWithUrl[$tableName] = $count;
                }
            }
        }

        $this->table(['Tabla', 'Registros con URL local'], collect($tablesWithUrl)->map(function ($count, $table) {
            return [$table, $count];
        })->toArray());

        if (!isset($tablesWithUrl[$targetTable])) {
            $this->info("Resumen: 0 ficheros por migrar en la tabla '{$targetTable}'. " . count($tablesWithUrl) . " tablas afectadas en total.");
            return 0;
        }

        $filesToMigrate = $tablesWithUrl[$targetTable];
        $this->info("Resumen: {$filesToMigrate} ficheros por migrar en la tabla '{$targetTable}', " . count($tablesWithUrl) . " tablas afectadas en total.");

        if ($isDryRun) {
            $this->warn("Ejecucion en modo --dry-run. No se ha modificado nada.");
            return 0;
        }

        $this->info("\n=== PASO 2: Migracion ({$targetTable}) ===");
        
        $rows = DB::table($targetTable)->where('url', 'like', $targetPrefix . '%')->get();
        $migratedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $this->line("Procesando ID {$row->id}...");
            $oldUrl = $row->url;

            // Extraer ruta relativa de /storage/
            $relativePath = str_replace($targetPrefix, '', $oldUrl);
            $localPath = storage_path('app/public/' . $relativePath);

            if (!file_exists($localPath)) {
                $this->warn("Omitido ID {$row->id}: Fichero local no encontrado en {$localPath}");
                $skippedCount++;
                continue;
            }

            try {
                // Preparar UploadedFile (fake)
                $mime = mime_content_type($localPath) ?: 'application/octet-stream';
                $uploadedFile = new UploadedFile($localPath, basename($localPath), $mime, null, true);

                // Determinar el directorio de destino
                // Si la ruta es talents/12/media/mp3/file.mp3, usamos el dirname.
                $uploadDir = dirname($relativePath);
                if ($uploadDir === '.') {
                    $uploadDir = '';
                }

                $result = $backblazeService->upload($uploadedFile, $uploadDir);

                if (!isset($result['url']) || empty($result['url'])) {
                    throw new \Exception("El servicio de subida no devolvio una URL.");
                }

                $newUrl = $result['url'];

                // Actualizar BBDD
                DB::table($targetTable)->where('id', $row->id)->update([
                    'url' => $newUrl,
                    'backblaze_key' => $result['key'] ?? null,
                ]);

                Log::info("MigrateLocalMediaToR2: Migrado ID {$row->id}", [
                    'old_url' => $oldUrl,
                    'new_url' => $newUrl,
                ]);

                // Eliminar local si procede
                if ($deleteLocal) {
                    $response = Http::head($newUrl);
                    if ($response->successful()) {
                        @unlink($localPath);
                        $this->line("  -> Subido a R2 y fichero local eliminado.");
                    } else {
                        $this->warn("  -> Subido a R2, pero la nueva URL ({$newUrl}) no devuelve 200. Fichero local conservado.");
                    }
                } else {
                    $this->line("  -> Subido a R2. Fichero local conservado.");
                }

                $migratedCount++;

            } catch (\Throwable $e) {
                $this->error("Fallo al migrar ID {$row->id}: " . $e->getMessage());
                Log::error("MigrateLocalMediaToR2: Fallo al migrar ID {$row->id}", ['error' => $e->getMessage()]);
                $failedCount++;
            }
        }

        $this->info("\n=== Resumen de Migracion ===");
        $this->info("Migrados: {$migratedCount}");
        $this->info("Omitidos (no existe local): {$skippedCount}");
        $this->info("Fallos: {$failedCount}");

        return $failedCount > 0 ? 1 : 0;
    }
}
