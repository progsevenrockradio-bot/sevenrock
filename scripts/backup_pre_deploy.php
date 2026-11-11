<?php

declare(strict_types=1);

/**
 * Script de respaldo seguro y consistente previo a despliegue.
 * Respalda Base de Datos (vía conexión nativa de Laravel), Configuración (.env) y Assets (public/build).
 * Puede ejecutarse desde el repositorio o copiarse fuera de él apuntando a --app-path.
 */

// Parsear argumentos opcionales: --app-path, --public-html-path, --backup-dir y --skip-db
$appPath = dirname(__DIR__);
$publicHtmlPath = null;
$backupDir = null;
$skipDb = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--app-path=')) {
        $appPath = substr($arg, 11);
    } elseif (str_starts_with($arg, '--public-html-path=')) {
        $publicHtmlPath = substr($arg, 19);
    } elseif (str_starts_with($arg, '--backup-dir=')) {
        $backupDir = substr($arg, 13);
    } elseif ($arg === '--skip-db') {
        $skipDb = true;
    }
}

$appPath = rtrim($appPath, '/\\');
if (! file_exists($appPath . '/bootstrap/app.php')) {
    fwrite(STDERR, "[ERROR CRÍTICO] No se encontró una aplicación válida de Laravel en: {$appPath}\n");
    exit(1);
}

// Cargar Laravel para obtener configuración fidedigna sin recortes frágiles de texto
require $appPath . '/vendor/autoload.php';
$app = require $appPath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$timestamp = date('Ymd_His');
$baseDir = dirname($appPath);

if ($backupDir === null || trim($backupDir) === '') {
    $backupDir = $baseDir . '/backups_pre_autopublish';
}
$backupDir = rtrim($backupDir, '/\\');

if (! is_dir($backupDir)) {
    if (! @mkdir($backupDir, 0750, true) && ! is_dir($backupDir)) {
        $fallbackDir = $appPath . '/storage/backups_pre_autopublish';
        if (! is_dir($fallbackDir) && ! @mkdir($fallbackDir, 0750, true)) {
            fwrite(STDERR, "[ERROR CRÍTICO] No se pudo crear el directorio de respaldos: {$backupDir}\n");
            exit(1);
        }
        $backupDir = $fallbackDir;
    }
}

// Determinar ruta efectiva de assets públicos servidos
if ($publicHtmlPath === null || trim($publicHtmlPath) === '') {
    if (is_dir($baseDir . '/public_html')) {
        $publicHtmlPath = $baseDir . '/public_html';
    } else {
        $publicHtmlPath = $appPath . '/public';
    }
}
$publicHtmlPath = rtrim($publicHtmlPath, '/\\');

echo "=== INICIANDO RESPALDO PRE-DESPLIEGUE VERIFICADO ({$timestamp}) ===\n";
echo "Ruta de la aplicación: {$appPath}\n";
echo "Ruta efectiva servida (public_html): {$publicHtmlPath}\n";
echo "Directorio de respaldos: {$backupDir}\n\n";

// --- VALIDACIONES PREVIAS (FAIL-FAST) ---

// V1. Validación previa de configuración de BD
$dbConfig = config('database.connections.mysql', []);
$host = (string) ($dbConfig['host'] ?? '127.0.0.1');
$port = (string) ($dbConfig['port'] ?? '3306');
$user = (string) ($dbConfig['username'] ?? '');
$pass = (string) ($dbConfig['password'] ?? '');
$dbName = (string) ($dbConfig['database'] ?? '');

if (! $skipDb && ($user === '' || $dbName === '')) {
    fwrite(STDERR, "[ERROR CRÍTICO] Configuración de base de datos vacía o ilegible en Laravel.\n");
    exit(1);
}

// V2. Validación previa de archivo .env
$envSource = $appPath . '/.env';
if (! file_exists($envSource)) {
    fwrite(STDERR, "[ERROR CRÍTICO] Archivo .env no existe en {$appPath}. Abortando respaldo.\n");
    exit(1);
}

// V3. Validación estricta de Assets Efectivos Servidos (public_html/build)
$effectiveBuildDir = $publicHtmlPath . '/build';
if (! is_dir($effectiveBuildDir)) {
    fwrite(STDERR, "[ERROR CRÍTICO] Directorio de assets servidos no existe: {$effectiveBuildDir}. Abortando respaldo.\n");
    exit(1);
}

// Localizar manifest.json (raíz de build o subdirectorio .vite)
$manifestPath = $effectiveBuildDir . '/manifest.json';
if (! file_exists($manifestPath)) {
    $manifestPath = $effectiveBuildDir . '/.vite/manifest.json';
}

if (! file_exists($manifestPath) || filesize($manifestPath) === 0) {
    fwrite(STDERR, "[ERROR CRÍTICO] manifest.json ausente o vacío en: {$effectiveBuildDir}. Abortando respaldo.\n");
    exit(1);
}

$manifestContent = file_get_contents($manifestPath);
$manifestData = json_decode($manifestContent ?: '', true);
if (! is_array($manifestData) || empty($manifestData)) {
    fwrite(STDERR, "[ERROR CRÍTICO] manifest.json en {$effectiveBuildDir} contiene JSON inválido o está vacío. Abortando respaldo.\n");
    exit(1);
}

// Verificar componentes esperados requeridos por el frontend
$requiredEntries = ['resources/css/app.css', 'resources/js/app.js'];
foreach ($requiredEntries as $entryKey) {
    if (! isset($manifestData[$entryKey]['file'])) {
        fwrite(STDERR, "[ERROR CRÍTICO] Entrada requerida '{$entryKey}' ausente en manifest.json de {$effectiveBuildDir}. Abortando respaldo.\n");
        exit(1);
    }
    $targetFile = $effectiveBuildDir . '/' . $manifestData[$entryKey]['file'];
    if (! file_exists($targetFile) || filesize($targetFile) === 0) {
        fwrite(STDERR, "[ERROR CRÍTICO] Componente compilado '{$targetFile}' referenciado por '{$entryKey}' no existe o está vacío. Abortando respaldo.\n");
        exit(1);
    }
}

// --- EJECUCIÓN DEL RESPALDO TRAS VALIDACIÓN EXITOSA ---

// 1. RESPALDO DE BASE DE DATOS
if (! $skipDb) {
    $dbBackupFile = "{$backupDir}/db_backup_{$timestamp}.sql";
    putenv("MYSQL_PWD={$pass}");
    $dumpCmd = sprintf(
        'mysqldump --no-tablespaces -h %s -P %s -u %s %s > %s',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($dbName),
        escapeshellarg($dbBackupFile)
    );

    exec($dumpCmd, $dumpOutput, $dumpCode);
    if ($dumpCode !== 0 || ! file_exists($dbBackupFile) || filesize($dbBackupFile) === 0) {
        fwrite(STDERR, "[ERROR CRÍTICO] Falló el respaldo de la base de datos (código {$dumpCode}). Abortando sin continuar.\n");
        exit(1);
    }
    $dbSizeKb = round(filesize($dbBackupFile) / 1024, 2);
    echo "[OK] Base de Datos respaldada: {$dbBackupFile} ({$dbSizeKb} KB)\n";
} else {
    echo "[INFO] Respaldo de BD omitido mediante --skip-db.\n";
}

// 2. RESPALDO DE CONFIGURACIÓN (.env)
$envBackupFile = "{$backupDir}/env_backup_{$timestamp}.backup";
if (! copy($envSource, $envBackupFile) || filesize($envBackupFile) === 0) {
    fwrite(STDERR, "[ERROR CRÍTICO] Falló el respaldo de configuración (.env). Abortando sin continuar.\n");
    exit(1);
}
echo "[OK] Configuración (.env) respaldada: {$envBackupFile} (" . filesize($envBackupFile) . " bytes)\n";

// 3. EMPAQUETADO DE ASSETS EFECTIVOS SERVIDOS (public_html/build)
$publicBuildBackupFile = "{$backupDir}/public_html_build_assets_{$timestamp}.tar.gz";
$tarCmdPublic = sprintf('tar -czf %s -C %s build', escapeshellarg($publicBuildBackupFile), escapeshellarg($publicHtmlPath));
exec($tarCmdPublic, $tarOutputPublic, $tarCodePublic);
if ($tarCodePublic !== 0 || ! file_exists($publicBuildBackupFile) || filesize($publicBuildBackupFile) === 0) {
    fwrite(STDERR, "[ERROR CRÍTICO] Falló el empaquetado de assets de public_html (código {$tarCodePublic}). Abortando.\n");
    exit(1);
}
$publicBuildSizeKb = round(filesize($publicBuildBackupFile) / 1024, 2);
echo "[OK] Assets servidos (public_html/build) respaldados: {$publicBuildBackupFile} ({$publicBuildSizeKb} KB)\n";

// Si la aplicación tiene su propio directorio app/public/build diferenciado, respaldarlo también
$appBuildDir = $appPath . '/public/build';
if (realpath($appBuildDir) !== realpath($effectiveBuildDir) && is_dir($appBuildDir)) {
    $appBuildBackupFile = "{$backupDir}/build_assets_{$timestamp}.tar.gz";
    $tarCmdApp = sprintf('tar -czf %s -C %s build', escapeshellarg($appBuildBackupFile), escapeshellarg($appPath . '/public'));
    exec($tarCmdApp, $tarOutputApp, $tarCodeApp);
    if ($tarCodeApp === 0 && file_exists($appBuildBackupFile) && filesize($appBuildBackupFile) > 0) {
        $appBuildSizeKb = round(filesize($appBuildBackupFile) / 1024, 2);
        echo "[OK] Assets internos de la app (app/public/build) respaldados: {$appBuildBackupFile} ({$appBuildSizeKb} KB)\n";
    }
}

echo "\nRESPALDO_VERIFICADO_OK: Todos los componentes (BD, .env, assets verificados) respaldados con éxito en {$backupDir}\n";
exit(0);

