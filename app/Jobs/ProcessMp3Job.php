<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\WriteTags as GetId3TagWriter;
use Throwable;

class ProcessMp3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

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

        $radioProgram = $this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram;
        $master = $radioProgram->masterProgram
            ?: MasterProgram::query()->find($radioProgram->master_program_id);

        $sourceDisk = (string) $radioProgram->archivo_mp3_disk;
        $fileUploadService = app(FileUploadService::class);
        $sourceAbsolutePath = $fileUploadService->localPath($this->rawPath, $sourceDisk);

        if ($sourceAbsolutePath === null || ! is_file($sourceAbsolutePath)) {
            throw new \RuntimeException("No se pudo leer el MP3 local: {$this->rawPath}");
        }

        $sourcePath = $this->rawPath;
        $finalPath = $this->buildProcessedPath($sourcePath);
        $rawExistsOnPublicDisk = $sourceDisk === 'public' && Storage::disk('public')->exists($sourcePath);

        RadioProgram::withoutEvents(function () use ($radioProgram): void {
            $radioProgram->forceFill([
                'status_message' => 'Procesando y etiquetando el MP3.',
            ])->saveQuietly();
        });

        $episodeNumber = max(1, (int) ($radioProgram->numero_episodio ?? 0));
        $programName = strtoupper(trim((string) ($radioProgram->titulo_programa ?: $master?->nombre ?: 'PODCAST')));
        $fecha = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('d-m-Y') : now()->format('d-m-Y');
        $fechaTitulo = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('d/m/Y') : now()->format('d/m/Y');
        $anio = $radioProgram->fecha_emision ? $radioProgram->fecha_emision->format('Y') : now()->format('Y');
        $invitado = trim(strip_tags((string) $radioProgram->biografia_invitado));

        $this->writeMetadata($sourceAbsolutePath, $master, $radioProgram, $programName, $invitado, $fecha, $fechaTitulo, $anio);

        if ($finalPath !== $sourcePath) {
            if ($rawExistsOnPublicDisk) {
                if (! Storage::disk('public')->copy($sourcePath, $finalPath)) {
                    throw new \RuntimeException("No se pudo copiar el archivo procesado a {$finalPath}");
                }
            } else {
                $copyContents = @file_get_contents($sourceAbsolutePath);
                if (! is_string($copyContents) || $copyContents === '') {
                    throw new \RuntimeException("No se pudo preparar el contenido procesado para {$finalPath}");
                }

                if (! Storage::disk('public')->put($finalPath, $copyContents)) {
                    throw new \RuntimeException("No se pudo guardar el archivo procesado en {$finalPath}");
                }
            }
        }

        if ($finalPath !== $sourcePath && $rawExistsOnPublicDisk && Storage::disk('public')->exists($sourcePath)) {
            Storage::disk('public')->delete($sourcePath);
        } elseif ($finalPath !== $sourcePath && $sourceDisk !== 'public' && is_file($sourceAbsolutePath)) {
            @unlink($sourceAbsolutePath);
        }

        RadioProgram::withoutEvents(function () use ($radioProgram, $finalPath): void {
            $radioProgram->forceFill([
                'archivo_mp3' => $finalPath,
                'archivo_mp3_disk' => 'public',
                'status_message' => 'MP3 procesado localmente. Preparando entregas.',
            ])->saveQuietly();
        });

        if ($this->dispatchNextJobs) {
            Bus::chain([
                new UploadRadiobossJob($radioProgram->id),
                new UploadArchiveOrgJob($radioProgram->id),
                new NotifyPodcastReadyJob($radioProgram->id),
            ])->dispatch();
        }
    }

    private function buildProcessedPath(string $sourcePath): string
    {
        $sourcePath = ltrim(str_replace('\\', '/', trim($sourcePath)), '/');
        if ($sourcePath === '') {
            return 'programas_procesados/episode.mp3';
        }

        if (str_starts_with($sourcePath, 'programas_procesados/')) {
            return $sourcePath;
        }

        return 'programas_procesados/' . basename($sourcePath);
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

        $attachedPicture = $this->resolveAttachedPictureTagData($radioProgram, $master);
        if ($attachedPicture !== null) {
            $tagWriter->tag_data['ATTACHED_PICTURE'] = [$attachedPicture];
        }

        if (! $tagWriter->WriteTags()) {
            throw new \RuntimeException(trim(implode(' | ', array_merge($tagWriter->errors, $tagWriter->warnings))) ?: 'getID3 no pudo escribir las etiquetas del MP3.');
        }

        $verification = $this->verifyTaggedMetadata($absolutePath, $tags);
        if (! ($verification['verified'] ?? false)) {
            Log::warning('ProcessMp3Job: metadata verification mismatch.', [
                'program_id' => $radioProgram->id,
                'message' => $verification['message'] ?? 'La metadata no pudo verificarse.',
            ]);
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
     *     tracknumber: string
     * }
     */
    private function buildMetadataTags(?MasterProgram $master, RadioProgram $radioProgram, string $programa, string $invitado, string $fecha, string $fechaTitulo, string $anio): array
    {
        $episodeNumber = (int) ($radioProgram->numero_episodio ?? 0);
        $title = trim(sprintf('%s - Ep. %d (%s)', $programa, $episodeNumber, $fechaTitulo));
        $artist = trim((string) ($radioProgram->conductor ?? $master?->conductor ?? 'Seven Rock Radio'));
        $album = trim($programa);
        $genre = trim((string) ($radioProgram->genero_musical ?? 'Rock'));
        $comment = collect(array_filter([
            'Emision: ' . $fecha,
            $invitado !== '' ? 'Invitado: ' . $invitado : null,
        ]))->implode(' | ');

        return [
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'Seven Rock Radio',
            'album' => $album !== '' ? $album : $programa,
            'year' => trim($anio),
            'genre' => $genre !== '' ? $genre : 'Rock',
            'comment' => $comment,
            'tracknumber' => (string) $episodeNumber,
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
     *     tracknumber: string
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
