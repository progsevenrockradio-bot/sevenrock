<?php

require __DIR__ . '/../../../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->safeLoad();

$apiUrl = $_ENV['RADIOBOSS_API_URL'] ?? null;
$stationId = $_ENV['RADIOBOSS_STATION_ID'] ?? null;
$apiKey = $_ENV['RADIOBOSS_API_KEY'] ?? null;

if (!$apiUrl || !$stationId || !$apiKey) {
    echo "Error: Credenciales de RADIOBOSS (API_URL, STATION_ID, API_KEY) no encontradas en .env\n";
    exit(1);
}

$url = rtrim($apiUrl, '/') . "/api/info/{$stationId}?key={$apiKey}";

$options = [
    'http' => [
        'header' => "Accept: application/json\r\n",
        'timeout' => 5
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error al conectar con RadioBoss API.\n";
    exit(1);
}

$data = json_decode($response, true);
if (!$data) {
    echo "Respuesta no válida de RadioBoss.\n";
    exit(1);
}

echo "📻 Estación: " . ($data['station_name'] ?? 'Desconocida') . "\n";
echo "🎵 Sonando ahora: " . ($data['nowplaying'] ?? 'Nada') . "\n";
echo "👥 Oyentes: " . ($data['listeners'] ?? '0') . "\n";
echo "▶️ Próxima pista: " . ($data['nexttrack'] ?? 'Desconocido') . "\n";
