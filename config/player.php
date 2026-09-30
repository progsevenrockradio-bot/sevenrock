<?php

/**
 * Reproductor y servidores de emision.
 *
 * ============================================================================
 *  PARA CAMBIAR DE EMISORA SOLO HAY QUE TOCAR **UNA LINEA** EN EL .env:
 *
 *      RADIO_ACTIVE_SERVER=radioboss        <- el que suena ahora
 *      RADIO_ACTIVE_SERVER=extassis         <- RadioBOSS y EXTASSIS
 *      RADIO_ACTIVE_SERVER=otra             <- una emisora nueva
 *
 *  El reproductor, la app (PWA) y las vistas NO se tocan: todos leen
 *  config('player.streams.*'), y eso se rellena solo con la emisora activa.
 *
 *  Despues de cambiar el .env hay que limpiar la cache de configuracion:
 *  borrar bootstrap/cache/config.php, o  php artisan config:clear
 * ============================================================================
 *
 *  Cada emisora tiene su bloque aqui abajo. Para anadir otra en el futuro:
 *  copiar el bloque 'otra', rellenar las URLs y poner RADIO_ACTIVE_SERVER=esa.
 *  Tambien se pueden cambiar las URLs desde el .env sin tocar este fichero
 *  (por ejemplo RADIOBOSS_STREAM_DIRECT=..., EXTASSIS_STREAM_DIRECT=...).
 */

$servidores = [

    // ------------------------------------------------------------------ RadioBOSS Cloud
    'radioboss' => [
        'nombre'     => 'RadioBOSS Cloud',
        'player'     => env('RADIOBOSS_STREAM_PLAYER', 'html5'),
        'direct'     => env('RADIOBOSS_STREAM_DIRECT', 'https://c30.radioboss.fm:8569/stream'),
        'alt_direct' => env('RADIOBOSS_STREAM_ALT', 'https://c30.radioboss.fm:18569/stream'),
        'listen'     => env('RADIOBOSS_STREAM_LISTEN', 'https://c30.radioboss.fm/stream/569'),
        'm3u'        => env('RADIOBOSS_STREAM_M3U', 'https://c30.radioboss.fm/playlist/569/stream.m3u'),
        'pls'        => env('RADIOBOSS_STREAM_PLS', 'https://c30.radioboss.fm/playlist/569/stream.pls'),
        'stats'      => env('RADIOBOSS_STREAM_STATS', 'https://c30.radioboss.fm:8569/status-json.xsl'),
        'api_url'    => env('RADIOBOSS_API_URL', 'https://c30.radioboss.fm'),
        'station_id' => env('RADIOBOSS_STATION_ID', '569'),
        'api_key'    => env('RADIOBOSS_API_KEY'),
    ],

    // ------------------------------------------------------------------ EXTASSIS NETwork
    // ShoutCast DNAS v2. El "/;" del final es la forma correcta de pedir el stream 1 (SID 1).
    'extassis' => [
        'nombre'     => 'EXTASSIS NETwork',
        'player'     => env('EXTASSIS_STREAM_PLAYER', 'html5'),
        'direct'     => env('EXTASSIS_STREAM_DIRECT', 'https://radios.mipanel.stream:6794/;'),
        'alt_direct' => env('EXTASSIS_STREAM_ALT', 'https://radios.mipanel.stream:6796/;'),
        'listen'     => env('EXTASSIS_STREAM_LISTEN', 'https://radios.mipanel.stream:6794/;'),
        'm3u'        => env('EXTASSIS_STREAM_M3U'),
        'pls'        => env('EXTASSIS_STREAM_PLS'),
        // Ojo: es un JSON de ShoutCast, no el status-json.xsl de Icecast/RadioBOSS.
        'stats'      => env('EXTASSIS_STREAM_STATS', 'https://radios.mipanel.stream:6794/stats?sid=1&json=1'),
        'stats_alt'  => env('EXTASSIS_STREAM_STATS_ALT', 'https://radios.mipanel.stream:6796/stats?sid=1&json=1'),
        'api_url'    => env('EXTASSIS_API_URL'),
        'station_id' => env('EXTASSIS_STATION_ID', '6794'),
        'api_key'    => env('EXTASSIS_API_KEY'),
    ],

    // ------------------------------------------------------------------ Otra emisora (plantilla)
    // Rellenar en el .env y poner RADIO_ACTIVE_SERVER=otra
    'otra' => [
        'nombre'     => env('OTRA_STREAM_NOMBRE', 'Emisora nueva'),
        'player'     => env('OTRA_STREAM_PLAYER', 'html5'),
        'direct'     => env('OTRA_STREAM_DIRECT'),
        'alt_direct' => env('OTRA_STREAM_ALT'),
        'listen'     => env('OTRA_STREAM_LISTEN'),
        'm3u'        => env('OTRA_STREAM_M3U'),
        'pls'        => env('OTRA_STREAM_PLS'),
        'stats'      => env('OTRA_STREAM_STATS'),
        'api_url'    => env('OTRA_STREAM_API_URL'),
        'station_id' => env('OTRA_STREAM_STATION_ID'),
        'api_key'    => env('OTRA_STREAM_API_KEY'),
    ],
];

// Cual esta activa. Si el nombre no existe, se usa RadioBOSS (nunca se queda sin emisora).
$activo = strtolower(trim((string) env('RADIO_ACTIVE_SERVER', 'radioboss')));
if (! isset($servidores[$activo])) {
    $activo = 'radioboss';
}
$elegido = $servidores[$activo];

/*
 * CONMUTACION AUTOMATICA ENTRE EMISORAS (opcional, pero recomendada):
 * el reproductor ya intenta primero 'direct' y, si falla, salta solo a 'alt_direct'.
 * Poniendo RADIO_STREAM_FAILOVER=radioboss, el plan B pasa a ser el OTRO servidor:
 * si EXTASSIS se cae, la web sigue sonando por RadioBOSS sin que nadie toque nada.
 * (Vacio = el plan B es el segundo puerto de la misma emisora.)
 */
$respaldo = strtolower(trim((string) env('RADIO_STREAM_FAILOVER', '')));
if ($respaldo !== '' && isset($servidores[$respaldo]) && $respaldo !== $activo) {
    $elegido['alt_direct'] = $servidores[$respaldo]['direct'];
    $elegido['alt_nombre'] = $servidores[$respaldo]['nombre'];
} else {
    $elegido['alt_nombre'] = $elegido['nombre'];
}

/*
 * IMPORTANTE: manda SIEMPRE la emisora activa. Las cinco variables antiguas
 * (RADIO_STREAM_DIRECT, _ALT, _LISTEN, _M3U, _PLS) solo se usan si el bloque de esa emisora
 * estuviera vacio, cosa que no pasa porque traen su valor por defecto.
 * Se pueden borrar del .env con tranquilidad: ya no hacen falta para cambiar de emisora.
 */
return [
    'active_server' => $activo,
    'active_name' => $elegido['nombre'],
    'active_alt_name' => $elegido['alt_nombre'],
    'servers' => $servidores,

    'streams' => [
        'direct'     => $elegido['direct']     ?: env('RADIO_STREAM_DIRECT'),
        'alt_direct' => $elegido['alt_direct'] ?: env('RADIO_STREAM_DIRECT_ALT'),
        'listen'     => $elegido['listen']     ?: env('RADIO_STREAM_LISTEN'),
        'm3u'        => $elegido['m3u']        ?: env('RADIO_STREAM_M3U'),
        'pls'        => $elegido['pls']        ?: env('RADIO_STREAM_PLS'),
        'stats'      => $elegido['stats'],
    ],

    'webhook' => [
        'key' => env('RADIOBOSS_WEBHOOK_KEY'),
        'path' => env('RADIOBOSS_WEBHOOK_PATH', 'radio/metadata'),
    ],

    'state' => [
        'file' => env('RADIO_PLAYER_STATE_FILE', 'radio/nowplaying.json'),
        'cover_path' => env('RADIO_PLAYER_COVER_PATH', 'radio/current-cover.jpg'),
    ],

    'radioboss' => [
        'api_url' => $servidores['radioboss']['api_url'],
        'station_id' => $servidores['radioboss']['station_id'],
        'api_key' => $servidores['radioboss']['api_key'],
        'metadata_txt_url' => env('RADIO_METADATA_TXT_URL'),
    ],

    'poll_interval' => (int) env('RADIO_PLAYER_POLL_INTERVAL', 10),
    'history_limit' => (int) env('RADIO_PLAYER_HISTORY_LIMIT', 10),

    'defaults' => [
        'artist' => 'Seven Rock Radio',
        'title' => 'Transmisión oficial',
        'show' => 'Programación habitual',
        'cover' => 'assets/lucille/album3.jpg',
    ],
];
