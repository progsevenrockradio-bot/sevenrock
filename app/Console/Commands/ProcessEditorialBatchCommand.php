<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EditorialBatchProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProcessEditorialBatchCommand extends Command
{
    protected $signature = 'editorial:process-batch 
                            {file : Ruta absoluta o relativa al fichero del lote DV-1}
                            {--manifest= : Ruta al manifiesto de imagenes instaladas (opcional)}
                            {--dry-run : Ejecuta sin persistir cambios en la base de datos}
                            {--approve-updates : Permite actualizar borradores existentes ante contenido modificado con aprobacion explicita}';

    protected $description = 'Procesa un lote estructurado DV-1 local en modo borrador, con reimportación segura, validación atómica y protección contra duplicados.';

    public function handle(EditorialBatchProcessor $batchProcessor): int
    {
        $filePath = (string) $this->argument('file');
        if (! File::exists($filePath)) {
            $this->error("El fichero del lote no existe: {$filePath}");
            return self::FAILURE;
        }

        $rawContent = File::get($filePath);
        $isDryRun = (bool) $this->option('dry-run');
        $approveUpdates = (bool) $this->option('approve-updates');

        $this->info("Analizando lote DV-1 desde: {$filePath}");

        // Cargar manifiesto de imágenes si existe
        $manifestPath = (string) $this->option('manifest') ?: dirname($filePath) . '/images_installed.json';
        $imagesManifest = [];
        if (File::exists($manifestPath)) {
            $imagesManifest = json_decode(File::get($manifestPath), true) ?: [];
            $this->info("Manifiesto de imágenes cargado desde: {$manifestPath}");
        }

        $result = $batchProcessor->process(
            $rawContent,
            $imagesManifest,
            sourceMessageId: null,
            senderEmail: 'dark.vader.agent@gmail.com',
            isDryRun: $isDryRun,
            allowUpdateApproval: $approveUpdates
        );

        if (! $result['success']) {
            $this->error("\n[ERROR EN LOTE {$result['batch_id']}] {$result['error']}");

            if (! empty($result['failed_elements'])) {
                $this->warn("\nElementos fallidos:");
                foreach ($result['failed_elements'] as $elemId => $err) {
                    $this->line("  - {$elemId}: {$err}");
                }
            }

            if (! empty($result['processed_elements'])) {
                $this->info("\nElementos procesados previamente en el intento: " . implode(', ', $result['processed_elements']));
            }

            return self::FAILURE;
        }

        $this->info("\n=== RESUMEN DE PROCESAMIENTO — LOTE {$result['batch_id']} ===");
        $this->info("Declarados: {$result['total_declared']} | Extraídos: {$result['total_extracted']}");
        $this->info("Borradores creados: {$result['created']}");
        $this->info("Borradores actualizados: {$result['updated']}");
        $this->info("Omitidos (idénticos sin cambios): {$result['skipped']}");
        $this->info("Conflictos / Artículos protegidos: {$result['conflicts']}");

        $this->line("\nDetalle por elemento:");
        foreach ($result['elements_detail'] as $elemId => $det) {
            $action = $det['action'];
            $postId = $det['post_id'] ? "Post ID: {$det['post_id']}" : 'N/A';
            $rev = $det['revision'] ? 'REVISIÓN REQUERIDA' : 'APROBADO';
            $this->line("  [{$action}] {$elemId} -> {$postId} | {$rev} | {$det['reason']}");
        }

        return self::SUCCESS;
    }
}
