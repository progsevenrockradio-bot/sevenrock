<?php

return [
    'streams' => [
        'direct' => env('RADIO_STREAM_DIRECT', 'https://c30.radioboss.fm:8569/stream'),
        'alt_direct' => env('RADIO_STREAM_DIRECT_ALT', 'https://c30.radioboss.fm:18569/stream'),
        'listen' => env('RADIO_STREAM_LISTEN', 'https://c30.radioboss.fm/stream/569'),
        'm3u' => env('RADIO_STREAM_M3U', 'https://c30.radioboss.fm/playlist/569/stream.m3u'),
        'pls' => env('RADIO_STREAM_PLS', 'https://c30.radioboss.fm/playlist/569/stream.pls'),
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
        'api_url' => env('RADIOBOSS_API_URL', 'https://c30.radioboss.fm'),
        'station_id' => env('RADIOBOSS_STATION_ID', '569'),
        'api_key' => env('RADIOBOSS_API_KEY'),
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
