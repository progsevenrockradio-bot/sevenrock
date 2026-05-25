<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithPodcastUploadPipeline;
use App\Models\RadioProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        try {
            if (! Storage::disk('public')->exists($this->localPath)) {
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

                return;
            }

            $uploadOk = $this->uploadToRadiobossWithRetries($this->remoteFolder, $remotePath, $this->localPath);

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
                $radiobossVerification = $this->verifyRadiobossUpload($remotePath, $this->localPath);
                $uploadOk = (bool) ($radiobossVerification['verified'] ?? false);
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
                    'error' => $this->radiobossError,
                ]);
            }

            $this->dispatchDeliveryNotification($radioProgram->id);
        } catch (Throwable $exception) {
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
                'exception' => $exception,
            ]);

            $this->dispatchDeliveryNotification($radioProgram->id);

            throw $exception;
        }
    }
}
