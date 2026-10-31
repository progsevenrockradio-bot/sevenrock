<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\PodcastProcessed;
use App\Events\PodcastUploadFailed;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use App\Services\PodcastPipelineAuditService;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\WriteTags as GetId3TagWriter;
use Throwable;

class ProcessMp3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 3;

    public const LOCAL_RETENTION_DAYS = 7;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public RadioProgram $radioProgram,
        public string $rawPath,
        public bool $preserveLocalCopy = false,
        public ?int $initiatedByUserId = null,
        public ?string $initiatedByName = null,
        public ?string $initiatedByEmail = null,
        public bool $dispatchNextJobs = true,
    ) {
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(600);

        $sourceAbsolutePath = null;
        $workingPath = null;

        try {
            $radioProgram = $this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram;
            $master = $radioProgram->masterProgram
                ?: MasterProgram::query()->find($radioProgram->master_program_id);

            $sourceDisk = (string) $radioProgram->archivo_mp3_disk;
            $fileUploadService = app(FileUploadService::class);
            $sourceAbsolutePath = $fileUploadService->localPath($this->rawPath, $sourceDisk);

            if ($sourceAbsolutePath === null || ! is_file($sourceAbsolutePath)) {
                throw new \RuntimeException("No se pudo leer el MP3 local: {$this->rawPath}");
            }

            $sourcePath = ltrim(str_replace('\\', '/', trim($this->rawPath)), '/');
            $workingPath = $this->buildWorkingPath($sourcePath);

            $this->prepareWorkingCopy($sourceAbsolutePath, $workingPath);

            RadioProgram::withoutEvents(function () use ($radioProgram): void {
                $radioProgram->forceFill([
                    'processing_started_at' => $radioProgram->processing_started_at ?? now(),
                    'status_message' => 'Procesando y etiquetando el MP3.',
                    'delivery_status' => 'delivery_pending',
                    'radioboss_status' => 'radioboss_pending',
                    'archive_org_status' => 'archive_pending',
                ])->saveQuietly();
            });

            app(PodcastPipelineAuditService::class)->record($radioProgram, 'PROCESSING_STARTED', 'El MP3 entró en la fase de procesamiento local.', [
                'raw_path' => $this->rawPath,
                'source_disk' => $sourceDisk,
                'dispatch_next_jobs' => $this->dispatchNextJobs,
            ]);

            $episodeNumber = max(1, (int) ($radioProgram->numero_episodio ?? 0));
            $programName = strtoupper(trim((string) ($radioProgram->titulo_programa ?: $master?->nombre ?: 'PODCAST')));
            $fecha = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('d-m-Y') : now()->format('d-m-Y');
            $fechaTitulo = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('d/m/Y') : now()->format('d/m/Y');
            $anio = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('Y') : now()->format('Y');
            $invitado = trim(strip_tags((string) $radioProgram->biografia_invitado));
            $durationSeconds = $this->extractDurationSeconds($workingPath);
            $storageFolder = $radioProgram->storageFolderSlug();
            $finalPath = $this->buildProcessedPath($radioProgram, $sourcePath);

            $this->writeMetadata($workingPath, $master, $radioProgram, $programName, $invitado, $fecha, $fechaTitulo, $anio);

            if (! $fileUploadService->isR2Configured()) {
                throw new \RuntimeException('Cloudflare R2 no está configurado como destino definitivo para podcasts.');
            }
            $targetDisk = 'r2';

            // 1. Subir y verificar el archivo definitivo procesado en Cloudflare R2 (sin crear copia local permanente)
            $this->publishWorkingCopy($workingPath, $finalPath, $targetDisk);

            $finishedAt = now();
            $processedSize = is_file($workingPath) ? (int) filesize($workingPath) : 0;
            $processedSha256 = is_file($workingPath) ? (hash_file('sha256', $workingPath) ?: null) : null;

            // 2. Persistir la referencia del definitivo en la base de datos ANTES de eliminar el original de podcast-inbox.
            // Si la actualización de base de datos falla, se lanza excepción antes de borrar el original para que sea recuperable.
            $existingMetadata = (array) ($radioProgram->delivery_metadata ?? []);
            $updatedMetadata = array_merge($existingMetadata, [
                'definitive_disk' => $targetDisk,
                'definitive_path' => $finalPath,
                'storage_folder' => $storageFolder,
                'r2_verified' => true,
                'r2_verified_at' => $finishedAt->toIso8601String(),
                'r2_size_bytes' => $processedSize,
                'r2_sha256' => $processedSha256,
                'preserve_local_copy' => false,
                'cover_embedded_in_mp3_outdated' => false,
                'raw_inbox_path' => $sourcePath !== $finalPath ? $sourcePath : ($existingMetadata['raw_inbox_path'] ?? null),
                'raw_inbox_disk' => $sourcePath !== $finalPath ? $sourceDisk : ($existingMetadata['raw_inbox_disk'] ?? null),
                'raw_inbox_cleaned' => false,
            ]);

            $persisted = $radioProgram->forceFill([
                'archivo_mp3' => $finalPath,
                'archivo_mp3_disk' => $targetDisk,
                'processing_finished_at' => $finishedAt,
                'status_message' => 'MP3 procesado y subido a almacenamiento definitivo (R2). Preparando entregas.',
                'delivery_metadata' => $updatedMetadata,
            ])->save();

            $freshCheck = RadioProgram::query()->whereKey($radioProgram->id)->value('archivo_mp3');
            if (! $persisted || trim((string) $freshCheck) !== $finalPath) {
                throw new \RuntimeException('No se pudo persistir la referencia del archivo definitivo en la base de datos; se conserva el original para reintento.');
            }

            // 3. Limpiar el original temporal de podcast-inbox SOLO después de haber guardado y verificado la referencia en BD
            $rawInboxCleaned = false;
            if ($finalPath !== $sourcePath) {
                $rawInboxCleaned = $this->cleanupSourceCopy($sourcePath, $finalPath, $sourceDisk);
                if ($rawInboxCleaned) {
                    $updatedMetadata['raw_inbox_cleaned'] = true;
                    RadioProgram::withoutEvents(function () use ($radioProgram, $updatedMetadata): void {
                        $radioProgram->forceFill([
                            'delivery_metadata' => $updatedMetadata,
                        ])->saveQuietly();
                    });
                }
            }

            if ($durationSeconds > 0) {
                RadioProgram::withoutEvents(function () use ($radioProgram, $durationSeconds): void {
                    $radioProgram->forceFill([
                        'duration_seconds' => $durationSeconds,
                    ])->saveQuietly();
                });

                if ($master) {
                    \App\Models\MasterProgramEmision::syncRealDurationForMaster(
                        $master,
                        $radioProgram->fecha_emision ? $radioProgram->fecha_emision->toDateString() : null,
                        $durationSeconds
                    );
                }
            }

            app(PodcastPipelineAuditService::class)->record($radioProgram, 'PROCESSING_COMPLETED', 'El MP3 quedó listo para distribución.', [
                'processed_path' => $finalPath,
                'processed_disk' => $targetDisk,
                'r2_sha256' => $processedSha256,
                'duration_seconds' => $durationSeconds,
                'size_bytes' => $processedSize,
            ]);

            if ($this->dispatchNextJobs) {
                PodcastProcessed::dispatch($radioProgram->id);
            }
        } finally {
            if (is_string($workingPath) && $workingPath !== '') {
                $this->cleanupWorkingCopy($workingPath);
            }

            if (is_string($sourceAbsolutePath) && $sourceAbsolutePath !== '' && $this->isTemporaryDownloadPath($sourceAbsolutePath)) {
                $this->cleanupWorkingCopy($sourceAbsolutePath);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $radioProgram = $this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram;
        if (! $radioProgram instanceof RadioProgram) {
            return;
        }

        RadioProgram::withoutEvents(function () use ($radioProgram, $exception): void {
            $radioProgram->forceFill([
                'processing_finished_at' => now(),
                'delivery_status' => 'delivery_failed',
                'delivery_last_error' => $exception->getMessage(),
                'status_message' => 'El procesamiento local falló.',
            ])->saveQuietly();
        });

        app(PodcastPipelineAuditService::class)->record($radioProgram, 'ERROR', 'Falló el procesamiento local del MP3.', [
            'stage' => 'processing_local',
            'exception' => $exception->getMessage(),
        ]);

        PodcastUploadFailed::dispatch($radioProgram->id, 'processing_local', $exception->getMessage(), [
            'exception_class' => $exception::class,
        ]);
    }

    private function buildProcessedPath(
        RadioProgram $radioProgram,
        string $sourcePath,
    ): string
    {
        $sourcePath = ltrim(str_replace('\\', '/', trim($sourcePath)), '/');
        if ($sourcePath === '') {
            return $radioProgram->canonicalProcessedPath();
        }

        if (RadioProgram::isProcessedStoragePath($sourcePath)) {
            return $sourcePath;
        }

        return $radioProgram->canonicalProcessedPath();
    }

    private function extractDurationSeconds(string $absolutePath): int
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return 0;
        }

        try {
            $getID3 = new GetID3();
            $getID3->setOption(['encoding' => 'UTF-8']);
            $analysis = $getID3->analyze($absolutePath);
        } catch (Throwable) {
            return 0;
        }

        if (! is_array($analysis)) {
            return 0;
        }

        $playtime = data_get($analysis, 'playtime_seconds');
        if (! is_numeric($playtime)) {
            return 0;
        }

        return max(0, (int) round((float) $playtime));
    }

    private function buildWorkingPath(string $sourcePath): string
    {
        $sourcePath = ltrim(str_replace('\\', '/', trim($sourcePath)), '/');
        $fileName = basename($sourcePath);
        $workingDirectory = storage_path('app/tmp/podcast-processing');

        File::ensureDirectoryExists($workingDirectory);

        return $workingDirectory . DIRECTORY_SEPARATOR . Str::uuid()->toString() . '-' . ($fileName !== '' ? $fileName : 'episode.mp3');
    }

    private function prepareWorkingCopy(string $sourceAbsolutePath, string $workingPath): void
    {
        if (! is_file($sourceAbsolutePath) || ! is_readable($sourceAbsolutePath)) {
            throw new \RuntimeException("No se pudo preparar el archivo de trabajo desde {$sourceAbsolutePath}");
        }

        if (! copy($sourceAbsolutePath, $workingPath)) {
            throw new \RuntimeException("No se pudo crear la copia de trabajo en {$workingPath}");
        }
    }

    private function publishWorkingCopy(string $workingPath, string $finalPath, string $disk = 'r2'): void
    {
        $stream = @fopen($workingPath, 'rb');
        if (! is_resource($stream)) {
            throw new \RuntimeException("No se pudo abrir la copia de trabajo para streaming: {$workingPath}");
        }

        try {
            if (! Storage::disk($disk)->writeStream($finalPath, $stream)) {
                rewind($stream);
                $copyContents = stream_get_contents($stream);
                if (! Storage::disk($disk)->put($finalPath, (string) $copyContents)) {
                    throw new \RuntimeException("No se pudo guardar el archivo procesado en {$disk}: {$finalPath}");
                }
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $localSize = (int) filesize($workingPath);
        $remoteSize = (int) (Storage::disk($disk)->size($finalPath) ?? 0);
        if ($localSize <= 0 || $remoteSize !== $localSize) {
            throw new \RuntimeException("La verificación de integridad falló para {$disk}: {$finalPath} (esperado {$localSize} bytes, subido {$remoteSize} bytes)");
        }
    }

    private function cleanupSourceCopy(string $sourcePath, string $finalPath, string $sourceDisk): bool
    {
        $sourcePath = ltrim(str_replace('\\', '/', trim($sourcePath)), '/');
        $finalPath = ltrim(str_replace('\\', '/', trim($finalPath)), '/');

        if ($sourcePath === '' || $sourcePath === $finalPath) {
            return false;
        }

        $targetDisk = trim($sourceDisk) !== '' ? trim($sourceDisk) : 'public';
        if ($targetDisk !== 'public' && ! str_starts_with($sourcePath, 'podcast-inbox/')) {
            return false;
        }

        $deleted = false;

        foreach (array_values(array_unique([$targetDisk, 'public'])) as $candidateDisk) {
            if ($candidateDisk !== 'public' && ! str_starts_with($sourcePath, 'podcast-inbox/')) {
                continue;
            }

            try {
                if (Storage::disk($candidateDisk)->exists($sourcePath)) {
                    $deleted = Storage::disk($candidateDisk)->delete($sourcePath) || $deleted;
                }
            } catch (Throwable $exception) {
                Log::warning('ProcessMp3Job: no se pudo eliminar el archivo original tras verificar R2.', [
                    'source_path' => $sourcePath,
                    'source_disk' => $candidateDisk,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $deleted;
    }

    private function isTemporaryDownloadPath(string $absolutePath): bool
    {
        return str_starts_with($absolutePath, storage_path('app/tmp/r2'))
            || str_starts_with($absolutePath, storage_path('app/tmp/backblaze'))
            || str_starts_with($absolutePath, storage_path('app/tmp/podcast-processing'));
    }

    private function cleanupWorkingCopy(string $workingPath): void
    {
        if (is_file($workingPath)) {
            @unlink($workingPath);
        }
    }

    private function writeMetadata(
        string $absolutePath,
        ?MasterProgram $master,
        RadioProgram $radioProgram,
        string $programName,
        string $invitado,
        string $fecha,
        string $fechaTitulo,
        string $anio,
    ): void {
        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('Metadata tagging failed because the MP3 file was not found.');
        }

        $tags = $this->buildMetadataTags($master, $radioProgram, $programName, $invitado, $fecha, $fechaTitulo, $anio);

        $tagWriter = new GetId3TagWriter();
        $tagWriter->filename = $absolutePath;
        $tagWriter->tagformats = ['id3v2.3', 'id3v1'];
        $tagWriter->overwrite_tags = true;
        $tagWriter->remove_other_tags = true;
        $tagWriter->tag_encoding = 'UTF-8';
        $tagWriter->tag_data = [
            'TITLE' => [$tags['title']],
            'ARTIST' => [$tags['artist']],
            'ALBUM' => [$tags['album']],
            'YEAR' => [$tags['year']],
            'GENRE' => [$tags['genre']],
            'COMMENT' => [$tags['comment']],
            'TRACKNUMBER' => [$tags['tracknumber']],
        ];

        $txxxFrames = array_values(array_filter([
            $tags['subject'] !== '' ? ['description' => 'subject', 'data' => $tags['subject']] : null,
            $tags['description'] !== '' ? ['description' => 'description', 'data' => $tags['description']] : null,
            $tags['date'] !== '' ? ['description' => 'date', 'data' => $tags['date']] : null,
        ]));
        if ($txxxFrames !== []) {
            $tagWriter->tag_data['TXXX'] = $txxxFrames;
        }

        $attachedPicture = $this->resolveAttachedPictureTagData($radioProgram, $master);
        if ($attachedPicture !== null) {
            $tagWriter->tag_data['ATTACHED_PICTURE'] = [$attachedPicture];
        }

        if (! $tagWriter->WriteTags()) {
            if ($txxxFrames !== []) {
                Log::warning('ProcessMp3Job: TXXX frames failed, retrying without extended fields.', [
                    'program_id' => $radioProgram->id,
                    'errors' => $tagWriter->errors,
                    'warnings' => $tagWriter->warnings,
                ]);

                $tagWriter = new GetId3TagWriter();
                $tagWriter->filename = $absolutePath;
                $tagWriter->tagformats = ['id3v2.3', 'id3v1'];
                $tagWriter->overwrite_tags = true;
                $tagWriter->remove_other_tags = true;
                $tagWriter->tag_encoding = 'UTF-8';
                $tagWriter->tag_data = [
                    'TITLE' => [$tags['title']],
                    'ARTIST' => [$tags['artist']],
                    'ALBUM' => [$tags['album']],
                    'YEAR' => [$tags['year']],
                    'GENRE' => [$tags['genre']],
                    'COMMENT' => [$tags['comment']],
                    'TRACKNUMBER' => [$tags['tracknumber']],
                ];

                if ($attachedPicture !== null) {
                    $tagWriter->tag_data['ATTACHED_PICTURE'] = [$attachedPicture];
                }

                if (! $tagWriter->WriteTags()) {
                    throw new \RuntimeException(trim(implode(' | ', array_merge($tagWriter->errors, $tagWriter->warnings))) ?: 'getID3 no pudo escribir las etiquetas del MP3.');
                }
            } else {
                throw new \RuntimeException(trim(implode(' | ', array_merge($tagWriter->errors, $tagWriter->warnings))) ?: 'getID3 no pudo escribir las etiquetas del MP3.');
            }
        }

        $verification = $this->verifyTaggedMetadata($absolutePath, $tags);
        if (! ($verification['verified'] ?? false)) {
            Log::error('ProcessMp3Job: metadata verification mismatch.', [
                'program_id' => $radioProgram->id,
                'message' => $verification['message'] ?? 'La metadata no pudo verificarse.',
                'expected' => $tags,
                'verified_tags' => $verification['tags'] ?? [],
            ]);

            throw new \RuntimeException((string) ($verification['message'] ?? 'La metadata del MP3 no pudo verificarse después de escribirla.'));
        }
    }

    /**
     * @return array{
     *     title: string,
     *     artist: string,
     *     album: string,
     *     year: string,
     *     genre: string,
     *     comment: string,
     *     tracknumber: string,
     *     subject: string,
     *     description: string,
     *     date: string
     * }
     */
    private function buildMetadataTags(?MasterProgram $master, RadioProgram $radioProgram, string $programa, string $invitado, string $fecha, string $fechaTitulo, string $anio): array
    {
        $episodeNumber = (int) ($radioProgram->numero_episodio ?? 0);
        $title = trim(sprintf('%s - Ep. %d (%s)', $programa, $episodeNumber, $fechaTitulo));
        $artist = trim((string) ($radioProgram->conductor ?? $master?->conductor ?? 'Seven Rock Radio'));
        $album = trim($programa);
        $genre = trim((string) ($radioProgram->genero_musical ?? 'Rock'));
        $subject = collect(array_filter([
            $programa !== '' ? $programa : null,
            'episode ' . $episodeNumber,
            trim((string) ($radioProgram->genero_musical ?? $master?->genero ?? '')),
        ]))->implode(', ');
        $description = trim(strip_tags((string) ($radioProgram->live_description ?? $radioProgram->comentario_episodio ?? $radioProgram->resena ?? '')));
        $dateForComment = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('Y-m-d') : now()->format('Y-m-d');
        $comment = collect(array_filter([
            'Emision: ' . $fecha,
            $invitado !== '' ? 'Invitado: ' . $invitado : null,
            $subject !== '' ? 'Tematica: ' . $subject : null,
            $description !== '' ? 'Descripcion: ' . $description : null,
            'Fecha: ' . $dateForComment,
        ]))->implode(' | ');

        return [
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'Seven Rock Radio',
            'album' => $album !== '' ? $album : $programa,
            'year' => trim($anio),
            'genre' => $genre !== '' ? $genre : 'Rock',
            'comment' => $comment,
            'tracknumber' => (string) $episodeNumber,
            'subject' => $subject,
            'description' => $description,
            'date' => $dateForComment,
        ];
    }

    /**
     * @param array{
     *     title: string,
     *     artist: string,
     *     album: string,
     *     year: string,
     *     genre: string,
     *     comment: string,
     *     tracknumber: string,
     *     subject: string,
     *     description: string,
     *     date: string
     * } $expected
     * @return array{verified: bool, message: string|null, tags: array<string, string>}
     */
    private function verifyTaggedMetadata(string $absolutePath, array $expected): array
    {
        $getID3 = new GetID3();
        $getID3->setOption(['encoding' => 'UTF-8']);
        $analysis = $getID3->analyze($absolutePath);

        if (! is_array($analysis)) {
            return [
                'verified' => false,
                'message' => 'getID3 no pudo leer la metadata del archivo.',
                'tags' => [],
            ];
        }

        $tags = data_get($analysis, 'tags_html.id3v2', []);
        if (! is_array($tags) || $tags === []) {
            $tags = data_get($analysis, 'tags.id3v2', []);
        }

        $normalized = [
            'title' => trim((string) ($tags['title'][0] ?? '')),
            'artist' => trim((string) ($tags['artist'][0] ?? '')),
            'album' => trim((string) ($tags['album'][0] ?? '')),
            'year' => trim((string) ($tags['year'][0] ?? $tags['date'][0] ?? '')),
            'genre' => trim((string) ($tags['genre'][0] ?? '')),
            'comment' => trim((string) ($tags['comment'][0] ?? '')),
            'tracknumber' => trim((string) ($tags['track'][0] ?? $tags['tracknumber'][0] ?? $tags['track_number'][0] ?? '')),
        ];

        foreach (['title', 'artist', 'album'] as $key) {
            $normalizedExpected = $this->normalizeTaggedMetadataValue((string) ($expected[$key] ?? ''));
            $normalizedActual = $this->normalizeTaggedMetadataValue((string) ($normalized[$key] ?? ''));

            if ($normalizedExpected !== '' && ! hash_equals($normalizedExpected, $normalizedActual)) {
                return [
                    'verified' => false,
                    'message' => sprintf('La metadata de %s no coincide tras escribirla.', $key),
                    'tags' => $normalized,
                ];
            }
        }

        return [
            'verified' => true,
            'message' => null,
            'tags' => $normalized,
        ];
    }

    private function normalizeTaggedMetadataValue(string $value): string
    {
        $value = trim($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return trim($value);
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function resolveAttachedPictureTagData(RadioProgram $radioProgram, ?MasterProgram $master): ?array
    {
        $candidates = array_filter([
            $radioProgram->imagen_episodio,
            $radioProgram->caratula_programa,
            $master?->caratula_url,
        ], static fn ($value) => trim((string) $value) !== '');

        foreach ($candidates as $candidate) {
            $picture = $this->loadAttachedPictureFromCandidate((string) $candidate);
            if ($picture !== null) {
                return $picture;
            }
        }

        return null;
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function loadAttachedPictureFromCandidate(string $candidate): ?array
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $publicPath = ltrim(str_replace('\\', '/', $candidate), '/');
        if (Storage::disk('public')->exists($publicPath)) {
            return $this->buildAttachedPicturePayloadFromFile(Storage::disk('public')->path($publicPath));
        }

        $normalizedUrl = PublicMediaUrl::normalizePublicUrl($candidate);
        if ($normalizedUrl === '') {
            return null;
        }

        $localPayload = $this->buildAttachedPicturePayloadFromNormalizedUrl($normalizedUrl);
        if ($localPayload !== null) {
            return $localPayload;
        }

        $remoteUrl = str_starts_with($normalizedUrl, '//') ? 'https:' . $normalizedUrl : $normalizedUrl;

        try {
            $response = ExternalHttp::client()->timeout(15)->get($remoteUrl);
            if (! $response->successful()) {
                return null;
            }

            $mime = trim(strtolower((string) $response->header('Content-Type', '')));
            if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                return null;
            }

            $data = $response->body();
            if ($data === '') {
                return null;
            }

            return [
                'data' => $data,
                'mime' => strtok($mime, ';') ?: $mime,
                'description' => 'Cover',
                'picturetypeid' => 3,
            ];
        } catch (Throwable $exception) {
            Log::warning('ProcessMp3Job: no se pudo descargar la portada para incrustarla en el MP3', [
                'candidate' => $candidate,
                'url' => $remoteUrl,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function buildAttachedPicturePayloadFromNormalizedUrl(string $normalizedUrl): ?array
    {
        $appHost = (string) parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $urlHost = (string) parse_url($normalizedUrl, PHP_URL_HOST);
        $urlPath = ltrim((string) parse_url($normalizedUrl, PHP_URL_PATH), '/');

        if ($urlPath === '') {
            return null;
        }

        if ($appHost !== '' && $urlHost !== '' && ! hash_equals($appHost, $urlHost)) {
            return null;
        }

        if (str_starts_with($urlPath, 'storage/')) {
            $storagePath = substr($urlPath, 8);
            if ($storagePath !== false && Storage::disk('public')->exists($storagePath)) {
                return $this->buildAttachedPicturePayloadFromFile(Storage::disk('public')->path($storagePath));
            }
        }

        $absolutePublicPath = public_path($urlPath);
        if (is_file($absolutePublicPath)) {
            return $this->buildAttachedPicturePayloadFromFile($absolutePublicPath);
        }

        return null;
    }

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function buildAttachedPicturePayloadFromFile(string $absolutePath): ?array
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = (string) (mime_content_type($absolutePath) ?: 'image/jpeg');

        return [
            'data' => $data,
            'mime' => $mime,
            'description' => 'Cover',
            'picturetypeid' => 3,
        ];
    }
}
