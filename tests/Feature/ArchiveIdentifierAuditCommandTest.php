<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArchiveIdentifierAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_archive_identifier_audit_command_reports_invalid_and_incomplete_rows(): void
    {
        $program = MasterProgram::query()->create([
            'nombre' => 'Broken Show',
            'conductor' => 'Seven Rock',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '20:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 120,
            'genero' => 'ROCK',
            'activo' => true,
            'archive_identifier' => 'bad identifier',
        ]);

        RadioProgram::query()->create([
            'titulo_programa' => 'Broken Episode',
            'master_program_id' => $program->id,
            'fecha_emision' => now()->toDateString(),
            'conductor' => 'Seven Rock',
            'genero_musical' => 'ROCK',
            'sync_archive_org' => true,
            'archive_org_status' => 'uploaded',
            'archive_org_remote_path' => null,
            'archive_org_uploaded_at' => now(),
            'archive_org_metadata' => null,
        ]);

        $exitCode = Artisan::call('sevenrock:audit-archive-identifiers', ['--limit' => 50]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Broken Show', $output);
        $this->assertStringContainsString('invalid_identifier_format', $output);
        $this->assertStringContainsString('missing_remote_path', $output);
        $this->assertStringContainsString('missing_metadata', $output);
    }
}
