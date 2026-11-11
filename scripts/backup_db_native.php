<?php

declare(strict_types=1);

/**
 * Script de respaldo seguro de base de datos utilizando la configuración real de Laravel.
 * Evita recortes de texto frágiles (cut / sed) que puedan corromper contraseñas con caracteres especiales.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbConfig = config('database.connections.mysql', []);
$host = (string) ($dbConfig['host'] ?? '127.0.0.1');
$port = (string) ($dbConfig['port'] ?? '3306');
$user = (string) ($dbConfig['username'] ?? '');
$pass = (string) ($dbConfig['password'] ?? '');
$dbName = (string) ($dbConfig['database'] ?? '');

if ($user === '' || $dbName === '') {
    fwrite(STDERR, "[ERROR] Configuración de base de datos incompleta en Laravel.\n");
    exit(1);
}

$backupDir = dirname(__DIR__, 2) . '/backups_pre_autopublish';
if (! is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

$timestamp = date('Ymd_His');
$backupFile = "{$backupDir}/db_backup_pre_autopublish_{$timestamp}.sql";

echo "Iniciando respaldo seguro de base de datos '{$dbName}' hacia: {$backupFile}\n";

// Configurar entorno con MYSQL_PWD de forma nativa para evitar exposición en ps y sin alterar caracteres
putenv("MYSQL_PWD={$pass}");

$cmd = sprintf(
    'mysqldump --no-tablespaces -h %s -P %s -u %s %s > %s',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    escapeshellarg($dbName),
    escapeshellarg($backupFile)
);

exec($cmd, $output, $returnCode);

if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
    $sizeKb = round(filesize($backupFile) / 1024, 2);
    echo "RESPALDO_VERIFICADO_OK: {$backupFile} ({$sizeKb} KB)\n";
    exit(0);
} else {
    fwrite(STDERR, "[ERROR] Falló mysqldump (código de salida {$returnCode}). Compruebe permisos y conectividad.\n");
    exit(1);
}
