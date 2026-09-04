<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 4));
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

echo "Iniciando LIMPIEZA MASIVA de la librería...\n";
echo "Esta operación puede tardar varios minutos.\n\n";

$folders = ftp_nlist($c, '/');
$totalFiles = 0;
$totalDeleted = 0;
$deletedList = [];

function scan_recursive($ftp, string $dir, array &$filesList): void {
    $contents = ftp_nlist($ftp, $dir);
    if (is_array($contents)) {
        foreach ($contents as $item) {
            $base = basename($item);
            if ($base == '.' || $base == '..') continue;
            
            $fullPath = (strpos($item, $dir) === 0) ? $item : rtrim($dir, '/') . '/' . $base;
            
            if (pathinfo($fullPath, PATHINFO_EXTENSION) == 'mp3') {
                $filesList[] = $fullPath;
            } elseif (pathinfo($fullPath, PATHINFO_EXTENSION) == '') {
                scan_recursive($ftp, $fullPath, $filesList);
            }
        }
    }
}

$allMp3Files = [];
foreach ($folders as $folder) {
    if (in_array(basename($folder), ['.', '..'])) continue;
    if (!preg_match('/^\d{2}\.-/', basename($folder))) continue;

    echo "Escaneando árbol de $folder...\n";
    $files = [];
    scan_recursive($c, $folder, $files);
    $allMp3Files = array_merge($allMp3Files, $files);
}

echo "\nEscaneo completo. " . count($allMp3Files) . " archivos MP3 encontrados en total.\n";
echo "Analizando reglas de limpieza...\n\n";

$groups = [];
foreach ($allMp3Files as $file) {
    $basename = basename($file);
    // Extraer nombre limpio (borrando '01 - ', '01.', etc)
    $cleanName = preg_replace('/^[\d\.\s-]+/', '', $basename);
    $lowername = strtolower($basename);
    
    // Regla 1: Borrar Live / Acoustic
    if (strpos($lowername, 'live') !== false || strpos($lowername, 'acoustic') !== false) {
        if (ftp_delete($c, $file)) {
            echo "[BORRADO LIVE/ACUSTICO] $file\n";
            $totalDeleted++;
            $deletedList[] = $file;
        } else {
            echo "[ERROR BORRADO] $file\n";
        }
        continue;
    }
    
    // Agrupar para Regla 2 (Duplicados)
    $groups[$cleanName][] = $file;
}

echo "\nAnalizando duplicados...\n";
foreach ($groups as $cleanName => $filePaths) {
    if (count($filePaths) > 1) {
        // Encontrar el nombre más corto/limpio para quedarnos con ese
        usort($filePaths, function($a, $b) {
            return strlen(basename($a)) <=> strlen(basename($b));
        });
        
        $keep = array_shift($filePaths); // Mantenemos el primero
        
        foreach ($filePaths as $del) {
            if (ftp_delete($c, $del)) {
                echo "[BORRADO DUPLICADO] $del\n";
                $totalDeleted++;
                $deletedList[] = $del;
            } else {
                echo "[ERROR BORRADO] $del\n";
            }
        }
    }
}

echo "\n=================================================\n";
echo "Limpieza finalizada con ÉXITO.\n";
echo "Archivos eliminados en total: $totalDeleted\n";

file_put_contents(__DIR__ . '/massive_clean_report.json', json_encode($deletedList, JSON_PRETTY_PRINT));

ftp_close($c);
