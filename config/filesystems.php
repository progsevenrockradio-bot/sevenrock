<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => env('APP_ENV') === 'testing'
                ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-public'
                : storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'radioboss' => [
            'driver' => 'ftp',
            'host' => env('RADIOBOSS_FTP_SERVER', env('RADIOBOSS_FTP_HOST', '')),
            'username' => env('RADIOBOSS_FTP_USER', env('RADIOBOSS_FTP_USERNAME', '')),
            'password' => env('RADIOBOSS_FTP_PASS', env('RADIOBOSS_FTP_PASSWORD', '')),
            'port' => (int) env('RADIOBOSS_FTP_PORT', 21),
            'root' => env('RADIOBOSS_FTP_ROOT', '/'),
            'passive' => filter_var(env('RADIOBOSS_FTP_PASSIVE', true), FILTER_VALIDATE_BOOL),
            'ssl' => filter_var(env('RADIOBOSS_FTP_SSL', false), FILTER_VALIDATE_BOOL),
            'timeout' => (int) env('RADIOBOSS_FTP_TIMEOUT', 60),
            'clear_before_upload' => filter_var(env('RADIOBOSS_FTP_CLEAR_BEFORE_UPLOAD', false), FILTER_VALIDATE_BOOL),
            'verify_after_upload' => filter_var(env('RADIOBOSS_FTP_VERIFY_AFTER_UPLOAD', false), FILTER_VALIDATE_BOOL),
            'scan_remote_for_episode_number' => filter_var(env('RADIOBOSS_FTP_SCAN_REMOTE_FOR_EPISODE_NUMBER', false), FILTER_VALIDATE_BOOL),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'backblaze' => [
            'driver' => 'backblaze',
            'account_id' => env('BACKBLAZE_ACCOUNT_ID', env('BACKBLAZE_B2_KEY_ID', '')),
            'application_key' => env('BACKBLAZE_APPLICATION_KEY', env('BACKBLAZE_B2_APPLICATION_KEY', '')),
            'bucket_id' => env('BACKBLAZE_BUCKET_ID', env('BACKBLAZE_B2_BUCKET_ID', '')),
            'bucket_name' => env('BACKBLAZE_BUCKET_NAME', env('BACKBLAZE_B2_BUCKET', '')),
            'url' => env('BACKBLAZE_URL', env('BACKBLAZE_B2_URL', '')),
            'prefix' => env('BACKBLAZE_PREFIX', env('BACKBLAZE_B2_PREFIX', '')),
            'throw' => false,
            'report' => false,
        ],

        'backblaze-b2' => [
            'driver' => 'backblaze',
            'account_id' => env('BACKBLAZE_ACCOUNT_ID', env('BACKBLAZE_B2_KEY_ID', '')),
            'application_key' => env('BACKBLAZE_APPLICATION_KEY', env('BACKBLAZE_B2_APPLICATION_KEY', '')),
            'bucket_id' => env('BACKBLAZE_BUCKET_ID', env('BACKBLAZE_B2_BUCKET_ID', '')),
            'bucket_name' => env('BACKBLAZE_BUCKET_NAME', env('BACKBLAZE_B2_BUCKET', '')),
            'url' => env('BACKBLAZE_URL', env('BACKBLAZE_B2_URL', '')),
            'prefix' => env('BACKBLAZE_PREFIX', env('BACKBLAZE_B2_PREFIX', '')),
            'throw' => false,
            'report' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
            'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('CLOUDFLARE_R2_BUCKET'),
            'url' => env('CLOUDFLARE_R2_URL'),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
