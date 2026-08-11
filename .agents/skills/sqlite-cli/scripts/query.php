<?php

$dbPath = $argv[1] ?? null;
$query = $argv[2] ?? null;

if (!$dbPath || !$query) {
    echo "Uso: php query.php <ruta_relativa_db> \"<consulta_sql>\"\n";
    exit(1);
}

$absolutePath = realpath(__DIR__ . '/../../../../' . $dbPath);

if (!$absolutePath || !file_exists($absolutePath)) {
    echo "Error: No se encontró la base de datos en $absolutePath\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $absolutePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query($query);
    if ($stmt) {
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (PDOException $e) {
    echo "Error SQL: " . $e->getMessage() . "\n";
    exit(1);
}
