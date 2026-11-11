<?php

declare(strict_types=1);

/**
 * Script de respaldo seguro y consistente previo a despliegue.
 * Respalda Base de Datos (vía conexión nativa de Laravel), Configuración (.env) y Assets (public/build).
 * Puede ejecutarse desde el repositorio o copiarse fuera de él apuntando a --app-path.
 */

// Parsear argumento opcional --app-path
$appPath = dirname(__DIR__);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--app-path=')) {
        $appPath = substr($arg, 11);
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
$backupDir = $baseDir . '/backups_pre_autopublish';
if (! is_dir($backupDir)) {
    if (! mkdir($backupDir, 0750, true) && ! is_dir($backupDir)) {
        fwrite(STDERR, "[ERROR CRÍTICO] No se pudo crear el directorio de respaldos: {$backupDir}\n");
        exit(1);
    }
}

echo "=== INICIANDO RESPALDO PRE-DESPLIEGUE VERIFICADO ({$timestamp}) ===\n";
echo "Ruta de la aplicación: {$appPath}\n";
echo "Directorio de respaldos: {$backupDir}\n\n";

// 1. RESPALDO DE BASE DE DATOS
$dbConfig = config('database.connections.mysql', []);
$host = (string) ($dbConfig['host'] ?? '127.0.0.1');
$port = (string) ($dbConfig['port'] ?? '3306');
$user = (string) ($dbConfig['username'] ?? '');
$pass = (string) ($dbConfig['password'] ?? '');
$dbName = (string) ($dbConfig['database'] ?? '');

if ($user === '' || $dbName === '') {
    fwrite(STDERR, "[ERROR] Configuración de base de datos vacía o ilegible en Laravel.\n");
    exit(1);
}

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

// 2. RESPALDO DE CONFIGURACIÓN (.env)
$envSource = $appPath . '/.env';
$envBackupFile = "{$backupDir}/env_backup_{$timestamp}.backup";
if (file_exists($envSource)) {
    if (! copy($envSource, $envBackupFile) || filesize($envBackupFile) === 0) {
        fwrite(STDERR, "[ERROR CRÍTICO] Falló el respaldo de configuración (.env). Abortando sin continuar.\n");
        exit(1);
    }
    echo "[OK] Configuración (.env) respaldada: {$envBackupFile} (" . filesize($envBackupFile) . " bytes)\n";
} else {
    fwrite(STDERR, "[AVISO] No existe archivo .env en {$appPath}\n");
}

// 3. RESPALDO DE ASSETS (public/build)
$buildSource = $appPath . '/public/build';
$buildBackupFile = "{$backupDir}/build_assets_{$timestamp}.tar.gz";
if (is_dir($buildSource)) {
    $tarCmd = sprintf('tar -czf %s -C %s build', escapeshellarg($buildBackupFile), escapeshellarg($appPath . '/public'));
    exec($tarCmd, $tarOutput, $tarCode);
    if ($tarCode !== 0 || ! file_exists($buildBackupFile) || filesize($buildBackupFile) === 0) {
        fwrite(STDERR, "[ERROR CRÍTICO] Falló el empaquetado de assets compilados (código {$tarCode}). Abortando.\n");
        exit(1);
    }
    $buildSizeKb = round(filesize($buildBackupFile) / 1024, 2);
    echo "[OK] Assets compilados respaldados: {$buildBackupFile} ({$buildSizeKb} KB)\n";
} else {
    echo "[INFO] No se encontró directorio public/build en {$appPath}; omitiendo archivo de assets.\n";
}

echo "\nRESPALDO_VERIFICADO_OK: Todos los componentes (BD, .env, assets) respaldados con éxito en {$backupDir}\n";
exit(0);

