<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Keep compiled Blade views inside the project so local development does
    | not depend on the Windows temp directory, which can retain stale files
    | across restarts and cause unserialization issues.
    |
    */
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
