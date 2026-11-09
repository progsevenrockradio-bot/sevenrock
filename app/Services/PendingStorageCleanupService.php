<?php

namespace App\Services;

use App\Models\PendingStorageCleanup;
use App\Models\TalentMedia;
use Illuminate\Support\Facades\Log;
use Throwable;

class PendingStorageCleanupService
{
    public function record(
        string $disk,
        string $storageKey,
        ?string $operationId = null,
        string $reason = 'compensation_failed',
        ?string $error = null
    ): PendingStorageCleanup {
        $sanitizedError = $error !== null ? substr($error, 0, 1000) : null;

        Log::warning('Registrando tarea persistente de limpieza de almacenamiento pendiente', [
            'disk' => $disk,
            'storage_key' => $storageKey,
            'operation_id' => $operationId,
            'reason' => $reason,
            'error' => $sanitizedError,
        ]);

        /** @var PendingStorageCleanup|null $existing */
        $existing = PendingStorageCleanup::query()
            ->where('disk', $disk)
            ->where('storage_key', $storageKey)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            $existing->update([
                'operation_id' => $operationId ?: $existing->operation_id,
                'reason' => $reason,
                'last_error' => $sanitizedError,
                'attempts' => $existing->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            return $existing;
        }

        return PendingStorageCleanup::query()->create([
            'disk' => $disk,
            'storage_key' => $storageKey,
            'operation_id' => $operationId,
            'reason' => $reason,
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => $sanitizedError,
            'last_attempt_at' => now(),
        ]);
    }

    public function retryTask(PendingStorageCleanup $task, ?FileUploadService $fileUploadService = null): bool
    {
        $fileUploadService = $fileUploadService ?: app(FileUploadService::class);

        // Protección de objetos compartidos: si un registro válido en BD ahora apunta a este archivo,
        // no debemos borrarlo bajo ninguna circunstancia.
        $isReferenced = TalentMedia::query()
            ->where('disk', $task->disk)
            ->where('backblaze_key', $task->storage_key)
            ->exists();

        if ($isReferenced) {
            Log::info('Limpieza omitida: el objeto está referenciado por un registro activo de TalentMedia', [
                'task_id' => $task->id,
                'disk' => $task->disk,
                'key' => $task->storage_key,
            ]);

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'last_error' => 'Omitido de forma segura: clave referenciada por un registro activo.',
            ]);

            return true;
        }

        try {
            $deleted = $fileUploadService->delete($task->storage_key, $task->disk);

            if ($deleted) {
                $task->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'last_error' => null,
                ]);

                Log::info('Limpieza de almacenamiento completada exitosamente', [
                    'task_id' => $task->id,
                    'disk' => $task->disk,
                    'key' => $task->storage_key,
                ]);

                return true;
            }

            $newAttempts = $task->attempts + 1;
            $task->update([
                'attempts' => $newAttempts,
                'status' => $newAttempts >= 5 ? 'failed' : 'pending',
                'last_attempt_at' => now(),
                'last_error' => 'FileUploadService::delete retornó false en el reintento.',
            ]);

            return false;
        } catch (Throwable $e) {
            $newAttempts = $task->attempts + 1;
            $task->update([
                'attempts' => $newAttempts,
                'status' => $newAttempts >= 5 ? 'failed' : 'pending',
                'last_attempt_at' => now(),
                'last_error' => substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('Excepción al reintentar limpieza de almacenamiento', [
                'task_id' => $task->id,
                'disk' => $task->disk,
                'key' => $task->storage_key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{total: int, completed: int, failed: int}
     */
    public function retryAllPending(int $limit = 50, ?FileUploadService $fileUploadService = null): array
    {
        $tasks = PendingStorageCleanup::query()
            ->pending()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = [
            'total' => $tasks->count(),
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($tasks as $task) {
            if ($this->retryTask($task, $fileUploadService)) {
                $results['completed']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}

