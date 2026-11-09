<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingStorageCleanup;
use App\Services\PendingStorageCleanupService;
use Illuminate\Console\Command;

class StorageCleanupPendingCommand extends Command
{
    protected $signature = 'storage:cleanup-pending
                            {--limit=50 : Cantidad máxima de tareas a procesar}
                            {--dry-run : Solo muestra las tareas pendientes sin ejecutar eliminaciones}';

    protected $description = 'Procesa y reintenta las tareas pendientes de compensación y limpieza de almacenamiento huérfano.';

    public function handle(PendingStorageCleanupService $service): int
    {
        $limit = (int) $this->option('limit');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info('======================================================');
        $this->info('   COMPENSACIÓN Y LIMPIEZA DE ALMACENAMIENTO PENDIENTE');
        $this->info('======================================================');

        $pendingQuery = PendingStorageCleanup::query()->pending()->orderBy('id');
        $totalPending = $pendingQuery->count();

        $this->line("Total de tareas pendientes registradas: {$totalPending}");

        if ($totalPending === 0) {
            $this->info('No hay tareas de limpieza pendientes.');
            return self::SUCCESS;
        }

        $tasks = $pendingQuery->limit($limit)->get();

        $tableData = $tasks->map(fn (PendingStorageCleanup $t) => [
            'ID' => $t->id,
            'Disco' => $t->disk,
            'Clave' => \Illuminate\Support\Str::limit($t->storage_key, 40),
            'Operación' => $t->operation_id ?: '-',
            'Intentos' => $t->attempts,
            'Último Error' => \Illuminate\Support\Str::limit($t->last_error ?: '-', 35),
            'Fecha Registro' => $t->created_at?->toDateTimeString(),
        ]);

        $this->table(['ID', 'Disco', 'Clave', 'Operación', 'Intentos', 'Último Error', 'Fecha Registro'], $tableData);

        if ($isDryRun) {
            $this->comment('Modo --dry-run activado: no se ejecutó ninguna acción sobre el almacenamiento.');
            return self::SUCCESS;
        }

        $this->line("Procesando hasta {$limit} tareas...");
        $results = $service->retryAllPending($limit);

        $this->newLine();
        $this->info("Resultado del reintento:");
        $this->line("  - Total procesadas: {$results['total']}");
        $this->line("  - Completadas con éxito: {$results['completed']}");
        $this->line("  - Fallidas / pendientes: {$results['failed']}");

        return $results['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

