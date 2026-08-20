<?php
require dirname(__DIR__, 4) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 4));
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

$local_dir = 'C:\Users\JOSE FONT\Desktop\spot';
$remote_dir = '00.- PROMOS Y PISADORES'; // Carpeta a crear o usar

$c = ftp_connect($ftp_server);
if (!@ftp_login($c, $ftp_user, $ftp_pass)) {
    die("Login failed.\n");
}
ftp_pasv($c, true);

echo "Iniciando carga de música al servidor FTP...\n";
echo "Carpeta destino: /$remote_dir\n\n";

// Crear directorio si no existe
if (!@ftp_chdir($c, "/$remote_dir")) {
    if (ftp_mkdir($c, "/$remote_dir")) {
        echo "Carpeta creada exitosamente en el servidor.\n";
    } else {
        die("Error creando la carpeta /$remote_dir.\n");
    }
} else {
    // volver a la raiz
    ftp_chdir($c, "/");
}

$files = glob(rtrim($local_dir, '\\/') . '/*.mp3');
$total = count($files);
$uploaded = 0;

foreach ($files as $file) {
    $basename = basename($file);
    $remote_file = "/$remote_dir/$basename";
    
    echo "Subiendo: $basename ... ";
    
    if (ftp_put($c, $remote_file, $file, FTP_BINARY)) {
        echo "[OK]\n";
        $uploaded++;
    } else {
        echo "[ERROR]\n";
    }
}

echo "\n=================================================\n";
echo "Carga finalizada. $uploaded de $total archivos subidos exitosamente.\n";

ftp_close($c);
