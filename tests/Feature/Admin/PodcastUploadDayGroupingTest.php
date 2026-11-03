<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PodcastUploadDayGroupingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_test_' . uniqid() . '@example.com',
            'is_admin' => true,
        ]);
    }

    private function createProgram(array $attributes = []): MasterProgram
    {
        return MasterProgram::create(array_merge([
            'nombre' => 'Programa Test ' . uniqid(),
            'conductor' => 'Conductor Test',
            'genero' => 'Rock',
            'dia_transmision' => 'LUNES',
            'hora_transmision' => '12:00:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 60,
            'activo' => true,
        ], $attributes));
    }

    public function test_program_with_friday_base_and_tuesday_rebroadcast_appears_in_both_selector_tabs(): void
    {
        // Caso exacto del programa ID 26: Viernes base 17:05 y Martes retransmisión 13:05
        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA TEST',
            'program_code' => 'UNOPORSEM',
            'conductor' => 'Lucho Almirón',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));

        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');

        $tuesdayPrograms = $programsByDay->get('MARTES');
        $fridayPrograms = $programsByDay->get('VIERNES');
        $mondayPrograms = $programsByDay->get('LUNES');

        $this->assertTrue($tuesdayPrograms->contains('id', $program->id), 'El programa ID 26 debe aparecer en el selector del MARTES');
        $this->assertTrue($fridayPrograms->contains('id', $program->id), 'El programa ID 26 debe aparecer en el selector del VIERNES');
        $this->assertFalse($mondayPrograms->contains('id', $program->id), 'El programa NO debe aparecer en LUNES');
    }

    public function test_selector_programs_are_sorted_by_day_pass_time(): void
    {
        // En martes: crear dos programas
        // Prog A: pase a las 18:00
        $progA = $this->createProgram([
            'nombre' => 'Programa Tarde',
            'program_code' => 'TARDE',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00:00',
            'activo' => true,
        ]);
        $progA->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '18:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // Prog B: pase a las 10:00 (debe ir primero en el selector)
        $progB = $this->createProgram([
            'nombre' => 'Programa Manana',
            'program_code' => 'MANANA',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '10:00:00',
            'activo' => true,
        ]);
        $progB->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $tuesdayList = $programsByDay->get('MARTES');

        $this->assertCount(2, $tuesdayList);
        $this->assertSame($progB->id, $tuesdayList->first()->id, 'El programa de las 10:00 debe aparecer antes que el de las 18:00');
        $this->assertSame($progA->id, $tuesdayList->last()->id);
    }

    public function test_historical_program_without_emisiones_falls_back_to_base_schedule(): void
    {
        $historical = $this->createProgram([
            'nombre' => 'Programa Antiguo Sin Emisiones',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '21:00:00',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $saturdayList = $programsByDay->get('SABADO');

        $this->assertTrue($saturdayList->contains('id', $historical->id), 'Debe aparecer en SÁBADO por retrocompatibilidad');
    }

    public function test_explicitly_disabled_emision_does_not_fall_back_to_base_schedule(): void
    {
        $program = $this->createProgram([
            'nombre' => 'Programa Desactivado',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '15:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'JUEVES',
            'hora_inicio' => '15:00:00',
            'duracion_minutos' => 60,
            'activo' => false, // Inactivo explícito
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $thursdayList = $programsByDay->get('JUEVES');

        $this->assertFalse($thursdayList->contains('id', $program->id), 'No debe aparecer si su única emisión está inactiva');
    }

    public function test_single_row_per_program_even_with_multiple_passes_on_same_day(): void
    {
        $program = $this->createProgram([
            'nombre' => 'Programa Doble Pase Viernes',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '10:00:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '22:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $fridayList = $programsByDay->get('VIERNES');

        $occurrences = $fridayList->where('id', $program->id)->count();
        $this->assertSame(1, $occurrences, 'Debe aparecer exactamente una vez en la pestaña de VIERNES');
    }

    public function test_suggested_episode_number_is_shared_across_day_passes_without_duplication(): void
    {
        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA TEST',
            'program_code' => 'UNOPORSEM',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        // Crear episodio 1 previamente
        RadioProgram::create([
            'master_program_id' => $program->id,
            'titulo_programa' => $program->nombre,
            'conductor' => $program->conductor,
            'numero_episodio' => 1,
            'fecha_emision' => '2026-11-06',
            'hora_emision' => '17:05:00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $response->viewData('programsByDay');
        $tuesdayProgram = $programsByDay->get('MARTES')->firstWhere('id', $program->id);
        $fridayProgram = $programsByDay->get('VIERNES')->firstWhere('id', $program->id);

        $this->assertNotNull($tuesdayProgram);
        $this->assertNotNull($fridayProgram);
        $this->assertSame(2, $tuesdayProgram->next_episode_suggested, 'El número sugerido para martes debe ser 2');
        $this->assertSame(2, $fridayProgram->next_episode_suggested, 'El número sugerido para viernes debe ser 2');
    }

    public function test_calculate_emission_deadline_uses_active_emission_time_for_weekday(): void
    {
        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA TEST',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'submission_cutoff_minutes' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        // Fecha martes: 2026-11-10 (Martes)
        $tuesdayDeadline = $program->calculateEmissionDeadline('2026-11-10');
        $this->assertSame('ok', $tuesdayDeadline['status']);
        /** @var \Carbon\Carbon $tuesdayEmissionDt */
        $tuesdayEmissionDt = $tuesdayDeadline['emission_datetime'];
        $this->assertSame('13:05:00', $tuesdayEmissionDt->format('H:i:s'), 'En martes debe usar 13:05:00');
        $this->assertSame('12:05:00', $tuesdayDeadline['deadline_datetime']->format('H:i:s'));

        // Fecha viernes: 2026-11-13 (Viernes)
        $fridayDeadline = $program->calculateEmissionDeadline('2026-11-13');
        $this->assertSame('ok', $fridayDeadline['status']);
        /** @var \Carbon\Carbon $fridayEmissionDt */
        $fridayEmissionDt = $fridayDeadline['emission_datetime'];
        $this->assertSame('17:05:00', $fridayEmissionDt->format('H:i:s'), 'En viernes debe usar 17:05:00');
        $this->assertSame('16:05:00', $fridayDeadline['deadline_datetime']->format('H:i:s'));
    }

    public function test_calculate_emission_deadline_blocks_days_without_active_emission_when_emisiones_exist(): void
    {
        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA TEST',
            'dia_transmision' => 'VIERNES', // Base viernes
            'hora_transmision' => '17:05:00',
            'submission_cutoff_minutes' => 60,
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        // Emisión activa solo en martes
        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        // Emisión explícitamente desactivada en viernes
        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => false,
        ]);

        // Consultar para un miércoles (sin emisión): debe bloquearse explícitamente
        $wednesdayDeadline = $program->calculateEmissionDeadline('2026-11-11');
        $this->assertSame('no_active_emission', $wednesdayDeadline['status']);
        $this->assertTrue($wednesdayDeadline['is_closed']);
        $this->assertStringContainsString('MIERCOLES', $wednesdayDeadline['reason']);

        // Consultar para un viernes (desactivada): no debe caer en el base del viernes, debe bloquearse
        $fridayDeadline = $program->calculateEmissionDeadline('2026-11-13');
        $this->assertSame('no_active_emission', $fridayDeadline['status']);
        $this->assertTrue($fridayDeadline['is_closed']);
        $this->assertStringContainsString('VIERNES', $fridayDeadline['reason']);
    }

    public function test_calculate_emission_deadline_with_multiple_active_passes_on_same_day_protects_earliest_pass(): void
    {
        $program = $this->createProgram([
            'nombre' => 'Programa Multiple Pase',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '18:00:00',
            'submission_cutoff_minutes' => 120, // 2 horas antes
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        // Pase tarde 20:00
        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '20:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // Primer pase 11:00
        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '11:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // Pase intermedio 15:00
        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '15:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $deadline = $program->calculateEmissionDeadline('2026-11-10'); // Martes
        $this->assertSame('ok', $deadline['status']);
        /** @var \Carbon\Carbon $emissionDt */
        $emissionDt = $deadline['emission_datetime'];
        $this->assertSame('11:00:00', $emissionDt->format('H:i:s'), 'Debe seleccionar el pase más temprano (11:00:00)');
        $this->assertSame('09:00:00', $deadline['deadline_datetime']->format('H:i:s'), 'El cierre debe proteger la primera emisión de la jornada');
    }

    public function test_end_to_end_podcast_upload_submission_for_tuesday_rebroadcast(): void
    {
        // Prueba de extremo a extremo del envío real del formulario para el programa (ID 26 representativo)
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('public');

        $program = $this->createProgram([
            'nombre' => 'UNO POR SEMANA .... LA LEYENDA CONTINUA',
            'program_code' => 'UNOPORSEM',
            'conductor' => 'Lucho Almirón',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '17:05:00',
            'timezone' => 'America/Caracas',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'normal',
            'dia_semana' => 'VIERNES',
            'hora_inicio' => '17:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $program->emisiones()->create([
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '13:05:00',
            'duracion_minutos' => 65,
            'activo' => true,
        ]);

        $mp3File = \Illuminate\Http\UploadedFile::fake()->create('episodio-martes.mp3', 1024, 'audio/mpeg');

        $payload = [
            'master_program_id' => $program->id,
            'numero_episodio' => '1',
            'live_title' => 'Especial Leyendas Martes',
            'fecha_emision' => '2026-11-10', // Martes
            'archivo_mp3' => $mp3File,
            'pipeline_action' => 'save', // Sin disparar colas reales pesadas
            'sync_archive_org' => 0,
        ];

        // 1. Envío POST desde el formulario
        $postResponse = $this->actingAs($this->admin)->post(route('admin.podcast-uploads.store'), $payload);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertRedirect();

        // 2. Comprobar persistencia del episodio
        $createdEpisode = RadioProgram::where('master_program_id', $program->id)
            ->where('live_title', 'Especial Leyendas Martes')
            ->first();

        $this->assertNotNull($createdEpisode, 'El episodio para el martes debe haberse creado en la base de datos');
        $this->assertSame(1, $createdEpisode->numero_episodio);
        $this->assertSame('2026-11-10', Carbon::parse($createdEpisode->fecha_emision)->toDateString());
        $this->assertSame('Lucho Almirón', $createdEpisode->conductor);

        // 3. Consulta posterior del index del formulario:
        // El programa ahora debe sugerir el episodio 2 en ambas pestañas (Martes y Viernes)
        $indexResponse = $this->actingAs($this->admin)->get(route('admin.podcast-uploads.index'));
        $indexResponse->assertOk();

        /** @var \Illuminate\Support\Collection $programsByDay */
        $programsByDay = $indexResponse->viewData('programsByDay');
        $tuesdayItem = $programsByDay->get('MARTES')->firstWhere('id', $program->id);
        $fridayItem = $programsByDay->get('VIERNES')->firstWhere('id', $program->id);

        $this->assertNotNull($tuesdayItem);
        $this->assertNotNull($fridayItem);
        $this->assertSame(2, $tuesdayItem->next_episode_suggested, 'El número sugerido en Martes debe actualizarse a 2');
        $this->assertSame(2, $fridayItem->next_episode_suggested, 'El número sugerido en Viernes debe actualizarse a 2');
    }
}
