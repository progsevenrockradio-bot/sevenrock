<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use App\Services\DesignProtectionService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DesignProtectionSafetyTest extends TestCase
{
    protected string $tempDir;
    protected string $internalBuildDir;
    protected string $publicBuildDir;
    protected string $backupDir;
    protected string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/design_protection_' . uniqid());
        $this->internalBuildDir = $this->tempDir . '/internal/build';
        $this->publicBuildDir = $this->tempDir . '/public/build';
        $this->backupDir = $this->tempDir . '/backup';
        $this->lockFile = $this->tempDir . '/design-protection.lock';

        @mkdir($this->internalBuildDir . '/assets', 0755, true);
        @mkdir($this->publicBuildDir . '/assets', 0755, true);
        @mkdir($this->backupDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    protected function createService(): DesignProtectionService
    {
        $service = new DesignProtectionService(
            $this->internalBuildDir,
            $this->publicBuildDir,
            $this->backupDir
        );
        $service->setLockFile($this->lockFile);

        return $service;
    }

    protected function seedHealthyBuild(string $dir, string $cssHash = 'css_v1_content', string $jsHash = 'js_v1_content'): array
    {
        $cssFile = "assets/app-{$cssHash}.css";
        $jsFile = "assets/app-{$jsHash}.js";

        $cssContent = "/* css content {$cssHash} */ body { background: #151515; }";
        $jsContent = "/* js content {$jsHash} */ console.log('sevenrock');";

        file_put_contents($dir . '/' . $cssFile, $cssContent);
        file_put_contents($dir . '/' . $cssFile . '.br', $cssContent . '.br');
        file_put_contents($dir . '/' . $cssFile . '.gz', $cssContent . '.gz');

        file_put_contents($dir . '/' . $jsFile, $jsContent);

        $manifest = [
            'resources/css/app.css' => [
                'file' => $cssFile,
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => $jsFile,
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ];

        $manifestContent = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($dir . '/manifest.json', $manifestContent);
        file_put_contents($dir . '/manifest.json.br', $manifestContent . '.br');
        file_put_contents($dir . '/manifest.json.gz', $manifestContent . '.gz');

        return [
            'manifest' => $manifest,
            'manifest_hash' => hash('sha256', $manifestContent),
            'css_file' => $cssFile,
            'css_hash' => hash_file('sha256', $dir . '/' . $cssFile),
            'js_file' => $jsFile,
            'js_hash' => hash_file('sha256', $dir . '/' . $jsFile),
        ];
    }

    public function test_can_validate_and_backup_a_healthy_build(): void
    {
        $seed = $this->seedHealthyBuild($this->internalBuildDir);
        $this->seedHealthyBuild($this->publicBuildDir);

        $service = $this->createService();
        $validation = $service->validateBuild($this->internalBuildDir);

        $this->assertTrue($validation['valid']);
        $this->assertSame($seed['manifest_hash'], $validation['manifest_hash']);
        $this->assertArrayHasKey($seed['css_file'], $validation['files']);
        $this->assertArrayHasKey($seed['js_file'], $validation['files']);

        $backupResult = $service->createBackup();
        $this->assertTrue($backupResult['success']);
        $this->assertSame($seed['manifest_hash'], $backupResult['manifest_hash']);
        $this->assertFileExists($this->backupDir . '/manifest.json');
        $this->assertFileExists($this->backupDir . '/' . $seed['css_file']);
        $this->assertFileExists($this->backupDir . '/' . $seed['css_file'] . '.br');

        $meta = $service->getBackupMetadata();
        $this->assertNotNull($meta);
        $this->assertSame($seed['manifest_hash'], $meta['manifest_hash']);
        $this->assertTrue($service->isBackupCompatible($seed['manifest_hash']));
    }

    public function test_recovers_missing_resource_with_exact_hash_from_compatible_backup(): void
    {
        $seed = $this->seedHealthyBuild($this->internalBuildDir);
        $this->seedHealthyBuild($this->publicBuildDir);

        $service = $this->createService();
        $service->createBackup();

        // Simular que el archivo CSS desapareció del build interno
        $targetCss = $this->internalBuildDir . '/' . $seed['css_file'];
        $this->assertFileExists($targetCss);
        unlink($targetCss);
        $this->assertFileDoesNotExist($targetCss);

        // Ejecutar auditoría y recuperación segura
        $report = $service->auditAndRecover(autoRecover: true, checkHttp: false);

        $this->assertSame('recovered', $report['status']);
        $this->assertSame('restored_from_backup', $report['action_taken']);
        $this->assertFileExists($targetCss);

        // Verificar que el archivo restaurado tiene exactamente su hash original
        $restoredHash = hash_file('sha256', $targetCss);
        $this->assertSame($seed['css_hash'], $restoredHash);
    }

    public function test_incompatible_backup_is_strictly_rejected_without_blind_repairs(): void
    {
        // 1. Crear respaldo para versión V1
        $seedV1 = $this->seedHealthyBuild($this->internalBuildDir, 'v1_hash', 'v1_js');
        $service = $this->createService();
        $service->createBackup();

        // 2. Cambiar build a versión V2 (nuevo hash de CSS y nuevo manifiesto)
        $seedV2 = $this->seedHealthyBuild($this->internalBuildDir, 'v2_new_hash', 'v2_new_js');
        $this->seedHealthyBuild($this->publicBuildDir, 'v2_new_hash', 'v2_new_js');

        $this->assertNotSame($seedV1['manifest_hash'], $seedV2['manifest_hash']);

        // 3. Simular que el CSS de V2 falta en el build
        $targetCssV2 = $this->internalBuildDir . '/' . $seedV2['css_file'];
        unlink($targetCssV2);
        $this->assertFileDoesNotExist($targetCssV2);

        // 4. Ejecutar auditoría y recuperación
        $report = $service->auditAndRecover(autoRecover: true, checkHttp: false);

        // Debe rechazar la recuperación porque el respaldo es incompatible (pertenece a V1)
        $this->assertSame('corrupt_unrecoverable', $report['status']);
        $this->assertSame('rejected_incompatible_backup', $report['action_taken']);
        $this->assertFalse($service->isBackupCompatible($seedV2['manifest_hash']));

        // Comprobar que no se restauró nada a ciegas ni se mezclaron archivos
        $this->assertFileDoesNotExist($targetCssV2);
    }

    public function test_coordination_lock_pauses_monitoring_during_deployment(): void
    {
        $this->seedHealthyBuild($this->internalBuildDir);
        $this->seedHealthyBuild($this->publicBuildDir);

        $service = $this->createService();
        $this->assertTrue($service->acquireLock(60, 'test_deployment'));
        $this->assertTrue($service->isLocked());

        // Al ejecutar monitor con bloqueo activo, debe omitir cualquier acción
        $report = $service->auditAndRecover(autoRecover: true, checkHttp: false);

        $this->assertSame('locked', $report['status']);
        $this->assertSame('skipped', $report['action_taken']);

        // Liberar bloqueo
        $service->releaseLock();
        $this->assertFalse($service->isLocked());
    }

    public function test_validation_fails_if_manifest_references_missing_file(): void
    {
        $seed = $this->seedHealthyBuild($this->internalBuildDir);
        $missingFile = $this->internalBuildDir . '/' . $seed['css_file'];
        unlink($missingFile);

        $service = $this->createService();
        $validation = $service->validateBuild($this->internalBuildDir);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $this->assertStringContainsString($seed['css_file'], $validation['errors'][0]);
    }
}
