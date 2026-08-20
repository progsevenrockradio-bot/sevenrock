<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$ftp_server = $_ENV['RADIOBOSS_FTP_SERVER'];
$ftp_user = $_ENV['RADIOBOSS_FTP_USER'];
$ftp_pass = $_ENV['RADIOBOSS_FTP_PASS'];

echo "Connecting to $ftp_server as $ftp_user...\n";
$c = ftp_connect($ftp_server);
if (@ftp_login($c, $ftp_user, $ftp_pass)) {
    ftp_pasv($c, true);
    echo "Login success. ROOT:\n";
    print_r(ftp_nlist($c, '/'));
} else {
    echo "Login failed.\n";
}
ftp_close($c);
