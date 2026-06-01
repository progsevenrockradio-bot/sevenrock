<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use App\Support\ExternalHttp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ArchiveOrgPodcastService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 900,
            'connect_timeout' => 30,
            'read_timeout' => 900,
            'http_errors' => true,
        ]);
    }

    public function syncEpisode(RadioProgram $episode): array
    {
        $episode->loadMissing('masterProgram');

        if (! $this->canSync()) {
            throw new RuntimeException('Faltan credenciales de Archive.org en services.archive_org.');
        }

        $master = $episode->masterProgram;
        $identifier = $this->resolveIdentifier($episode, $master);

        try {
            $absolutePath = $this->resolveLocalPath($episode);
        } catch (Throwable $exception) {
            $episode->forceFill([
                'archive_org_status' => 'error',
                'archive_org_last_error' => $exception->getMessage(),
                'status_message' => 'Archive.org no pudo iniciar: falta el MP3 local.',
            ])->saveQuietly();

            throw $exception;
        }
        $remotePath = $this->resolveRemotePath($episode);
        $itemExistsBefore = $this->itemExists($identifier);
        $created = ! $itemExistsBefore;
        $itemMetadata = $this->buildItemMetadata($episode, $master);
        $fileMetadata = $this->buildEpisodeFileMetadata($episode, $master);
        $snapshot = $this->buildArchiveSnapshot(
            episode: $episode,
            master: $master,
            identifier: $identifier,
            remotePath: $remotePath,
            itemExistsBefore: $itemExistsBefore,
            created: $created,
            itemMetadata: $itemMetadata,
            fileMetadata: $fileMetadata,
        );

        $episode->forceFill([
            'archive_org_status' => 'pending',
            'archive_org_last_error' => null,
            'archive_org_metadata' => $snapshot,
            'status_message' => 'Sincronizando Archive.org.',
        ])->saveQuietly();

        $this->uploadFile($identifier, $remotePath, $absolutePath, $itemMetadata);

        $episode->forceFill([
            'archive_org_remote_path' => $remotePath,
        ])->saveQuietly();

        if (! $this->waitForRemoteFileOnArchiveOrg($identifier, $remotePath)) {
            Log::warning('ArchiveOrgPodcastService: file not reachable after waiting, continuing with metadata patch anyway.', [
                'identifier' => $identifier,
                'remote_path' => $remotePath,
            ]);
        }

        $this->applyEpisodeMetadata($identifier, $remotePath, $fileMetadata);

        $verification = $this->verifyUploadedEpisode($identifier, $remotePath, $absolutePath);
        $pendingIndexing = (bool) ($verification['pending_indexing'] ?? false);

        if (! ($verification['verified'] ?? false) && ! $pendingIndexing) {
            throw new RuntimeException((string) ($verification['message'] ?? 'No se pudo verificar la subida en Archive.org.'));
        }

        if ($master instanceof MasterProgram && blank($master->archive_identifier)) {
            $master->forceFill(['archive_identifier' => $identifier])->saveQuietly();
        }

        $episode->forceFill([
            'archive_org_status' => $pendingIndexing ? 'pending' : 'synced',
            'archive_org_remote_path' => $remotePath,
            'archive_org_uploaded_at' => now(),
            'archive_org_verified_at' => $pendingIndexing ? null : now(),
            'archive_org_last_error' => null,
            'archive_org_metadata' => array_merge($snapshot, [
                'status' => $pendingIndexing ? 'pending' : 'synced',
                'synced_at' => $pendingIndexing ? null : now()->toIso8601String(),
                'remote_path' => $remotePath,
                'verification' => $verification,
                'pending_indexing' => $pendingIndexing,
            ]),
            'status_message' => $pendingIndexing
                ? 'Archive.org subido, en espera de indexación.'
                : 'Archive.org sincronizado correctamente.',
        ])->saveQuietly();

        $this->cleanupLocalPath($absolutePath, (string) $episode->archivo_mp3_disk);

        return [
            'success' => true,
            'created' => $created,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'item_exists_before' => $itemExistsBefore,
            'verification' => $verification,
            'status' => $pendingIndexing ? 'pending' : 'synced',
            'pending_indexing' => $pendingIndexing,
        ];
    }

    public function syncEpisodeMetadata(RadioProgram $episode): array
    {
        $episode->loadMissing('masterProgram');

        if (! $this->canSync()) {
            throw new RuntimeException('Faltan credenciales de Archive.org en services.archive_org.');
        }

        $master = $episode->masterProgram;
        $identifier = $this->resolveIdentifier($episode, $master);
        $remotePath = trim((string) ($episode->archive_org_remote_path ?: $this->resolveRemotePath($episode)));

        if ($remotePath === '') {
            throw new RuntimeException('No se pudo determinar el archivo remoto del capítulo.');
        }

        try {
            $absolutePath = $this->resolveLocalPath($episode);
        } catch (Throwable $exception) {
            $episode->forceFill([
                'archive_org_status' => 'error',
                'archive_org_last_error' => $exception->getMessage(),
                'status_message' => 'Archive.org no pudo verificar la metadata porque falta el MP3 local.',
            ])->saveQuietly();

            throw $exception;
        }

        if (! $this->itemExists($identifier)) {
            throw new RuntimeException("El item {$identifier} todavía no existe en Archive.org.");
        }

        $fileMetadata = $this->buildEpisodeFileMetadata($episode, $master);

        if (! $this->waitForRemoteFileOnArchiveOrg($identifier, $remotePath)) {
            Log::warning('ArchiveOrgPodcastService: file not reachable before metadata PATCH, continuing anyway.', [
                'identifier' => $identifier,
                'remote_path' => $remotePath,
            ]);
        }

        $this->applyEpisodeMetadata($identifier, $remotePath, $fileMetadata);
        $verification = $this->verifyUploadedEpisode($identifier, $remotePath, $absolutePath);

        $pendingIndexing = (bool) ($verification['pending_indexing'] ?? false);

        if (! ($verification['verified'] ?? false) && ! $pendingIndexing) {
            throw new RuntimeException((string) ($verification['message'] ?? 'No se pudo verificar la metadata de Archive.org.'));
        }

        $snapshot = array_merge((array) ($episode->archive_org_metadata ?? []), [
            'source' => 'archive-org-metadata-sync',
            'record_id' => $episode->id,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'status' => 'synced',
            'synced_at' => now()->toIso8601String(),
            'episode_metadata' => $fileMetadata,
            'verification' => $verification,
        ]);

        $episode->forceFill([
            'archive_org_status' => $pendingIndexing ? 'pending' : 'synced',
            'archive_org_remote_path' => $remotePath,
            'archive_org_uploaded_at' => $episode->archive_org_uploaded_at ?? now(),
            'archive_org_verified_at' => $pendingIndexing ? null : now(),
            'archive_org_last_error' => null,
            'archive_org_metadata' => $snapshot,
        ])->saveQuietly();

        $this->cleanupLocalPath($absolutePath, (string) $episode->archivo_mp3_disk);

        return [
            'success' => true,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'verification' => $verification,
        ];
    }

    public function canSync(): bool
    {
        return trim((string) config('services.archive_org.access_key', '')) !== ''
            && trim((string) config('services.archive_org.secret_key', '')) !== '';
    }

    /**
     * @return array{
     *     verified: bool,
     *     identifier: string,
     *     remote_path: string,
     *     local_size: int,
     *     remote_size: int|null,
     *     message: string|null,
     * }
     */
    private function verifyUploadedEpisode(string $identifier, string $remotePath, string $absolutePath): array
    {
        $localSize = is_file($absolutePath) ? (int) filesize($absolutePath) : 0;
        $largeFile = $localSize > 50 * 1024 * 1024;

        if ($largeFile) {
            try {
                $response = $this->client->request('HEAD', $this->downloadEndpoint() . '/' . rawurlencode($identifier) . '/' . $this->encodePath($remotePath), [
                    'http_errors' => false,
                ]);

                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                    return [
                        'verified' => false,
                        'pending_indexing' => true,
                        'identifier' => $identifier,
                        'remote_path' => $remotePath,
                        'local_size' => $localSize,
                        'remote_size' => null,
                        'message' => 'Archive.org todavía está indexando el archivo; HEAD no está disponible todavía.',
                    ];
                }

                $remoteSizeHeader = trim((string) $response->getHeaderLine('Content-Length'));
                $remoteSize = $remoteSizeHeader !== '' ? (int) $remoteSizeHeader : null;

                if ($remoteSize !== null && $localSize > 0 && $remoteSize !== $localSize) {
                    return [
                        'verified' => false,
                        'identifier' => $identifier,
                        'remote_path' => $remotePath,
                        'local_size' => $localSize,
                        'remote_size' => $remoteSize,
                        'message' => 'El tamaño remoto no coincide con el archivo local en Archive.org.',
                    ];
                }

                return [
                    'verified' => true,
                    'identifier' => $identifier,
                    'remote_path' => $remotePath,
                    'local_size' => $localSize,
                    'remote_size' => $remoteSize,
                    'message' => null,
                ];
            } catch (Throwable) {
                return [
                    'verified' => false,
                    'pending_indexing' => true,
                    'identifier' => $identifier,
                    'remote_path' => $remotePath,
                    'local_size' => $localSize,
                    'remote_size' => null,
                    'message' => 'Archive.org todavía está indexando el archivo; HEAD no está disponible todavía.',
                ];
            }
        }

        $remoteStream = $this->downloadRemoteFile($identifier, $remotePath);

        if (! is_resource($remoteStream)) {
            return [
                'verified' => false,
                'pending_indexing' => true,
                'identifier' => $identifier,
                'remote_path' => $remotePath,
                'local_size' => $localSize,
                'remote_size' => null,
                'message' => 'Archive.org todavía está indexando el archivo; el remoto aún no está disponible.',
            ];
        }

        $remoteSize = 0;
        $hashContext = hash_init('sha256');

        try {
            while (! feof($remoteStream)) {
                $chunk = fread($remoteStream, 8192);
                if ($chunk === false) {
                    break;
                }

                if ($chunk === '') {
                    continue;
                }

                $remoteSize += strlen($chunk);
                hash_update($hashContext, $chunk);
            }
        } finally {
            fclose($remoteStream);
        }

        $remoteChecksum = hash_final($hashContext);
        $localChecksum = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null;

        if ($localSize > 0 && $remoteSize !== $localSize) {
            return [
                'verified' => false,
                'identifier' => $identifier,
                'remote_path' => $remotePath,
                'local_size' => $localSize,
                'remote_size' => $remoteSize,
                'message' => 'El tamaño remoto no coincide con el archivo local en Archive.org.',
            ];
        }

        if (is_string($localChecksum) && $localChecksum !== '' && ! hash_equals($localChecksum, $remoteChecksum)) {
            return [
                'verified' => false,
                'identifier' => $identifier,
                'remote_path' => $remotePath,
                'local_size' => $localSize,
                'remote_size' => $remoteSize,
                'message' => 'El checksum remoto no coincide con el archivo local en Archive.org.',
            ];
        }

        return [
            'verified' => true,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'local_size' => $localSize,
            'remote_size' => $remoteSize,
            'message' => null,
        ];
    }

    private function downloadRemoteFile(string $identifier, string $remotePath): mixed
    {
        try {
            $response = $this->client->request('GET', $this->downloadEndpoint() . '/' . rawurlencode($identifier) . '/' . $this->encodePath($remotePath), [
                'stream' => true,
            ]);

            $body = $response->getBody();
            if (! method_exists($body, 'detach')) {
                return null;
            }

            $stream = fopen('php://temp', 'r+');
            if ($stream === false) {
                return null;
            }

            $remoteStream = $body->detach();
            if (! is_resource($remoteStream)) {
                fclose($stream);

                return null;
            }

            try {
                if (stream_copy_to_stream($remoteStream, $stream) === false) {
                    return null;
                }
            } finally {
                fclose($remoteStream);
            }

            rewind($stream);

            return $stream;
        } catch (Throwable) {
            return null;
        }
    }

    public function itemExists(string $identifier): bool
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return false;
        }

        try {
            $this->client->request('GET', $this->apiEndpoint() . '/metadata/' . rawurlencode($identifier));

            return true;
        } catch (ClientException $exception) {
            if ($exception->getResponse()?->getStatusCode() === 404) {
                return false;
            }

            throw $exception;
        } catch (Throwable) {
            return false;
        }
    }

    public function resolveIdentifier(RadioProgram $episode, ?MasterProgram $master = null): string
    {
        $configured = trim((string) ($master?->archive_identifier ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $base = trim((string) ($master?->nombre ?? $episode->titulo_programa ?? 'podcast'));
        $slug = Str::slug($base, '-');
        $suffix = max(1, (int) ($master?->id ?? $episode->id));

        return ($slug !== '' ? $slug : 'podcast') . '-' . $suffix;
    }

    private function resolveLocalPath(RadioProgram $episode): string
    {
        $stored = trim((string) ($episode->archivo_mp3 ?? ''));
        if ($stored === '') {
            throw new RuntimeException('El episodio no tiene archivo MP3 local asociado.');
        }

        $disk = (string) $episode->archivo_mp3_disk;
        $absolutePath = app(FileUploadService::class)->localPath($stored, $disk);
        if (! is_string($absolutePath) || $absolutePath === '' || ! file_exists($absolutePath)) {
            throw new RuntimeException("No se pudo leer el MP3 local: {$stored}");
        }

        return $absolutePath;
    }

    private function resolveRemotePath(RadioProgram $episode): string
    {
        $stored = trim((string) ($episode->archivo_mp3 ?? ''));
        if ($stored === '') {
            return '';
        }

        return basename(str_replace('\\', '/', $stored));
    }

    private function cleanupLocalPath(string $absolutePath, string $disk): void
    {
        if (! str_starts_with($absolutePath, storage_path('app/tmp/backblaze'))) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildItemMetadata(RadioProgram $episode, ?MasterProgram $master): array
    {
        $programTitle = trim((string) ($master?->nombre ?: $episode->titulo_programa));
        $description = trim((string) ($master?->descripcion ?: $episode->resena ?: $episode->comentario_episodio ?: ''));
        $creator = trim((string) ($master?->conductor ?: $episode->conductor ?: ''));

        return array_filter([
            'collection' => trim((string) config('services.archive_org.collection', 'opensource_audio')),
            'mediatype' => trim((string) config('services.archive_org.mediatype', 'audio')),
            'title' => $programTitle,
            'description' => strip_tags($description),
            'subject' => implode(', ', array_filter([
                $programTitle !== '' ? $programTitle : null,
                'podcast',
                'radio',
                trim((string) ($master?->genero ?: $episode->genero_musical ?: '')),
            ])),
            'creator' => $creator,
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @return array<string, string>
     */
    private function buildEpisodeFileMetadata(RadioProgram $episode, ?MasterProgram $master): array
    {
        $programTitle = trim((string) ($master?->nombre ?: $episode->titulo_programa));
        $episodeNumber = max(1, (int) ($episode->numero_episodio ?? 0));
        $title = trim((string) ($episode->live_title ?: $episode->comentario_episodio ?: $episode->resena ?: $programTitle));

        return array_filter([
            'title' => $title !== '' ? $title : $programTitle,
            'creator' => trim((string) ($episode->conductor ?: $master?->conductor ?: '')),
            'subject' => implode(', ', array_filter([
                $programTitle !== '' ? $programTitle : null,
                'episode ' . $episodeNumber,
                trim((string) ($master?->genero ?: $episode->genero_musical ?: '')),
            ])),
            'description' => strip_tags(trim((string) ($episode->live_description ?: $episode->comentario_episodio ?: $episode->resena ?: ''))),
            'date' => optional($episode->fecha_emision)->toDateString() ?: now()->toDateString(),
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * @param array<string, string> $itemMetadata
     * @return array<string, string>
     */
    private function mapItemMetadataToHeaders(array $itemMetadata): array
    {
        $headers = [];

        foreach ($itemMetadata as $key => $value) {
            $sanitizedValue = $this->sanitizeHeaderValue($value);

            if ($sanitizedValue === '') {
                continue;
            }

            $headers['x-archive-meta-' . strtolower(trim($key))] = $sanitizedValue;
        }

        return $headers;
    }

    private function sanitizeHeaderValue(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;

        return trim($normalized);
    }

    private function uploadFile(string $identifier, string $remotePath, string $absolutePath, array $itemMetadata = []): void
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("No se pudo leer el MP3 local: {$absolutePath}");
        }

        $headers = [
            'Authorization' => 'LOW ' . trim((string) config('services.archive_org.access_key', '')) . ':' . trim((string) config('services.archive_org.secret_key', '')),
            'x-archive-auto-make-bucket' => '1',
            'x-archive-interactive-priority' => '1',
            // Evita la negociación "100-continue" de Guzzle en archivos grandes.
            'Expect' => '',
            'User-Agent' => config('app.name', 'Laravel') . ' ArchiveOrgPodcastService',
        ];

        if ($itemMetadata !== []) {
            $headers = array_merge($headers, $this->mapItemMetadataToHeaders($itemMetadata));
        }

        $this->retry(function () use ($identifier, $remotePath, $headers, $absolutePath): void {
            $stream = fopen($absolutePath, 'rb');
            if ($stream === false) {
                throw new RuntimeException("No se pudo abrir el archivo para lectura: {$absolutePath}");
            }

            try {
                $this->client->request('PUT', $this->s3Endpoint() . '/' . rawurlencode($identifier) . '/' . $this->encodePath($remotePath), [
                    'headers' => $headers,
                    'body' => $stream,
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        });
    }

    private function waitForRemoteFileOnArchiveOrg(string $identifier, string $remotePath): bool
    {
        $delays = [30, 60, 120];

        foreach ($delays as $delaySeconds) {
            sleep($delaySeconds);

            if ($this->verifyFileExistsOnArchiveOrg($identifier, $remotePath)) {
                return true;
            }

            Log::warning('ArchiveOrgPodcastService: file not yet reachable after upload, retrying.', [
                'identifier' => $identifier,
                'remote_path' => $remotePath,
                'wait_seconds' => $delaySeconds,
            ]);
        }

        return false;
    }

    private function verifyFileExistsOnArchiveOrg(string $identifier, string $remotePath): bool
    {
        try {
            $response = $this->client->request('HEAD', $this->downloadEndpoint() . '/' . rawurlencode($identifier) . '/' . $this->encodePath($remotePath), [
                'http_errors' => false,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, string> $fileMetadata
     */
    private function applyEpisodeMetadata(string $identifier, string $remotePath, array $fileMetadata): void
    {
        $patches = [];

        foreach ($fileMetadata as $key => $value) {
            if ($value === '') {
                continue;
            }

            $patches[] = [
                'op' => 'add',
                'path' => '/' . $key,
                'value' => $value,
            ];
        }

        if ($patches === []) {
            return;
        }

        try {
            $this->retry(function () use ($identifier, $remotePath, $patches): void {
                $this->client->request('POST', $this->apiEndpoint() . '/metadata/' . rawurlencode($identifier), [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        '-target' => 'files/' . $this->encodePath($remotePath),
                        '-patch' => json_encode($patches, JSON_THROW_ON_ERROR),
                        'access' => trim((string) config('services.archive_org.access_key', '')),
                        'secret' => trim((string) config('services.archive_org.secret_key', '')),
                    ],
                ]);
            });
        } catch (Throwable $exception) {
            Log::warning('ArchiveOrgPodcastService: metadata PATCH failed (non-fatal, ID3 tags carry essential metadata).', [
                'identifier' => $identifier,
                'remote_path' => $remotePath,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, string> $itemMetadata
     * @param array<string, string> $fileMetadata
     * @return array<string, mixed>
     */
    private function buildArchiveSnapshot(
        RadioProgram $episode,
        ?MasterProgram $master,
        string $identifier,
        string $remotePath,
        bool $itemExistsBefore,
        bool $created,
        array $itemMetadata,
        array $fileMetadata,
    ): array {
        return [
            'source' => 'archive-org-upload',
            'record_id' => $episode->id,
            'identifier' => $identifier,
            'remote_path' => $remotePath,
            'item_exists_before' => $itemExistsBefore,
            'created' => $created,
            'item_metadata' => $itemMetadata,
            'episode_metadata' => $fileMetadata,
            'status' => 'pending',
            'episode' => [
                'master_program_id' => $episode->master_program_id,
                'numero_episodio' => $episode->numero_episodio,
                'titulo_programa' => $episode->titulo_programa,
                'conductor' => $episode->conductor,
                'fecha_emision' => optional($episode->fecha_emision)->toDateString(),
                'biografia_invitado' => $episode->biografia_invitado,
                'resena' => $episode->resena,
                'live_title' => $episode->live_title,
                'live_description' => $episode->live_description,
                'comentario_episodio' => $episode->comentario_episodio,
                'sync_archive_org' => $episode->sync_archive_org,
                'master_program_name' => $master?->nombre,
            ],
        ];
    }

    private function apiEndpoint(): string
    {
        return 'https://archive.org';
    }

    private function downloadEndpoint(): string
    {
        return rtrim((string) config('services.archive_org.download_endpoint', 'https://archive.org/download'), '/');
    }

    private function s3Endpoint(): string
    {
        $endpoint = trim((string) config('services.archive_org.endpoint', 'https://s3.us.archive.org'));

        return rtrim($endpoint, '/');
    }

    private function encodePath(string $path): string
    {
        $segments = array_map('rawurlencode', array_filter(explode('/', str_replace('\\', '/', trim($path))), static fn (string $segment): bool => $segment !== ''));

        return implode('/', $segments);
    }

    private function retry(callable $callback, int $attempts = 3): void
    {
        $delayMs = [250, 500, 1000];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $callback();

                return;
            } catch (Throwable $exception) {
                if ($attempt >= $attempts) {
                    throw $exception;
                }

                usleep(($delayMs[$attempt - 1] ?? 250) * 1000);
            }
        }
    }
}
