<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Keep compiled views inside the app so deployment clears are deterministic.
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
