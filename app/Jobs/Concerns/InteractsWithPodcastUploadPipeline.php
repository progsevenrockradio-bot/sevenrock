<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Jobs\SendDeliveryNotification;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\WriteTags as GetId3TagWriter;
use Throwable;

trait InteractsWithPodcastUploadPipeline
{
    private ?string $radiobossError = null;

    /**
     * @return array{id:?int,name:?string,email:?string}
     */
    private function resolveAuditActor(?int $initiatedByUserId, ?string $initiatedByName, ?string $initiatedByEmail): array
    {
        if ($initiatedByUserId !== null) {
            $user = User::query()->find($initiatedByUserId);
            if ($user instanceof User) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }
        }

        return [
            'id' => $initiatedByUserId,
            'name' => $initiatedByName,
            'email' => $initiatedByEmail,
        ];
    }

    private function dispatchDeliveryNotification(int $radioProgramId): void
    {
        SendDeliveryNotification::dispatch($radioProgramId);
    }

    private function resolveUploadFolder(?MasterProgram $master, RadioProgram $radioProgram): string
    {
        $folder = trim((string) ($radioProgram->ruta_ftp_radioboss ?: $master?->ruta_ftp), '/\\');

        return trim(str_replace(['..', '\\'], '', $folder), '/\\') ?: 'Programas';
    }

    private function syncEpisodeNumberBeforeProcessing(?MasterProgram $master, RadioProgram $radioProgram, string $folder): int
    {
        $currentEpisodeNumber = max(1, (int) ($radioProgram->numero_episodio ?? 0));
        $dbMaxEpisodeNumber = $this->resolveMaxEpisodeNumberFromDatabase($master, $radioProgram, $folder);
        $remoteMaxEpisodeNumber = filter_var(config('filesystems.disks.radioboss.scan_remote_for_episode_number', false), FILTER_VALIDATE_BOOL)
            ? $this->resolveMaxEpisodeNumberFromRemoteFolder($folder)
            : 0;

        if ($this->shouldPreserveManualEpisodeNumber($currentEpisodeNumber, $dbMaxEpisodeNumber)) {
            return $currentEpisodeNumber;
        }

        $resolvedEpisodeNumber = max(
            $currentEpisodeNumber,
            $dbMaxEpisodeNumber + 1,
            $remoteMaxEpisodeNumber + 1
        );

        if ($resolvedEpisodeNumber !== $currentEpisodeNumber) {
            $radioProgram->numero_episodio = $resolvedEpisodeNumber;
            RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                'numero_episodio' => $resolvedEpisodeNumber,
            ]));

            Log::info('Podcast upload: numero de episodio ajustado antes del procesamiento', [
                'program_id' => $radioProgram->id,
                'previous' => $currentEpisodeNumber,
                'resolved' => $resolvedEpisodeNumber,
                'db_max' => $dbMaxEpisodeNumber,
                'remote_max' => $remoteMaxEpisodeNumber,
                'folder' => $folder,
            ]);
        }

        return $resolvedEpisodeNumber;
    }

    private function shouldPreserveManualEpisodeNumber(int $currentEpisodeNumber, int $dbMaxEpisodeNumber): bool
    {
        return $currentEpisodeNumber > 0;
    }

    private function resolveMaxEpisodeNumberFromDatabase(?MasterProgram $master, RadioProgram $radioProgram, string $folder): int
    {
        $title = trim((string) ($master?->nombre ?: $radioProgram->titulo_programa));
        $normalizedTitle = Str::lower(Str::ascii($title));
        $masterProgramId = $master?->id ?: $radioProgram->master_program_id;

        return (int) RadioProgram::query()
            ->whereKeyNot($radioProgram->id)
            ->where(function ($query) use ($masterProgramId, $normalizedTitle, $folder): void {
                if ($masterProgramId) {
                    $query->where('master_program_id', $masterProgramId);
                }

                if ($normalizedTitle !== '') {
                    $query->orWhereRaw('LOWER(TRIM(titulo_programa)) = ?', [$normalizedTitle]);
                }

                if ($folder !== '') {
                    $query->orWhere('ruta_ftp_radioboss', $folder);
                }
            })
            ->max('numero_episodio');
    }

    private function resolveMaxEpisodeNumberFromRemoteFolder(string $folder): int
    {
        $radioBossService = app(\App\Services\RadioBossService::class);

        if (! $radioBossService->canSync()) {
            return 0;
        }

        try {
            $files = $radioBossService->files($folder);
        } catch (Throwable $exception) {
            Log::warning('Podcast upload: no se pudo leer la carpeta remota para calcular el siguiente episodio', [
                'folder' => $folder,
                'exception' => $exception->getMessage(),
            ]);

            return 0;
        }

        $maxEpisode = 0;
        foreach ($files as $file) {
            $maxEpisode = max($maxEpisode, $this->extractEpisodeNumberFromPath((string) $file));
        }

        return $maxEpisode;
    }

    private function extractEpisodeNumberFromPath(string $path): int
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        $fileName = basename($normalizedPath);

        if (preg_match('/\bEp\.\s*(\d{1,5})\b/i', $normalizedPath, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/^(\d{1,5})\.-/', $fileName, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
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

    private function escribirMetadata(string $ruta, ?MasterProgram $master, RadioProgram $radioProgram, string $programa, string $invitado, string $fecha, string $fechaTitulo, string $anio): void
    {
        if (! file_exists($ruta)) {
            Log::warning('Podcast upload: metadata tagging skipped because the file was not found.', [
                'file' => basename($ruta),
                'program' => $programa,
            ]);

            throw new \RuntimeException('Metadata tagging failed because the MP3 file was not found.');
        }

        try {
            $tags = $this->buildMetadataTags($master, $radioProgram, $programa, $invitado, $fecha, $fechaTitulo, $anio);

            $tagWriter = new GetId3TagWriter();
            $tagWriter->filename = $ruta;
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

            $verification = $this->verifyTaggedMetadata($ruta, $tags);
            if (! ($verification['verified'] ?? false)) {
                Log::warning('Podcast upload: metadata tagging verification mismatch; continuing processing.', [
                    'file' => basename($ruta),
                    'program' => $programa,
                    'message' => $verification['message'] ?? 'No se pudo verificar la metadata escrita en el MP3.',
                    'verification' => $verification,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Podcast upload: metadata tagging failed with exception.', [
                'file' => basename($ruta),
                'program' => $programa,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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
    private function verifyTaggedMetadata(string $ruta, array $expected): array
    {
        $getID3 = new GetID3();
        $getID3->setOption(['encoding' => 'UTF-8']);
        $analysis = $getID3->analyze($ruta);

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
            if (trim((string) ($expected[$key] ?? '')) !== '' && ! hash_equals(trim((string) $expected[$key]), $normalized[$key])) {
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

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function resolveAttachedPictureTagData(RadioProgram $radioProgram, ?MasterProgram $master): ?array
    {
        $candidates = array_filter([
            $radioProgram->imagen_episodio,
            $radioProgram->caratula_programa,
            $master?->caratula_url,
        ], fn ($value) => trim((string) $value) !== '');

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
            Log::warning('Podcast upload: no se pudo descargar la portada para incrustarla en el MP3', [
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

    private function uploadToRadioboss(string $folder, string $remotePath, string $localPath): void
    {
        $radioBossService = app(\App\Services\RadioBossService::class);
        $radioBossService->upload(
            $folder,
            $remotePath,
            Storage::disk('public')->path(ltrim($localPath, '/')),
            filter_var(config('filesystems.disks.radioboss.clear_before_upload', false), FILTER_VALIDATE_BOOL)
        );
    }

    /**
     * @return array{
     *     verified: bool,
     *     remote_path: string,
     *     local_path: string,
     *     local_size: int,
     *     remote_size: int|null,
     *     local_checksum_sha256: string|null,
     *     remote_checksum_sha256: string|null,
     *     message: string|null,
     * }
     */
    private function verifyRadiobossUpload(string $remotePath, string $localPath): array
    {
        $radioBossService = app(\App\Services\RadioBossService::class);
        $localAbsolutePath = Storage::disk('public')->path(ltrim($localPath, '/'));
        $localSize = $this->fileSizeInBytes($localAbsolutePath);
        $localChecksum = $this->checksumFile($localAbsolutePath);

        if (! $radioBossService->exists($remotePath)) {
            return [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'local_size' => $localSize,
                'remote_size' => null,
                'local_checksum_sha256' => $localChecksum,
                'remote_checksum_sha256' => null,
                'message' => 'El archivo no existe en RadioBOSS después de la subida.',
            ];
        }

        $binary = $radioBossService->read($remotePath);
        if (! is_string($binary) || $binary === '') {
            return [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'local_size' => $localSize,
                'remote_size' => null,
                'local_checksum_sha256' => $localChecksum,
                'remote_checksum_sha256' => null,
                'message' => 'No se pudo abrir el archivo remoto de RadioBOSS para verificarlo.',
            ];
        }

        $remoteSize = strlen($binary);
        $remoteChecksum = hash('sha256', $binary);

        if ($localSize > 0 && $remoteSize !== $localSize) {
            return [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'local_size' => $localSize,
                'remote_size' => $remoteSize,
                'local_checksum_sha256' => $localChecksum,
                'remote_checksum_sha256' => $remoteChecksum,
                'message' => 'El tamaño remoto no coincide con el archivo local en RadioBOSS.',
            ];
        }

        if ($localChecksum !== null && ! hash_equals($localChecksum, $remoteChecksum)) {
            return [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'local_size' => $localSize,
                'remote_size' => $remoteSize,
                'local_checksum_sha256' => $localChecksum,
                'remote_checksum_sha256' => $remoteChecksum,
                'message' => 'El checksum remoto no coincide con el archivo local en RadioBOSS.',
            ];
        }

        return [
            'verified' => true,
            'remote_path' => $remotePath,
            'local_path' => $localPath,
            'local_size' => $localSize,
            'remote_size' => $remoteSize,
            'local_checksum_sha256' => $localChecksum,
            'remote_checksum_sha256' => $remoteChecksum,
            'message' => null,
        ];
    }

    private function uploadToRadiobossWithRetries(string $folder, string $remotePath, string $localPath): bool
    {
        $attempts = 3;
        $delays = [2, 5, 10];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->uploadToRadioboss($folder, $remotePath, $localPath);
                $this->radiobossError = null;

                return true;
            } catch (Throwable $exception) {
                $this->radiobossError = $exception->getMessage();

                Log::warning('Podcast upload: RadioBOSS upload attempt failed', [
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'folder' => $folder,
                    'remote_path' => $remotePath,
                    'exception_class' => get_class($exception),
                    'exception' => $exception->getMessage(),
                ]);

                if ($attempt < $attempts) {
                    sleep($delays[$attempt - 1] ?? 1);
                }
            }
        }

        return false;
    }

    private function fileSizeInBytes(string $path): int
    {
        return is_file($path) ? (int) filesize($path) : 0;
    }

    private function checksumFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $checksum = @hash_file('sha256', $path);

        return is_string($checksum) && $checksum !== '' ? $checksum : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveNotificationRecipients(?MasterProgram $master): array
    {
        $candidates = [
            $master?->email_notificacion ?: $this->resolveGlobalNotificationPrimaryRecipient(),
            $master?->email_copia_notificacion ?: $this->resolveGlobalNotificationCopyRecipient(),
        ];

        $normalized = [];
        foreach ($candidates as $candidate) {
            foreach ($this->flattenNotificationCandidates($candidate) as $emailCandidate) {
                $email = filter_var($emailCandidate, FILTER_VALIDATE_EMAIL);
                if ($email !== false) {
                    $normalized[] = Str::lower($email);
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    private function resolveGlobalNotificationCopyRecipient(): ?string
    {
        $theme = ThemeSetting::current();
        $themeEmail = trim((string) ($theme->notification_copy_email ?: $theme->contact_email));

        return $themeEmail !== '' ? $themeEmail : null;
    }

    private function resolveGlobalNotificationPrimaryRecipient(): ?string
    {
        $theme = ThemeSetting::current();
        $themeEmail = trim((string) ($theme->notification_email ?: $theme->contact_email));

        return $themeEmail !== '' ? $themeEmail : 'prog.sevenrockradio@gmail.com';
    }

    private function resolveNotificationMailer(): string
    {
        $themeMailer = trim((string) ThemeSetting::current()->notification_mailer);
        if ($themeMailer !== '') {
            return $themeMailer;
        }

        $configuredMailer = trim((string) config('services.notifications.mailer', ''));
        if ($configuredMailer !== '') {
            return $configuredMailer;
        }

        $defaultMailer = (string) config('mail.default', 'log');
        $smtpHost = trim((string) config('mail.mailers.smtp.host', ''));
        $smtpUsername = trim((string) config('mail.mailers.smtp.username', ''));

        if ($smtpHost !== '' && $smtpUsername !== '') {
            return 'smtp';
        }

        return $defaultMailer;
    }

    /**
     * @return array<int, string>
     */
    private function flattenNotificationCandidates(mixed $candidate): array
    {
        if (is_array($candidate)) {
            $flattened = [];

            foreach ($candidate as $value) {
                foreach ($this->flattenNotificationCandidates($value) as $nestedValue) {
                    $flattened[] = $nestedValue;
                }
            }

            return $flattened;
        }

        $stringValue = trim((string) $candidate);
        if ($stringValue === '') {
            return [];
        }

        return preg_split('/\s*[,;]\s*/', $stringValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
