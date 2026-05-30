<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

// Clave temporal para resetear OPcache una sola vez.
$expectedKey = '7f4c1d9a2e8b4c0d9f7a1e3c5b6d8f0a';
$providedKey = $_GET['key'] ?? '';

if (!is_string($providedKey) || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

if (!function_exists('opcache_reset')) {
    http_response_code(500);
    echo "OPcache is not available\n";
    exit;
}

$result = opcache_reset();

echo $result ? "OPcache reset\n" : "OPcache reset returned false\n";
