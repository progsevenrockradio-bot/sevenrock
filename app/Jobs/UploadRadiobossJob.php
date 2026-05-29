<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Models\RadioProgram;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadRadiobossJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithPodcastUploadPipeline;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 10];

    public function __construct(
        public int $radioProgramId,
        public string $localPath,
        public string $remoteFolder,
    ) {
    }

    public function handle(): void
    {
        $radioProgram = RadioProgram::query()->with('masterProgram')->findOrFail($this->radioProgramId);
        $remotePath = trim($this->remoteFolder, '/\\') . '/' . basename(str_replace('\\', '/', $this->localPath));
        $remotePath = trim(str_replace(['//', '\\'], ['/', '/'], $remotePath), '/');
        $fileUploadService = app(FileUploadService::class);
        $absolutePath = $fileUploadService->localPath($this->localPath, (string) $radioProgram->archivo_mp3_disk);

        try {
            if (! is_string($absolutePath) || $absolutePath === '' || ! is_file($absolutePath)) {
                throw new \RuntimeException("No se pudo leer el MP3 local: {$this->localPath}");
            }

            if (! app(\App\Services\RadioBossService::class)->canSync()) {
                RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                    'enviado_radioboss' => false,
                    'radioboss_status' => 'skipped',
                    'radioboss_verified_at' => null,
                    'radioboss_last_error' => null,
                    'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                        'status' => 'skipped',
                        'remote_path' => $remotePath,
                        'local_path' => $this->localPath,
                        'reason' => 'RadioBOSS no está configurado.',
                    ]),
                ]));

                $this->dispatchDeliveryNotification($radioProgram->id);

                if (str_starts_with($absolutePath, storage_path('app/tmp/backblaze')) && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }

                return;
            }

            $uploadOk = $this->uploadToRadiobossWithRetries($this->remoteFolder, $remotePath, $absolutePath, $this->localPath);

            $radiobossVerification = [
                'verified' => false,
                'remote_path' => $remotePath,
                'local_path' => $this->localPath,
                'local_size' => null,
                'remote_size' => null,
                'local_checksum_sha256' => null,
                'remote_checksum_sha256' => null,
                'message' => null,
            ];

            if ($uploadOk) {
                $radiobossVerification = $this->verifyRadiobossUpload($remotePath, $absolutePath, $this->localPath);
                $uploadOk = (bool) ($radiobossVerification['verified'] ?? false);

                if (! $uploadOk && $this->radiobossError === null) {
                    $this->radiobossError = (string) ($radiobossVerification['message'] ?? 'No se pudo verificar la subida a RadioBOSS.');
                }
            }

            RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                'enviado_radioboss' => $uploadOk,
                'radioboss_status' => $uploadOk ? 'verified' : 'error',
                'radioboss_verified_at' => $uploadOk ? now() : null,
                'radioboss_last_error' => $uploadOk ? null : (string) ($radiobossVerification['message'] ?? $this->radiobossError ?? 'No se pudo verificar la subida a RadioBOSS.'),
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => $uploadOk ? 'verified' : 'error',
                    'verified_at' => $uploadOk ? now()->toIso8601String() : null,
                    'remote_path' => $remotePath,
                    'local_path' => $this->localPath,
                    'verification' => $radiobossVerification,
                ]),
                'status_message' => $uploadOk
                    ? 'RadioBOSS verificado.'
                    : 'RadioBOSS no respondió correctamente.',
            ]));

            if (! $uploadOk) {
                Log::warning('UploadRadiobossJob: fallo la subida a RadioBOSS.', [
                    'program_id' => $radioProgram->id,
                    'remote_path' => $remotePath,
                    'local_path' => $this->localPath,
                    'local_exists' => is_string($absolutePath) && $absolutePath !== '' && is_file($absolutePath),
                    'verification' => $radiobossVerification,
                    'error' => $this->radiobossError,
                ]);
            }

            $this->dispatchDeliveryNotification($radioProgram->id);
        } catch (Throwable $exception) {
            if (is_string($absolutePath) && $absolutePath !== '' && str_starts_with($absolutePath, storage_path('app/tmp/backblaze')) && is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            RadioProgram::withoutEvents(fn (): bool => (bool) $radioProgram->update([
                'enviado_radioboss' => false,
                'radioboss_status' => 'error',
                'radioboss_verified_at' => null,
                'radioboss_last_error' => $exception->getMessage(),
                'radioboss_metadata' => array_merge((array) ($radioProgram->radioboss_metadata ?? []), [
                    'status' => 'error',
                    'remote_path' => $remotePath,
                    'local_path' => $this->localPath,
                    'verification' => null,
                ]),
                'status_message' => 'RadioBOSS no pudo completarse.',
            ]));

            Log::error('Error en UploadRadiobossJob', [
                'program_id' => $radioProgram->id,
                'localPath' => $this->localPath,
                'remotePath' => $remotePath,
                'local_exists' => is_string($absolutePath) && $absolutePath !== '' && is_file($absolutePath),
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
                'exception' => $exception,
            ]);

            $this->dispatchDeliveryNotification($radioProgram->id);

            throw $exception;
        }

        if (is_string($absolutePath) && $absolutePath !== '' && str_starts_with($absolutePath, storage_path('app/tmp/backblaze')) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
