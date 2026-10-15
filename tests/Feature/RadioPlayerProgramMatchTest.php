<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\RadioPlayerService;
use App\Support\RadioPlayerStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RadioPlayerProgramMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'player.radioboss.api_url' => '',
            'player.radioboss.metadata_txt_url' => '',
        ]);
    }

    public function test_matches_program_by_filename_even_when_outside_schedule_and_sets_track_metadata(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas'));

        $master = MasterProgram::create([
            'nombre' => 'Polución',
            'conductor' => 'Yury Fernández',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución',
            'live_title' => 'POLUCIÓN PODCAST 52',
            'conductor' => 'Yury Fernández',
            'numero_episodio' => 52,
            'fecha_emision' => '2026-10-15',
            'duration_seconds' => 3600,
        ]);

        app(RadioPlayerStateStore::class)->write([
            'title' => 'Transmisión oficial',
            'artist' => 'Seven Rock Radio',
            'file' => '\\04.- JUEVES\\18.- POLUCION\\052.- POLUCION 15-10-2026.mp3',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();
        $diagnosis = $service->getLastDiagnosis();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertSame('POLUCIÓN PODCAST 52', $state['track']['title']);
        $this->assertSame('Polución', $state['track']['artist']);
        $this->assertSame('fichero', $diagnosis['match_via']);
        $this->assertSame('Polución', $diagnosis['matched_program']);
    }

    public function test_normal_music_track_does_not_trigger_program_block(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'America/Caracas'));

        MasterProgram::create([
            'nombre' => 'Polución',
            'conductor' => 'Yury Fernández',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        app(RadioPlayerStateStore::class)->write([
            'title' => 'Enter Sandman',
            'artist' => 'Metallica',
            'album' => 'Metallica',
        ]);

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();

        $this->assertFalse($state['es_bloque_programa']);
        $this->assertSame('Enter Sandman', $state['track']['title']);
        $this->assertSame('Metallica', $state['track']['artist']);
    }

    public function test_schedule_matches_program_within_expanded_window_margin(): void
    {
        // 20 minutes before transmission starts (within 30 minute margin)
        Carbon::setTestNow(Carbon::parse('2026-10-15 17:40:00', 'America/Caracas'));

        $master = MasterProgram::create([
            'nombre' => 'Polución',
            'conductor' => 'Yury Fernández',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '18:00:00',
            'duracion_minutos' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => 'Polución',
            'live_title' => 'POLUCIÓN PODCAST 52',
            'conductor' => 'Yury Fernández',
            'numero_episodio' => 52,
            'fecha_emision' => '2026-10-15',
            'duration_seconds' => 3600,
        ]);

        app(RadioPlayerStateStore::class)->forget();

        $service = app(RadioPlayerService::class);
        $state = $service->resolve();
        $diagnosis = $service->getLastDiagnosis();

        $this->assertTrue($state['es_bloque_programa']);
        $this->assertSame('Polución', $state['program_name']);
        $this->assertSame('horario', $diagnosis['match_via']);
    }
}
