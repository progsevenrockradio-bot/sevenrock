<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProgramUploadedNotification;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use App\Models\ThemeSetting;
use App\Services\AuditTrailService;
use App\Services\ArchiveOrgPodcastService;
use App\Services\RadioBossService;
use App\Support\ExternalHttp;
use App\Support\PublicMediaUrl;
use JamesHeinrich\GetID3\GetID3;
use JamesHeinrich\GetID3\WriteTags as GetId3TagWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class UploadMp3Job implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    private ?string $radiobossError = null;

    public function __construct(
        public RadioProgram $radioProgram,
        public string $localPath,
        public bool $preserveLocalCopy = false,
        public ?int $initiatedByUserId = null,
        public ?string $initiatedByName = null,
        public ?string $initiatedByEmail = null,
    ) {
    }

    public function handle(ArchiveOrgPodcastService $archiveOrgPodcastService, AuditTrailService $auditTrailService, RadioBossService $radioBossService): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(600);

        $uploadOk = false;

        $actor = $this->resolveAuditActor();

        try {
            $auditTrailService->recordSystem('upload.started', 'Procesamiento del MP3 iniciado', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'master_program_id' => $this->radioProgram->master_program_id,
                'local_path' => $this->localPath,
                'preserve_local_copy' => $this->preserveLocalCopy,
            ]);

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'Procesando y etiquetando el MP3.',
            ]));

            $master = $this->radioProgram->masterProgram
                ?: MasterProgram::query()->find($this->radioProgram->master_program_id);

            $folder = $this->resolveUploadFolder($master);
            $episodeNumber = $this->syncEpisodeNumberBeforeProcessing($master, $folder, $radioBossService);
            $numeroEp = str_pad((string) $episodeNumber, 3, '0', STR_PAD_LEFT);
            $nombreProg = strtoupper(trim((string) $this->radioProgram->titulo_programa));
            $fecha = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d-m-Y') : date('d-m-Y');
            $fechaTitulo = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('d/m/Y') : date('d/m/Y');
            $anio = $this->radioProgram->fecha_emision ? $this->radioProgram->fecha_emision->format('Y') : date('Y');
            $invitado = trim(strip_tags((string) $this->radioProgram->biografia_invitado));

            $nuevoNombre = "{$numeroEp}.- {$nombreProg}" . ($invitado !== '' ? " ({$invitado})" : '') . " {$fecha}.mp3";
            $nuevoNombre = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $nuevoNombre);

            $nombreFtp = function_exists('iconv')
                ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nuevoNombre) ?: $nuevoNombre)
                : $nuevoNombre;
            $nombreFtp = preg_replace('/[^A-Za-z0-9\-\.\s#\(\)]/', '', (string) $nombreFtp) ?: $nuevoNombre;

            $nuevaRuta = $this->isProcessedLocalBackup($this->localPath)
                ? (string) $this->localPath
                : 'programas_procesados/' . $nombreFtp;

            if (! Storage::disk('public')->exists($this->localPath)) {
                Log::warning("UploadMp3Job: No se encontró el MP3 crudo: {$this->localPath}");

                return;
            }

            if (! $this->isProcessedLocalBackup($this->localPath)) {
                $copied = Storage::disk('public')->copy($this->localPath, $nuevaRuta);
                if (! $copied) {
                    throw new \RuntimeException("UploadMp3Job: No se pudo copiar el archivo de {$this->localPath} a {$nuevaRuta}");
                }

                Storage::disk('public')->delete($this->localPath);

                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['archivo_mp3' => $nuevaRuta]));
            } elseif ((string) $this->radioProgram->archivo_mp3 !== $nuevaRuta) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['archivo_mp3' => $nuevaRuta]));
            }

            $rutaAbsoluta = storage_path('app/public/' . $nuevaRuta);
            $this->escribirMetadata($rutaAbsoluta, $master, $nombreProg, $invitado, $fecha, $fechaTitulo, $anio);
            $fileName = basename($nuevaRuta);
            $auditTrailService->recordSystem('upload.metadata_verified', 'Metadata del MP3 verificada', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'file' => $fileName,
                'local_path' => $nuevaRuta,
            ]);

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'Metadata escrita. Enviando a RadioBOSS.',
            ]));

            $remotePath = $folder . '/' . $fileName;
            $ftpHost = trim((string) config('filesystems.disks.radioboss.host', ''));
            $archiveShouldSync = (bool) ($this->radioProgram->sync_archive_org ?? true);
            $archiveCanSync = $archiveShouldSync && $archiveOrgPodcastService->canSync();

            $radiobossVerification = [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $nuevaRuta,
                'local_size' => null,
                'remote_size' => null,
                'local_checksum_sha256' => null,
                'remote_checksum_sha256' => null,
                'message' => null,
            ];

            $archiveVerification = [
                'verified' => false,
                'identifier' => null,
                'remote_path' => null,
                'local_size' => null,
                'remote_size' => null,
                'message' => null,
            ];

            if ($ftpHost !== '') {
                $uploadOk = $this->uploadToRadiobossWithRetries($radioBossService, $folder, $remotePath, $nuevaRuta);

                if ($uploadOk) {
                    $radiobossVerification = $this->verifyRadiobossUpload($radioBossService, $remotePath, $nuevaRuta);
                    $uploadOk = (bool) ($radiobossVerification['verified'] ?? false);
                    $auditTrailService->recordSystem(
                        'upload.radioboss.' . ($uploadOk ? 'verified' : 'error'),
                        $uploadOk ? 'RadioBOSS verificado' : 'RadioBOSS no pudo verificarse',
                        [
                            'actor' => $actor,
                            'program_id' => $this->radioProgram->id,
                            'remote_path' => $remotePath,
                            'verification' => $radiobossVerification,
                        ],
                        $uploadOk ? 'info' : 'warning'
                    );
                }

                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'enviado_radioboss' => $uploadOk,
                    'radioboss_status' => $uploadOk ? 'verified' : 'error',
                    'radioboss_verified_at' => $uploadOk ? now() : null,
                    'radioboss_last_error' => $uploadOk ? null : (string) ($radiobossVerification['message'] ?? $this->radiobossError ?? 'No se pudo verificar la subida a RadioBOSS.'),
                    'radioboss_metadata' => array_merge((array) ($this->radioProgram->radioboss_metadata ?? []), [
                        'status' => $uploadOk ? 'verified' : 'error',
                        'verified_at' => $uploadOk ? now()->toIso8601String() : null,
                        'remote_path' => $remotePath,
                        'local_path' => $nuevaRuta,
                        'verification' => $radiobossVerification,
                    ]),
                ]));

                if (! $uploadOk) {
                    Log::warning('RadioBOSS upload failed after retries; keeping local backup.', [
                        'program_id' => $this->radioProgram->id,
                        'remote_path' => $remotePath,
                        'error' => $this->radiobossError,
                    ]);

                    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                        'status_message' => 'RadioBOSS no respondió correctamente.',
                    ]));
                }
            } else {
                Log::warning("RADIOBOSS_FTP_SERVER no está configurado. Se omite subida remota para {$fileName}.");
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'enviado_radioboss' => false,
                    'radioboss_status' => 'skipped',
                    'radioboss_verified_at' => null,
                    'radioboss_last_error' => null,
                    'radioboss_metadata' => array_merge((array) ($this->radioProgram->radioboss_metadata ?? []), [
                        'status' => 'skipped',
                        'remote_path' => $remotePath,
                        'local_path' => $nuevaRuta,
                    ]),
                ]));
            }

            $archiveUploadOk = false;
            if ($archiveCanSync) {
                try {
                    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                        'status_message' => 'RadioBOSS procesado. Sincronizando Archive.org.',
                    ]));

                    $archiveResult = $archiveOrgPodcastService->syncEpisode($this->radioProgram->fresh(['masterProgram']) ?? $this->radioProgram);
                    $archiveUploadOk = true;
                    $archiveVerification = (array) ($archiveResult['verification'] ?? []);
                    $auditTrailService->recordSystem('upload.archive.synced', 'Archive.org sincronizado y verificado', [
                        'actor' => $actor,
                        'program_id' => $this->radioProgram->id,
                        'identifier' => $archiveResult['identifier'] ?? null,
                        'remote_path' => $archiveResult['remote_path'] ?? null,
                        'verification' => $archiveVerification,
                    ]);
                    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                        'archive_org_status' => 'synced',
                        'archive_org_verified_at' => now(),
                        'archive_org_last_error' => null,
                        'archive_org_metadata' => array_merge((array) ($this->radioProgram->archive_org_metadata ?? []), [
                            'status' => 'synced',
                            'synced_at' => now()->toIso8601String(),
                            'verification' => $archiveVerification,
                        ]),
                    ]));
                } catch (Throwable $archiveError) {
                    Log::warning('UploadMp3Job: fallo la subida a Archive.org', [
                        'program_id' => $this->radioProgram->id,
                        'exception' => $archiveError->getMessage(),
                    ]);
                    $auditTrailService->recordSystem('upload.archive.error', 'Fallo la subida a Archive.org', [
                        'actor' => $actor,
                        'program_id' => $this->radioProgram->id,
                        'exception' => class_basename($archiveError),
                        'message' => $archiveError->getMessage(),
                    ], 'error');

                    RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                        'archive_org_status' => 'error',
                        'archive_org_verified_at' => null,
                        'archive_org_last_error' => $archiveError->getMessage(),
                        'archive_org_metadata' => array_merge((array) ($this->radioProgram->archive_org_metadata ?? []), [
                            'status' => 'error',
                            'last_error' => $archiveError->getMessage(),
                            'synced_at' => now()->toIso8601String(),
                        ]),
                        'status_message' => 'Archive.org falló al sincronizar.',
                    ]));
                }
            } elseif ($archiveShouldSync) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_verified_at' => null,
                    'archive_org_last_error' => null,
                    'archive_org_metadata' => array_merge((array) ($this->radioProgram->archive_org_metadata ?? []), [
                        'status' => 'skipped',
                        'last_error' => null,
                        'synced_at' => now()->toIso8601String(),
                    ]),
                ]));
                $archiveVerification = [
                    'verified' => false,
                    'message' => 'Faltan credenciales de Archive.org para verificar la entrega.',
                ];
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'status_message' => 'Archive.org omitido por credenciales incompletas.',
                ]));
            } else {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'archive_org_status' => 'skipped',
                    'archive_org_verified_at' => null,
                    'archive_org_last_error' => null,
                ]));
                $archiveVerification = [
                    'verified' => false,
                    'message' => 'La sincronización con Archive.org está desactivada.',
                ];
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                    'status_message' => 'Archive.org desactivado para este episodio.',
                ]));
            }

            $deliveryStatus = $archiveShouldSync
                ? ($archiveCanSync
                    ? ($uploadOk && $archiveUploadOk ? 'verified' : (($uploadOk || $archiveUploadOk) ? 'partial' : 'failed'))
                    : ($uploadOk ? 'partial' : 'failed'))
                : ($uploadOk ? 'verified' : 'failed');

            $deliveryError = $deliveryStatus === 'verified'
                ? null
                : trim(implode(' | ', array_filter([
                    (string) ($radiobossVerification['message'] ?? $this->radiobossError ?? ''),
                    (string) ($archiveVerification['message'] ?? ''),
                ])));

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'delivery_status' => $deliveryStatus,
                'delivery_verified_at' => $deliveryStatus === 'verified' ? now() : null,
                'delivery_last_error' => $deliveryError !== '' ? $deliveryError : null,
                'status_message' => $deliveryStatus === 'verified'
                    ? 'Procesamiento finalizado correctamente.'
                    : 'Procesamiento finalizado con incidencias.',
                'delivery_metadata' => [
                    'status' => $deliveryStatus,
                    'verified' => $deliveryStatus === 'verified',
                    'updated_at' => now()->toIso8601String(),
                    'radioboss' => $radiobossVerification,
                    'archive_org' => $archiveVerification,
                    'local_file' => [
                        'path' => $nuevaRuta,
                        'size' => $this->fileSizeInBytes($rutaAbsoluta),
                        'checksum_sha256' => $this->checksumFile($rutaAbsoluta),
                    ],
                ],
            ]));

            $emailDestinos = $this->resolveNotificationRecipients($master);
            if ($emailDestinos !== []) {
                try {
                    foreach ($emailDestinos as $emailDestino) {
                        $pendingMail = Mail::mailer($this->resolveNotificationMailer())
                            ->to($emailDestino)
                            ->from(
                                $this->resolveNotificationFromAddress() ?? (string) config('mail.from.address', 'hello@example.com'),
                                (string) config('mail.from.name', config('app.name', 'SevenRockRadio'))
                            );

                        $replyTo = $this->resolveNotificationReplyToAddress();
                        if ($replyTo !== null) {
                            $pendingMail->replyTo($replyTo, (string) config('app.name', 'SevenRockRadio'));
                        }

                        $pendingMail->send(new ProgramUploadedNotification(
                                fileName: $fileName,
                                uploadedToRadioboss: $uploadOk,
                                archiveVerified: $archiveUploadOk,
                                deliveryStatus: $deliveryStatus,
                                deliveryMetadata: (array) ($this->radioProgram->delivery_metadata ?? []),
                                failureReason: $deliveryError !== '' ? $deliveryError : null,
                            ));
                    }
                    $auditTrailService->recordSystem('upload.notification.sent', 'Correo de notificación enviado', [
                        'actor' => $actor,
                        'program_id' => $this->radioProgram->id,
                        'recipients' => $emailDestinos,
                        'mailer' => $this->resolveNotificationMailer(),
                        'delivery_status' => $deliveryStatus,
                    ]);
                } catch (Throwable $mailError) {
                    Log::error('UploadMp3Job: fallo enviando email de notificacion', [
                        'program_id' => $this->radioProgram->id,
                        'emails' => $emailDestinos,
                        'file' => $fileName,
                        'uploaded_to_radioboss' => $uploadOk,
                        'delivery_status' => $deliveryStatus,
                        'exception' => $mailError,
                    ]);
                }
            } else {
                Log::warning('UploadMp3Job: no se envio correo porque no hay un destinatario valido configurado', [
                    'program_id' => $this->radioProgram->id,
                    'master_program_id' => $master?->id,
                    'email_notificacion' => $master?->email_notificacion,
                    'email_copia_notificacion' => $master?->email_copia_notificacion,
                    'global_copy_email' => $this->resolveGlobalNotificationCopyRecipient(),
                    'file' => $fileName,
                ]);
                $auditTrailService->recordSystem('upload.notification.skipped', 'No había destinatarios válidos para el correo', [
                    'actor' => $actor,
                    'program_id' => $this->radioProgram->id,
                ], 'warning');
            }

            if ($deliveryStatus === 'verified' && ! $this->preserveLocalCopy) {
                Storage::disk('public')->delete($nuevaRuta);
            } else {
                Log::warning("Archivo NO eliminado (backup local preservado): {$nuevaRuta}");
            }

            $auditTrailService->recordSystem('upload.completed', 'Procesamiento del MP3 finalizado', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'delivery_status' => $deliveryStatus,
                'radioboss_status' => $this->radioProgram->radioboss_status,
                'archive_org_status' => $this->radioProgram->archive_org_status,
            ], $deliveryStatus === 'verified' ? 'info' : 'warning');
        } catch (Throwable $e) {
            if (! $uploadOk) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update(['enviado_radioboss' => false]));
            }

            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'status_message' => 'El procesamiento del episodio falló.',
            ]));

            $auditTrailService->recordSystem('upload.failed', 'Procesamiento del MP3 falló', [
                'actor' => $actor,
                'program_id' => $this->radioProgram->id,
                'message' => $e->getMessage(),
                'exception' => class_basename($e),
            ], 'error');

            Log::error('Error en UploadMp3Job', [
                'program_id' => $this->radioProgram->id,
                'localPath' => $this->localPath,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{id:?int,name:?string,email:?string}
     */
    private function resolveAuditActor(): array
    {
        if ($this->initiatedByUserId !== null) {
            $user = User::query()->find($this->initiatedByUserId);
            if ($user instanceof User) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }
        }

        return [
            'id' => $this->initiatedByUserId,
            'name' => $this->initiatedByName,
            'email' => $this->initiatedByEmail,
        ];
    }

    protected function uploadToRadioboss(RadioBossService $radioBossService, string $folder, string $remotePath, string $localPath): void
    {
        $radioBossService->upload(
            $folder,
            $remotePath,
            storage_path('app/public/' . ltrim($localPath, '/')),
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
    protected function verifyRadiobossUpload(RadioBossService $radioBossService, string $remotePath, string $localPath): array
    {
        $localAbsolutePath = storage_path('app/public/' . ltrim($localPath, '/'));
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

    protected function uploadToRadiobossWithRetries(RadioBossService $radioBossService, string $folder, string $remotePath, string $localPath): bool
    {
        $attempts = 3;
        $delays = [2, 5, 10];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->uploadToRadioboss($radioBossService, $folder, $remotePath, $localPath);
                $this->radiobossError = null;

                return true;
            } catch (Throwable $e) {
                $this->radiobossError = $e->getMessage();

                Log::warning('RadioBOSS upload attempt failed', [
                    'program_id' => $this->radioProgram->id,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'folder' => $folder,
                    'remote_path' => $remotePath,
                    'exception_class' => get_class($e),
                    'exception' => $e->getMessage(),
                ]);

                if ($attempt < $attempts) {
                    sleep($delays[$attempt - 1] ?? 1);
                }
            }
        }

        return false;
    }

    protected function escribirMetadata($ruta, ?MasterProgram $master, $programa, $invitado, $fecha, $fechaTitulo, $anio): void
    {
        if (! file_exists((string) $ruta)) {
            Log::warning('Metadata tagging skipped: file not found.', [
                'file' => basename((string) $ruta),
                'program' => (string) $programa,
            ]);

            throw new \RuntimeException('Metadata tagging failed because the MP3 file was not found.');
        }

        try {
            $tags = $this->buildMetadataTags($master, $programa, $invitado, $fecha, $fechaTitulo, $anio);

            $tagWriter = new GetId3TagWriter();
            $tagWriter->filename = (string) $ruta;
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

            $attachedPicture = $this->resolveAttachedPictureTagData($master);
            if ($attachedPicture !== null) {
                $tagWriter->tag_data['ATTACHED_PICTURE'] = [$attachedPicture];
            }

            if (! $tagWriter->WriteTags()) {
                throw new \RuntimeException(trim(implode(' | ', array_merge($tagWriter->errors, $tagWriter->warnings))) ?: 'getID3 no pudo escribir las etiquetas del MP3.');
            }

            $verification = $this->verifyTaggedMetadata($ruta, $tags);
            if (! ($verification['verified'] ?? false)) {
                Log::warning('Metadata tagging verification mismatch; continuing processing.', [
                    'file' => basename((string) $ruta),
                    'program' => (string) $programa,
                    'message' => $verification['message'] ?? 'No se pudo verificar la metadata escrita en el MP3.',
                    'verification' => $verification,
                ]);
            } else {
                Log::info('Metadata tagging verified.', [
                    'file' => basename((string) $ruta),
                    'program' => (string) $programa,
                    'verification' => $verification,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Metadata tagging failed with exception.', [
                'file' => basename((string) $ruta),
                'program' => (string) $programa,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
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
    private function buildMetadataTags(?MasterProgram $master, mixed $programa, mixed $invitado, mixed $fecha, mixed $fechaTitulo, mixed $anio): array
    {
        $episodeNumber = (int) ($this->radioProgram->numero_episodio ?? 0);
        $title = trim(sprintf('%s - Ep. %d (%s)', (string) $programa, $episodeNumber, (string) $fechaTitulo));
        $artist = trim((string) ($this->radioProgram->conductor ?? $master?->conductor ?? 'Seven Rock Radio'));
        $album = trim((string) $programa);
        $genre = trim((string) ($this->radioProgram->genero_musical ?? 'Rock'));
        $comment = collect(array_filter([
            'Emision: ' . (string) $fecha,
            $invitado !== '' ? 'Invitado: ' . $invitado : null,
        ]))->implode(' | ');

        return [
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'Seven Rock Radio',
            'album' => $album !== '' ? $album : (string) $programa,
            'year' => trim((string) $anio),
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

    private function resolveUploadFolder(?MasterProgram $master): string
    {
        $folder = trim((string) ($this->radioProgram->ruta_ftp_radioboss ?: $master?->ruta_ftp), '/\\');

        return trim(str_replace(['..', '\\'], '', $folder), '/\\') ?: 'Programas';
    }

    private function syncEpisodeNumberBeforeProcessing(?MasterProgram $master, string $folder, RadioBossService $radioBossService): int
    {
        $currentEpisodeNumber = max(1, (int) ($this->radioProgram->numero_episodio ?? 0));
        $dbMaxEpisodeNumber = $this->resolveMaxEpisodeNumberFromDatabase($master, $folder);
        $remoteMaxEpisodeNumber = filter_var(config('filesystems.disks.radioboss.scan_remote_for_episode_number', false), FILTER_VALIDATE_BOOL)
            ? $this->resolveMaxEpisodeNumberFromRemoteFolder($radioBossService, $folder)
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
            $this->radioProgram->numero_episodio = $resolvedEpisodeNumber;
            RadioProgram::withoutEvents(fn (): bool => (bool) $this->radioProgram->update([
                'numero_episodio' => $resolvedEpisodeNumber,
            ]));

            Log::info('UploadMp3Job: numero de episodio ajustado antes del procesamiento', [
                'program_id' => $this->radioProgram->id,
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

    private function resolveMaxEpisodeNumberFromDatabase(?MasterProgram $master, string $folder): int
    {
        $title = trim((string) ($master?->nombre ?: $this->radioProgram->titulo_programa));
        $normalizedTitle = Str::lower(Str::ascii($title));
        $masterProgramId = $master?->id ?: $this->radioProgram->master_program_id;

        return (int) RadioProgram::query()
            ->whereKeyNot($this->radioProgram->id)
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

    private function resolveMaxEpisodeNumberFromRemoteFolder(RadioBossService $radioBossService, string $folder): int
    {
        if (! $radioBossService->canSync()) {
            return 0;
        }

        try {
            $files = $radioBossService->files($folder);
        } catch (Throwable $exception) {
            Log::warning('UploadMp3Job: no se pudo leer la carpeta remota para calcular el siguiente episodio', [
                'program_id' => $this->radioProgram->id,
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
        $themeEmail = trim((string) (ThemeSetting::current()->notification_copy_email ?: ThemeSetting::current()->contact_email));

        return $themeEmail !== '' ? $themeEmail : null;
    }

    private function resolveGlobalNotificationPrimaryRecipient(): ?string
    {
        $themeEmail = trim((string) (ThemeSetting::current()->notification_email ?: ThemeSetting::current()->contact_email));

        return $themeEmail !== '' ? $themeEmail : null;
    }

    private function resolveNotificationFromAddress(): ?string
    {
        $themeEmail = trim((string) ThemeSetting::current()->notification_from_email);
        if ($themeEmail !== '') {
            return $themeEmail;
        }

        $mailFrom = trim((string) config('mail.from.address', ''));

        return $mailFrom !== '' ? $mailFrom : null;
    }

    private function resolveNotificationReplyToAddress(): ?string
    {
        $themeEmail = trim((string) ThemeSetting::current()->notification_reply_to_email);
        if ($themeEmail !== '') {
            return $themeEmail;
        }

        return $this->resolveGlobalNotificationPrimaryRecipient();
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

    /**
     * @return array{data:string,mime:string,description:string,picturetypeid:int}|null
     */
    private function resolveAttachedPictureTagData(?MasterProgram $master): ?array
    {
        $candidates = array_filter([
            $this->radioProgram->imagen_episodio,
            $this->radioProgram->caratula_programa,
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
            return $this->buildAttachedPicturePayloadFromFile(storage_path('app/public/' . $publicPath));
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
            Log::warning('UploadMp3Job: no se pudo descargar la portada para incrustarla en el MP3', [
                'program_id' => $this->radioProgram->id,
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
                return $this->buildAttachedPicturePayloadFromFile(storage_path('app/public/' . $storagePath));
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

        $mime = mime_content_type($absolutePath) ?: '';
        if (! str_starts_with(strtolower($mime), 'image/')) {
            return null;
        }

        return [
            'data' => $data,
            'mime' => $mime,
            'description' => 'Cover',
            'picturetypeid' => 3,
        ];
    }

    private function isProcessedLocalBackup(string $path): bool
    {
        return Str::startsWith(trim($path), 'programas_procesados/');
    }
}
