<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 4));
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$folder = '10.- HEAVY METAL';

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

echo "Limpiador de Duplicados en FTP: /$folder\n";
echo "=================================================\n";

$files = ftp_nlist($c, "/$folder");

if (is_array($files)) {
    $groups = [];
    
    // Agrupar archivos ignorando números iniciales
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'mp3') {
            $basename = basename($file);
            // Quitar números iniciales como "01 - ", "12 - ", "53 - "
            $cleanName = preg_replace('/^\d+\s*-\s*/', '', $basename);
            $groups[$cleanName][] = $file;
        }
    }
    
    $deletedCount = 0;
    
    foreach ($groups as $cleanName => $filePaths) {
        if (count($filePaths) > 1) {
            echo "\nEncontrados " . count($filePaths) . " duplicados para: $cleanName\n";
            // Ordenar para dejar el más "limpio" o corto
            sort($filePaths); 
            
            // Mantener el primero
            $keep = array_shift($filePaths);
            echo "[MANTENER] $keep\n";
            
            // Borrar el resto
            foreach ($filePaths as $del) {
                $fullPath = "/$folder/$del";
                if (ftp_delete($c, $fullPath)) {
                    echo "[BORRADO OK] $fullPath\n";
                    $deletedCount++;
                } else {
                    echo "[ERROR BORRAR] $del\n";
                }
            }
        }
    }
    
    echo "\n=================================================\n";
    echo "Limpieza finalizada. $deletedCount archivos duplicados eliminados.\n";
}

ftp_close($c);
