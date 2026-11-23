<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DesignProtectionService;
use Illuminate\Console\Command;

class DesignProtectCommand extends Command
{
    protected $signature = 'design:protect
                            {--check : Solo comprueba la integridad sin realizar cambios ni restauraciones}
                            {--monitor : Modo de vigilancia programada para el Scheduler}
                            {--backup : Crea un respaldo completo del build verificado actual}
                            {--validate-deploy : Valida el build e impide despliegues corruptos o desincronizados}
                            {--lock : Adquiere el bloqueo de despliegue para pausar la vigilancia}
                            {--unlock : Libera el bloqueo de despliegue}
                            {--public-path= : Ruta personalizada al directorio público del build}';

    protected $description = 'Protección, vigilancia y recuperación automática de archivos de diseño y manifiesto Vite';

    public function handle(DesignProtectionService $service): int
    {
        $customPublic = $this->option('public-path');
        if ($customPublic) {
            $service->setPublicBuildPath($customPublic);
        }

        // 1. Manejo de bloqueo / desbloqueo de despliegue
        if ($this->option('lock')) {
            if ($service->acquireLock(180, 'deployment')) {
                $this->info('✓ Bloqueo de protección de diseño adquirido (vigilancia en pausa durante 180s).');
                return self::SUCCESS;
            }
            $this->warn('⚠ Ya existe un bloqueo activo: ' . json_encode($service->getLockInfo()));
            return self::FAILURE;
        }

        if ($this->option('unlock')) {
            $service->releaseLock();
            $this->info('✓ Bloqueo de protección de diseño liberado.');
            return self::SUCCESS;
        }

        // 2. Creación de respaldo manual o post-despliegue
        if ($this->option('backup')) {
            $this->info('Creando respaldo verificado del build activo...');
            try {
                $result = $service->createBackup();
                $this->info("✓ Respaldo creado exitosamente en {$result['backup_dir']}");
                $this->line("  Hash de manifiesto: {$result['manifest_hash']}");
                $this->line("  Archivos respaldados: {$result['total_files']}");
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('✗ Error al crear el respaldo: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        // 3. Validación pre-despliegue
        if ($this->option('validate-deploy')) {
            $this->info('Validando integridad del build antes de activar despliegue...');
            $validation = $service->validateBuild($service->getInternalBuildPath());

            if (!$validation['valid']) {
                $this->error('✗ Fallo en validación de build interno:');
                foreach ($validation['errors'] as $err) {
                    $this->error("  - {$err}");
                }
                return self::FAILURE;
            }

            $this->info("✓ Build interno íntegro (Hash: {$validation['manifest_hash']})");

            $publicPath = $service->getPublicBuildPath();
            if ($publicPath && is_dir($publicPath)) {
                $sync = $service->verifySynchronized();
                if (!$sync['synchronized']) {
                    $this->error('✗ Desincronización entre build interno y público:');
                    foreach ($sync['errors'] as $err) {
                        $this->error("  - {$err}");
                    }
                    return self::FAILURE;
                }
                $this->info('✓ Builds interno y público perfectamente sincronizados.');
            }

            return self::SUCCESS;
        }

        // 4. Modo vigilancia para Scheduler
        if ($this->option('monitor')) {
            $report = $service->auditAndRecover(
                autoRecover: true,
                checkHttp: true,
                publicBuildPath: $customPublic
            );

            if ($report['status'] === 'locked') {
                $this->line("ℹ {$report['message']}");
                return self::SUCCESS;
            }

            if ($report['status'] === 'recovered') {
                $this->warn("⚠ {$report['message']}");
                foreach ($report['recovered_files'] as $rf) {
                    $this->line("  + Recuperado: {$rf}");
                }
                return self::SUCCESS;
            }

            if ($report['status'] === 'healthy') {
                $this->info("✓ Integridad de diseño verificada (Hash: {$report['manifest_hash']})");
                return self::SUCCESS;
            }

            $this->error("✗ Estado crítico de diseño: {$report['status']}");
            if (isset($report['errors'])) {
                foreach ($report['errors'] as $err) {
                    $this->error("  - {$err}");
                }
            }

            return self::FAILURE;
        }

        // 5. Modo comprobación/chequeo (--check o sin argumentos)
        $this->info('Auditoría de integridad de diseño y assets:');
        $report = $service->auditAndRecover(
            autoRecover: false,
            checkHttp: false,
            publicBuildPath: $customPublic
        );

        $summary = $service->getStatusSummary();
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Estado', $summary['label']],
                ['Versión Activa', $summary['manifest_hash']],
                ['Respaldo Disponible', $summary['backup_available'] ? 'Sí (' . $summary['backup_hash'] . ')' : 'No'],
                ['Respaldo Compatible', $summary['is_compatible'] ? 'Sí (Coincide)' : 'No'],
                ['Última Verificación', $summary['last_check'] ?? 'Nunca'],
                ['Mensaje', $summary['message']],
            ]
        );

        return $report['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }
}
