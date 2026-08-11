<?php
// Script para uso exclusivo del agente IA. Ejecuta consultas SQL en el entorno local.
require_once __DIR__ . '/../../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$query = $argv[1] ?? '';
if (empty($query)) {
    echo json_encode(["error" => "No query provided"]);
    exit(1);
}

try {
    $results = Illuminate\Support\Facades\DB::select($query);
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
    exit(1);
}
