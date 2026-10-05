<?php

namespace Tests\Feature;

use App\Support\ProgramTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Models\MasterProgram;
use Tests\TestCase;

class ProgramTextNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_limpiar_conductor_removes_prefixes()
    {
        $this->assertEquals('PABLO TECHEIRA', ProgramTextNormalizer::limpiarConductor('CONDUCIDO POR: PABLO TECHEIRA'));
        $this->assertEquals('Juan', ProgramTextNormalizer::limpiarConductor('Conduce: Juan'));
        $this->assertEquals('Pablo Techeira', ProgramTextNormalizer::limpiarConductor('Pablo Techeira'));
        $this->assertEquals('Alguien', ProgramTextNormalizer::limpiarConductor('Host: Alguien'));
        $this->assertEquals('Otro', ProgramTextNormalizer::limpiarConductor('Con: Otro'));
        $this->assertEquals('', ProgramTextNormalizer::limpiarConductor('Conduce: '));
        $this->assertEquals('', ProgramTextNormalizer::limpiarConductor(null));
    }

    public function test_limpiar_descripcion_removes_consecutive_duplicates()
    {
        $this->assertEquals("Texto A.", ProgramTextNormalizer::limpiarDescripcion("Texto A.\n\nTexto A."));
        $this->assertEquals("Texto A.", ProgramTextNormalizer::limpiarDescripcion("Texto A.\nTexto A."));
        $this->assertEquals("A.\n\nB.", ProgramTextNormalizer::limpiarDescripcion("A.\nB."));
        $this->assertEquals("Texto A.\n\nTexto B.", ProgramTextNormalizer::limpiarDescripcion("Texto A.\nTexto A.\nTexto B.\nTexto B."));
    }

    public function test_command_without_apply_does_not_save_changes()
    {
        $program = MasterProgram::create([
            'nombre' => 'Test Program',
            'host' => 'Conduce: Juan',
            'description' => "Duplicado.\nDuplicado.",
            'activo' => true,
        ]);

        $this->artisan('programs:normalize-texts')
             ->expectsOutputToContain('SIMULATION MODE')
             ->expectsOutputToContain('HOST CHANGED:')
             ->expectsOutputToContain('DESC CHANGED:')
             ->assertExitCode(0);

        $program->refresh();
        $this->assertEquals('Conduce: Juan', $program->host);
        $this->assertEquals("Duplicado.\nDuplicado.", $program->description);
    }

    public function test_command_with_apply_saves_changes()
    {
        $program = MasterProgram::create([
            'nombre' => 'Test Program',
            'host' => 'Conduce: Juan',
            'description' => "Duplicado.\nDuplicado.",
            'activo' => true,
        ]);

        $this->artisan('programs:normalize-texts', ['--apply' => true])
             ->expectsOutputToContain('APPLY MODE')
             ->assertExitCode(0);

        $program->refresh();
        $this->assertEquals('Juan', $program->host);
        $this->assertEquals("Duplicado.", $program->description);
    }
}
