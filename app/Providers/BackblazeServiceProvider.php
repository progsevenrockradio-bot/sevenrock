<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Zaxbux\BackblazeB2\Client;
use Zaxbux\Flysystem\BackblazeB2Adapter;

class BackblazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Storage::extend('backblaze', function ($app, array $config): FilesystemAdapter {
            $bucketId = trim((string) ($config['bucket_id'] ?? ''));
            $prefix = trim((string) ($config['prefix'] ?? ''));

            $client = new Client([
                'applicationKeyId' => (string) ($config['account_id'] ?? ''),
                'applicationKey' => (string) ($config['application_key'] ?? ''),
            ]);

            $adapter = new BackblazeB2Adapter(
                $client,
                $bucketId !== '' ? $bucketId : null,
                $prefix !== '' ? $prefix : null,
            );

            $filesystem = new Filesystem($adapter, $config);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
