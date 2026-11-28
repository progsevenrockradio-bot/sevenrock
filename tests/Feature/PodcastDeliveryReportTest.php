<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MasterProgram;
use App\Models\MasterProgramEmision;
use App\Models\RadioProgram;
use App\Models\RadioProgramEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class PodcastDeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-view-cache';
        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config([
            'view.compiled' => $compiledPath,
            'app.timezone' => 'Europe/Madrid',
        ]);
    }

    public function test_independent_upload_and_emission_filters_and_return_to_preservation(): void
    {
        Bus::fake();
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Principal']);
        $uploader = User::factory()->create(['is_conductor' => true, 'name' => 'Carlos Rock']);

        $program = MasterProgram::factory()->create([
            'nombre' => 'Zona Metal',
            'conductor' => 'Carlos Rock',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '20:00:00',
            'timezone' => 'Europe/Madrid',
            'activo' => true,
        ]);

        // Episodios subidos el lunes 23/11/2026 con emisiones martes, miércoles, jueves y viernes
        $mondayUpload = Carbon::create(2026, 11, 23, 14, 30, 0, 'Europe/Madrid');

        $tueEp = $this->createEpisode($program, [
            'numero_episodio' => 101,
            'live_title' => 'Especial Martes',
            'submitted_by_user_id' => $uploader->id,
            'fecha_emision' => '2026-11-24',
            'dia_transmision' => 'MARTES',
            'radioboss_status' => 'radioboss_verified',
            'archive_org_status' => 'archive_verified',
            'delivery_status' => 'delivery_verified',
        ], $mondayUpload);

        $wedEp = $this->createEpisode($program, [
            'numero_episodio' => 102,
            'live_title' => 'Especial Miercoles',
            'submitted_by_user_id' => $uploader->id,
            'fecha_emision' => '2026-11-25',
            'dia_transmision' => 'MIERCOLES',
            'radioboss_status' => 'radioboss_pending',
            'archive_org_status' => 'archive_pending',
            'delivery_status' => 'delivery_pending',
        ], $mondayUpload->copy()->addMinutes(10));

        $friEp = $this->createEpisode($program, [
            'numero_episodio' => 103,
            'live_title' => 'Especial Viernes',
            'submitted_by_user_id' => $uploader->id,
            'fecha_emision' => '2026-11-27',
            'dia_transmision' => 'VIERNES',
        ], $mondayUpload->copy()->addMinutes(20));

        // Episodio subido otro día (domingo 22/11) pero emitido el miércoles 25/11
        $sundayEp = $this->createEpisode($program, [
            'numero_episodio' => 100,
            'live_title' => 'Subido Domingo',
            'submitted_by_user_id' => $uploader->id,
            'fecha_emision' => '2026-11-25',
            'dia_transmision' => 'MIERCOLES',
        ], Carbon::create(2026, 11, 22, 18, 0, 0, 'Europe/Madrid'));

        // 1. Filtrar "subidos el lunes 23/11": debe mostrar Martes, Miércoles y Viernes, pero NO el del domingo
        $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.index', [
            'uploaded_from' => '2026-11-23',
            'uploaded_to' => '2026-11-23',
            'sort_by' => 'emission_date',
            'sort_dir' => 'asc',
        ]));

        $response->assertOk()
            ->assertSee('Especial Martes')
            ->assertSee('Especial Miercoles')
            ->assertSee('Especial Viernes')
            ->assertDontSee('Subido Domingo')
            ->assertSee('23/11/2026')
            ->assertSee('24/11/2026')
            ->assertSee('25/11/2026')
            ->assertSee('27/11/2026');

        // 2. Filtrar independientemente por fecha de emisión (solo 25/11)
        $emissionOnly = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.index', [
            'emission_from' => '2026-11-25',
            'emission_to' => '2026-11-25',
        ]));

        $emissionOnly->assertOk()
            ->assertSee('Especial Miercoles')
            ->assertSee('Subido Domingo')
            ->assertDontSee('Especial Martes')
            ->assertDontSee('Especial Viernes');

        // 3. Abrir detalle editorial con return_to conserva los filtros en el enlace de vuelta
        $returnUrl = route('admin.podcast-uploads.deliveries.index', [
            'uploaded_from' => '2026-11-23',
            'uploaded_to' => '2026-11-23',
        ]);
        $detailResponse = $this->actingAs($admin)->get(route('admin.podcast-uploads.editorial.show', [
            'radioProgram' => $tueEp->id,
            'return_to' => $returnUrl,
        ]));

        $detailResponse->assertOk()
            ->assertSee('Volver a Entregas y emisiones')
            ->assertSee('uploaded_from=2026-11-23', false);

        // 4. La consulta no dispara trabajos ni correos
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();

        // El acceso existe en Emisiones y podcasts
        $this->actingAs($admin)
            ->get(route('admin.podcast-uploads.index'))
            ->assertOk()
            ->assertSee('Entregas y emisiones');
    }

    public function test_known_submitter_legacy_unregistered_user_and_audio_revisions(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Actual']);
        $originalUploader = User::factory()->create(['is_conductor' => true, 'name' => 'Laura Conductora']);

        $program = MasterProgram::factory()->create([
            'nombre' => 'Noche Alternativa',
            'conductor' => 'Conductor Por Defecto',
            'dia_transmision' => 'JUEVES',
            'hora_transmision' => '21:00:00',
            'timezone' => 'America/Caracas',
            'activo' => true,
        ]);

        // Registro con usuario conocido y sustitución de audio
        $withRevisions = $this->createEpisode($program, [
            'numero_episodio' => 10,
            'live_title' => 'Episodio Revisado',
            'submitted_by_user_id' => $originalUploader->id,
            'fecha_emision' => '2026-11-26',
            'editorial_version' => 2,
            'editorial_original_payload' => [
                'initial' => ['version' => 1, 'archivo_mp3' => 'podcast-inbox/v1.mp3', 'reason' => 'initial_submission'],
                'current' => ['version' => 2, 'archivo_mp3' => 'podcast-inbox/v2.mp3', 'reason' => 'conductor_revision'],
                'history' => [
                    ['version' => 1, 'archivo_mp3' => 'podcast-inbox/v1.mp3', 'reason' => 'initial_submission', 'recorded_at' => '2026-11-23T10:00:00Z'],
                ],
            ],
        ], Carbon::create(2026, 11, 23, 10, 0, 0, 'Europe/Madrid'));

        RadioProgramEvent::query()->create([
            'radio_program_id' => $withRevisions->id,
            'event_type' => 'CONDUCTOR_SUBMISSION_UPDATED',
            'event_message' => 'El conductor sustituyó el audio antes de emisión.',
            'metadata' => ['new_version' => 2, 'had_prepared_audio' => true],
            'created_at' => Carbon::create(2026, 11, 24, 12, 0, 0, 'Europe/Madrid'),
        ]);

        // Registro antiguo sin submitted_by_user_id
        $this->createEpisode($program, [
            'numero_episodio' => 9,
            'live_title' => 'Episodio Historico Sin Usuario',
            'submitted_by_user_id' => null,
            'conductor' => 'Conductor Por Defecto',
            'fecha_emision' => '2026-11-19',
        ], Carbon::create(2026, 11, 18, 9, 0, 0, 'Europe/Madrid'));

        $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.index'));
        $response->assertOk()
            ->assertSee('Laura Conductora')
            ->assertSee('v2 · Audio sustituido')
            ->assertSee('Usuario no registrado');

        // En la vista de impresión (sin barra de sesión del admin), el registro antiguo nunca se atribuye al admin ni al conductor
        $printLegacy = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.print', [
            'submitted_by_user_id' => 'unregistered',
        ]));

        $printLegacy->assertOk()
            ->assertSee('Episodio Historico Sin Usuario')
            ->assertSee('Usuario no registrado')
            ->assertDontSee('Admin Actual')
            ->assertDontSee('Conductor Por Defecto');

        // Filtro explícito por usuario no registrado
        $unregisteredFilter = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.index', [
            'submitted_by_user_id' => 'unregistered',
        ]));

        $unregisteredFilter->assertOk()
            ->assertSee('Episodio Historico Sin Usuario')
            ->assertDontSee('Episodio Revisado');
    }

    public function test_schedule_reference_timezone_missing_dates_and_calendar_mismatch_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $program = MasterProgram::factory()->create([
            'nombre' => 'Rock Sin Fronteras',
            'dia_transmision' => 'MARTES',
            'hora_transmision' => '20:00:00',
            'timezone' => 'America/Mexico_City',
            'activo' => true,
        ]);

        MasterProgramEmision::query()->create([
            'master_program_id' => $program->id,
            'tipo' => 'normal',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '20:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        MasterProgramEmision::query()->create([
            'master_program_id' => $program->id,
            'tipo' => 'retransmision',
            'dia_semana' => 'MARTES',
            'hora_inicio' => '23:00:00',
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        // 1. Episodio en martes sin hora_inicio guardada -> muestra franjas como "Ref. actual" y zona horaria
        $this->createEpisode($program, [
            'numero_episodio' => 20,
            'live_title' => 'Episodio En Martes',
            'fecha_emision' => '2026-11-24', // Martes
            'hora_inicio' => null,
            'dia_transmision' => 'MARTES',
        ]);

        // 2. Episodio sin fecha de emisión -> indica explícitamente que falta (no inventa created_at)
        $this->createEpisode($program, [
            'numero_episodio' => 21,
            'live_title' => 'Episodio Sin Fecha',
            'fecha_emision' => null,
            'hora_inicio' => null,
        ]);

        // 3. Episodio en jueves sin autorización manual -> aviso de discrepancia sin corregir
        $mismatchEp = $this->createEpisode($program, [
            'numero_episodio' => 22,
            'live_title' => 'Episodio Discrepante',
            'fecha_emision' => '2026-11-26', // Jueves (calendario es Martes)
            'dia_transmision' => 'MARTES',
        ]);

        // 4. Episodio en jueves CON fecha manual autorizada -> NO se marca como error/discrepancia
        $this->createEpisode($program, [
            'numero_episodio' => 23,
            'live_title' => 'Episodio Manual Autorizado',
            'fecha_emision' => '2026-11-26', // Jueves
            'dia_transmision' => 'MARTES',
            'late_submission_authorized_by' => $admin->id,
            'late_submission_authorized_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.index'));
        $response->assertOk()
            ->assertSee('20:00 · 23:00 (Retrans.)')
            ->assertSee('Ref. actual')
            ->assertSee('America/Mexico_City')
            ->assertSee('Fecha no registrada')
            ->assertSee('No coincide con calendario actual')
            ->assertSee('Fecha manual autorizada');

        // Confirmar que la consulta NO corrigió la fecha guardada
        $this->assertSame('2026-11-26', $mismatchEp->fresh()->fecha_emision?->toDateString());
    }

    public function test_print_includes_all_filtered_results_and_permissions_are_enforced(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $conductorA = User::factory()->create(['is_conductor' => true, 'name' => 'Conductor A']);
        $conductorB = User::factory()->create(['is_conductor' => true, 'name' => 'Conductor B']);

        $programA = MasterProgram::factory()->create([
            'nombre' => 'Programa Alfa',
            'timezone' => 'Europe/Madrid',
            'activo' => true,
        ]);
        $programB = MasterProgram::factory()->create([
            'nombre' => 'Programa Beta',
            'timezone' => 'America/Bogota',
            'activo' => true,
        ]);

        $programA->authorizeConductor($conductorA, $admin->id);
        $programB->authorizeConductor($conductorB, $admin->id);

        // Crear 28 episodios del Programa Alfa (supera la paginación de 25)
        for ($i = 1; $i <= 28; $i++) {
            $this->createEpisode($programA, [
                'numero_episodio' => $i,
                'live_title' => "Episodio Alfa #{$i}",
                'submitted_by_user_id' => $conductorA->id,
                'fecha_emision' => '2026-11-24',
            ], Carbon::create(2026, 11, 23, 10, 0, 0, 'Europe/Madrid')->addMinutes($i));
        }

        // Episodio de Conductor B en Programa Beta
        $this->createEpisode($programB, [
            'numero_episodio' => 1,
            'live_title' => 'Episodio Secreto Beta',
            'submitted_by_user_id' => $conductorB->id,
            'fecha_emision' => '2026-11-24',
        ], Carbon::create(2026, 11, 23, 12, 0, 0, 'Europe/Madrid'));

        // 1. Admin en impresión filtrada por Programa Alfa ve los 28 resultados (no solo 25)
        $adminPrint = $this->actingAs($admin)->get(route('admin.podcast-uploads.deliveries.print', [
            'master_program_id' => $programA->id,
        ]));

        $adminPrint->assertOk()
            ->assertSee('Total de episodios:')
            ->assertSee('28')
            ->assertSee('Episodio Alfa #1')
            ->assertSee('Episodio Alfa #28')
            ->assertDontSee('Episodio Secreto Beta')
            ->assertSee('Europe/Madrid');

        // 2. Conductor A no puede acceder a la ruta administrativa (403)
        $this->actingAs($conductorA)
            ->get(route('admin.podcast-uploads.deliveries.index'))
            ->assertForbidden();

        $this->actingAs($conductorA)
            ->get(route('admin.podcast-uploads.deliveries.print'))
            ->assertForbidden();

        // 3. Conductor A en su vista acotada solo ve sus propios episodios permitidos (nunca los de Conductor B)
        $conductorList = $this->actingAs($conductorA)
            ->get(route('conductor.episodes.deliveries.index'));

        $conductorList->assertOk()
            ->assertSee('Episodio Alfa #28')
            ->assertDontSee('Episodio Secreto Beta');

        $conductorPrint = $this->actingAs($conductorA)
            ->get(route('conductor.episodes.deliveries.print'));

        $conductorPrint->assertOk()
            ->assertSee('Episodio Alfa #1')
            ->assertSee('Episodio Alfa #28')
            ->assertDontSee('Episodio Secreto Beta');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEpisode(MasterProgram $program, array $overrides = [], ?Carbon $createdAt = null): RadioProgram
    {
        $episode = RadioProgram::withoutEvents(static function () use ($program, $overrides): RadioProgram {
            return RadioProgram::query()->create(array_merge([
                'master_program_id' => $program->id,
                'titulo_programa' => $program->nombre,
                'conductor' => $program->conductor ?: 'Conductor',
                'numero_episodio' => 1,
                'live_title' => 'Episodio de prueba',
                'fecha_emision' => '2026-11-24',
                'archivo_mp3' => 'podcast-inbox/test-' . uniqid() . '.mp3',
                'archivo_mp3_disk' => 'r2',
                'enviado_radioboss' => false,
                'radioboss_status' => 'skipped',
                'archive_org_status' => 'skipped',
                'delivery_status' => 'skipped',
                'editorial_status' => RadioProgram::EDITORIAL_STATUS_NOT_REQUIRED,
                'editorial_version' => 1,
            ], $overrides));
        });

        if ($createdAt !== null) {
            $episode->timestamps = false;
            $episode->created_at = $createdAt;
            $episode->updated_at = $createdAt;
            $episode->saveQuietly();
            $episode->timestamps = true;
        }

        return $episode->fresh();
    }
}
