<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "<h3>Corrigiendo estructura de la tabla...</h3>";
    
    // 1. Modificar columna id para agregar PRIMARY KEY y AUTO_INCREMENT
    DB::statement("ALTER TABLE radio_artists MODIFY COLUMN id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY;");
    echo "<p style='color:green;'>✔️ Tabla 'radio_artists' corregida: columna 'id' ahora es AUTO_INCREMENT y PRIMARY KEY.</p>";
    
    // 2. Modificar columna name para que sea UNIQUE (para coincidir con la migración original)
    try {
        DB::statement("ALTER TABLE radio_artists ADD UNIQUE (name);");
        echo "<p style='color:green;'>✔️ Índice único en 'name' creado correctamente.</p>";
    } catch (\Throwable $ex) {
        echo "<p style='color:orange;'>⚠️ No se pudo crear el índice único en 'name' (posiblemente ya existe o hay duplicados): " . htmlspecialchars($ex->getMessage()) . "</p>";
    }

} catch (\Throwable $e) {
    echo "<h3 style='color:red;'>Error al corregir:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
