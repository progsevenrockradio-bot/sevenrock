<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discogs' => [
        'token' => env('DISCOGS_API_TOKEN', env('DISCOGS_TOKEN')),
    ],

    'lastfm' => [
        'key' => env('LASTFM_KEY'),
        'api_key' => env('LASTFM_API_KEY'),
        'secret' => env('LASTFM_API_SECRET'),
    ],

    'genius' => [
        'token' => env('GENIUS_API_TOKEN'),
    ],

    'musixmatch' => [
        'key' => env('MUSIXMATCH_API_KEY'),
    ],

    'gemini' => [
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    'archive_org' => [
        'access_key' => env('ARCHIVE_ORG_ACCESS_KEY'),
        'secret_key' => env('ARCHIVE_ORG_SECRET_KEY'),
        'region' => env('ARCHIVE_REGION', 'us-east-1'),
        'bucket' => env('ARCHIVE_BUCKET'),
        'endpoint' => env('ARCHIVE_ENDPOINT', 'https://s3.us.archive.org'),
        'collection' => env('ARCHIVE_COLLECTION', 'opensource_audio'),
        'mediatype' => env('ARCHIVE_MEDIATYPE', 'audio'),
    ],

    'notifications' => [
        'mailer' => env('NOTIFICATION_MAILER'),
    ],

    'podcast_ingest' => [
        'enabled' => env('PODCAST_INGEST_ENABLED', true),
        'inbox_dir' => env('PODCAST_INGEST_INBOX_DIR', 'podcast-inbox'),
        'processing_dir' => env('PODCAST_INGEST_PROCESSING_DIR', 'podcast-inbox/processing'),
        'error_dir' => env('PODCAST_INGEST_ERROR_DIR', 'podcast-inbox/error'),
        'json_dir' => env('PODCAST_INGEST_JSON_DIR', 'podcast-inbox/generated-json'),
        'default_sync_archive_org' => env('PODCAST_INGEST_DEFAULT_SYNC_ARCHIVE_ORG', true),
    ],

    'imap' => [
        'host' => env('IMAP_HOST', 'imap.gmail.com'),
        'port' => env('IMAP_PORT', 993),
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'username' => env('IMAP_USERNAME'),
        'password' => env('IMAP_PASSWORD'),
        'validate_cert' => env('IMAP_VALIDATE_CERT', true),
        'archive_bucket' => env('EMAIL_ARCHIVE_BUCKET'),
    ],

];
