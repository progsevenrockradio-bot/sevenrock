<?php
$logFile = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -150);
    echo "<h2>Últimas 150 líneas del log de Laravel:</h2>";
    echo "<pre style='background:#111; color:#eee; padding:15px; overflow:auto; font-family:monospace;'>" . htmlspecialchars(implode("", $lastLines)) . "</pre>";
} else {
    echo "El archivo de log no existe en: " . realpath($logFile);
}
