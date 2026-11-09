<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PendingStorageCleanup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageCleanupPendingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2_private');
    }

    public function test_command_displays_no_pending_tasks_message_when_empty(): void
    {
        $this->artisan('storage:cleanup-pending')
            ->expectsOutputToContain('No hay tareas de limpieza pendientes.')
            ->assertSuccessful();
    }

    public function test_command_dry_run_lists_tasks_without_deleting(): void
    {
        $key = 'talents/dry_run_test.mp3';
        Storage::disk('r2_private')->put($key, 'contenido');

        PendingStorageCleanup::create([
            'disk' => 'r2_private',
            'storage_key' => $key,
            'operation_id' => 'op-dry-run',
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => 'Simulated prior failure',
        ]);

        $this->artisan('storage:cleanup-pending', ['--dry-run' => true])
            ->expectsOutputToContain('Modo --dry-run activado')
            ->assertSuccessful();

        // El archivo sigue intacto
        $this->assertTrue(Storage::disk('r2_private')->exists($key));
        $this->assertEquals('pending', PendingStorageCleanup::first()->status);
    }

    public function test_command_retries_and_completes_pending_tasks(): void
    {
        $key = 'talents/to_clean_via_command.mp3';
        Storage::disk('r2_private')->put($key, 'contenido a limpiar');

        $task = PendingStorageCleanup::create([
            'disk' => 'r2_private',
            'storage_key' => $key,
            'operation_id' => 'op-clean-cmd',
            'status' => 'pending',
            'attempts' => 1,
            'last_error' => 'Previous network failure',
        ]);

        $this->artisan('storage:cleanup-pending')
            ->expectsOutputToContain('Completadas con éxito: 1')
            ->assertSuccessful();

        $this->assertFalse(Storage::disk('r2_private')->exists($key));
        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }
}

