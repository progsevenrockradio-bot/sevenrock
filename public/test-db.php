<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Iniciar la app de consola para no disparar toda la pila HTTP completa
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $res = DB::select("SHOW COLUMNS FROM radio_artists");
    echo "<h2>Estructura de la tabla radio_artists:</h2>";
    echo "<pre style='background:#111; color:#eee; padding:15px; overflow:auto;'>";
    foreach ($res as $col) {
        printf("Campo: %-15s | Tipo: %-15s | Null: %-3s | Key: %-3s | Default: %-10s | Extra: %s\n",
            $col->Field,
            $col->Type,
            $col->Null,
            $col->Key,
            $col->Default ?? 'NULL',
            $col->Extra
        );
    }
    echo "</pre>";
} catch (\Throwable $e) {
    echo "<h2>Error al conectar o consultar DB:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
