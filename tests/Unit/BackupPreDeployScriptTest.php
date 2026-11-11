<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class BackupPreDeployScriptTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/backup_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_script_aborts_if_public_html_build_directory_missing(): void
    {
        $phpBinary = 'C:\\DEV\\Laragon\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scripts/backup_pre_deploy.php');
        $nonExistentPublicHtml = $this->tempDir . '/non_existent_public';
        $backupDir = $this->tempDir . '/backups';

        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' --app-path=' . escapeshellarg(base_path()) . ' --public-html-path=' . escapeshellarg($nonExistentPublicHtml) . ' --backup-dir=' . escapeshellarg($backupDir) . ' --skip-db 2>&1',
            $output,
            $exitCode
        );

        $this->assertNotSame(0, $exitCode);
        $fullOutput = implode("\n", $output);
        $this->assertStringContainsString('Directorio de assets servidos no existe', $fullOutput);
    }

    public function test_script_aborts_if_manifest_json_is_missing(): void
    {
        $phpBinary = 'C:\\DEV\\Laragon\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scripts/backup_pre_deploy.php');
        $publicHtml = $this->tempDir . '/public_html';
        $backupDir = $this->tempDir . '/backups';
        mkdir($publicHtml . '/build', 0777, true);

        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' --app-path=' . escapeshellarg(base_path()) . ' --public-html-path=' . escapeshellarg($publicHtml) . ' --backup-dir=' . escapeshellarg($backupDir) . ' --skip-db 2>&1',
            $output,
            $exitCode
        );

        $this->assertNotSame(0, $exitCode);
        $fullOutput = implode("\n", $output);
        $this->assertStringContainsString('manifest.json ausente o vacío', $fullOutput);
    }

    public function test_script_aborts_if_manifest_references_missing_entrypoint_component(): void
    {
        $phpBinary = 'C:\\DEV\\Laragon\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scripts/backup_pre_deploy.php');
        $publicHtml = $this->tempDir . '/public_html';
        $backupDir = $this->tempDir . '/backups';
        $buildDir = $publicHtml . '/build';
        mkdir($buildDir, 0777, true);

        // manifest referenciando un app.css inexistente
        $manifest = [
            'resources/css/app.css' => [
                'file' => 'assets/app-missing.css',
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app-missing.js',
            ],
        ];
        file_put_contents($buildDir . '/manifest.json', (string) json_encode($manifest));

        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' --app-path=' . escapeshellarg(base_path()) . ' --public-html-path=' . escapeshellarg($publicHtml) . ' --backup-dir=' . escapeshellarg($backupDir) . ' --skip-db 2>&1',
            $output,
            $exitCode
        );

        $this->assertNotSame(0, $exitCode);
        $fullOutput = implode("\n", $output);
        $this->assertStringContainsString('Componente compilado', $fullOutput);
        $this->assertStringContainsString('no existe o está vacío', $fullOutput);
    }

    public function test_script_succeeds_when_all_assets_and_manifest_are_present(): void
    {
        $phpBinary = 'C:\\DEV\\Laragon\\laragon\\bin\\php\\php-8.3.36-Win32-vs16-x64\\php.exe';
        $scriptPath = base_path('scripts/backup_pre_deploy.php');
        $publicHtml = $this->tempDir . '/public_html';
        $backupDir = $this->tempDir . '/backups';
        $buildDir = $publicHtml . '/build';
        $assetsDir = $buildDir . '/assets';
        mkdir($assetsDir, 0777, true);

        // Crear archivos válidos
        file_put_contents($assetsDir . '/app-test.css', 'body { color: red; }');
        file_put_contents($assetsDir . '/app-test.js', 'console.log("ok");');

        $manifest = [
            'resources/css/app.css' => [
                'file' => 'assets/app-test.css',
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app-test.js',
            ],
        ];
        file_put_contents($buildDir . '/manifest.json', (string) json_encode($manifest));

        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath) . ' --app-path=' . escapeshellarg(base_path()) . ' --public-html-path=' . escapeshellarg($publicHtml) . ' --backup-dir=' . escapeshellarg($backupDir) . ' --skip-db 2>&1',
            $output,
            $exitCode
        );

        $fullOutput = implode("\n", $output);
        $this->assertSame(0, $exitCode, "Output was: " . $fullOutput);
        $this->assertStringContainsString('RESPALDO_VERIFICADO_OK', $fullOutput);
        $this->assertStringContainsString('Assets servidos (public_html/build) respaldados', $fullOutput);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
