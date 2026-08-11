<?php

require __DIR__ . '/../../../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
$dotenv->safeLoad();

$key = $_ENV['BRAVE_API_KEY'] ?? null;

if (!$key) {
    echo "Error: BRAVE_API_KEY no encontrado en .env\n";
    exit(1);
}

$query = $argv[1] ?? null;

if (!$query) {
    echo "Uso: php search.php \"<termino_de_busqueda>\"\n";
    exit(1);
}

$encodedQuery = urlencode($query);
$url = "https://api.search.brave.com/res/v1/web/search?q={$encodedQuery}&count=5";

$options = [
    'http' => [
        'header' => "Accept: application/json\r\n" .
                    "Accept-Encoding: gzip\r\n" .
                    "X-Subscription-Token: {$key}\r\n",
        'method' => 'GET'
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error al conectar con Brave Search API.\n";
    exit(1);
}

// Handle gzip if necessary
$isGzip = (substr($response, 0, 2) === "\x1f\x8b");
if ($isGzip) {
    $response = gzdecode($response);
}

$data = json_decode($response, true);
$results = $data['web']['results'] ?? [];

if (empty($results)) {
    echo "No se encontraron resultados.\n";
    exit(0);
}

foreach ($results as $index => $item) {
    echo ($index + 1) . ". " . $item['title'] . "\n";
    echo "   URL: " . $item['url'] . "\n";
    echo "   " . $item['description'] . "\n\n";
}
