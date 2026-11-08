<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\User;
use App\Support\TalentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FreePlanUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        Storage::fake('r2_private');

        if (! Schema::hasTable('gallery_images')) {
            Schema::create('gallery_images', function ($table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('image');
                $table->string('section')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('order_position')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createFreeTalent(array $attributes = []): Talent
    {
        $user = User::factory()->create();

        return Talent::query()->create(array_merge([
            'user_id' => $user->id,
            'band_name' => 'Banda Gratuita ' . uniqid(),
            'email' => 'gratuita_' . uniqid() . '@example.test',
            'password' => 'secret1234',
            'plan' => 'free',
            'subscription_status' => 'active',
            'interacts' => 10,
            'is_featured' => false,
        ], $attributes));
    }

    /**
     * 1. Definición y configuración común del Plan Gratuito.
     */
    public function test_free_plan_definition_and_model_limits_are_unified(): void
    {
        $freeDef = TalentPlan::definition('free');

        $this->assertSame(0.00, $freeDef['price']);
        $this->assertSame('0€', $freeDef['monthly_label']);
        $this->assertSame([
            'photos' => 1,
            'songs' => 2,
            'documents' => 0,
            'videos' => 0,
            'storage_mb' => 50,
        ], $freeDef['limits']);

        $this->assertSame([
            'songs' => 12,
            'photos' => 10,
            'documents' => 0,
            'videos' => 0,
        ], $freeDef['max_file_size_mb']);

        $talent = $this->createFreeTalent();
        $this->assertSame($freeDef['limits'], $talent->planLimits());
        $this->assertSame(12 * 1024, $talent->maxFileSizeKb('mp3'));
        $this->assertSame(10 * 1024, $talent->maxFileSizeKb('photo'));
    }

    /**
     * 2. Primera y segunda canción aceptadas; tercera rechazada con mensaje explicativo claro del límite.
     */
    public function test_free_plan_accepts_first_and_second_song_and_rejects_third_song(): void
    {
        $talent = $this->createFreeTalent();

        // Primera canción: aceptada
        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Primera Canción',
                'file' => UploadedFile::fake()->create('tema_1.mp3', 4 * 1024, 'audio/mpeg'),
            ])
            ->assertRedirect(route('talents.media.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $talent->media()->where('type', 'mp3')->count());
        $this->assertTrue($talent->canUpload('mp3'));

        // Segunda canción: aceptada
        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Segunda Canción',
                'file' => UploadedFile::fake()->create('tema_2.mp3', 4 * 1024, 'audio/mpeg'),
            ])
            ->assertRedirect(route('talents.media.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $talent->media()->where('type', 'mp3')->count());
        $this->assertFalse($talent->canUpload('mp3'));

        // Tercera canción: rechazada con mensaje claro del límite
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Tercera Canción Rechazada',
                'file' => UploadedFile::fake()->create('tema_3.mp3', 4 * 1024, 'audio/mpeg'),
            ]);

        $response->assertSessionHasErrors('type');
        $this->assertSame(2, $talent->media()->where('type', 'mp3')->count());

        $errorMsg = session('errors')->first('type');
        $this->assertStringContainsString('límite de 2 canciones', $errorMsg);
        $this->assertStringContainsString('actualmente tienes 2', $errorMsg);
    }

    /**
     * 3. Protección de cuentas existentes: cuenta histórica con 3 canciones las conserva.
     *    No se borra, oculta ni recorta contenido; se bloquea una 4ta canción pero se permite foto.
     */
    public function test_historical_free_account_with_three_songs_is_preserved_and_can_upload_photo(): void
    {
        $talent = $this->createFreeTalent(['band_name' => 'Banda Histórica']);

        // Crear 3 canciones preexistentes (cada una 5 MB = 15 MB total)
        for ($i = 1; $i <= 3; $i++) {
            TalentMedia::query()->create([
                'talent_id' => $talent->id,
                'type' => 'mp3',
                'filename' => "historica_{$i}.mp3",
                'disk' => 'r2',
                'backblaze_key' => "talents/{$talent->id}/media/mp3/historica_{$i}.mp3",
                'url' => "https://r2.test/talents/{$talent->id}/media/mp3/historica_{$i}.mp3",
                'title' => "Tema Histórico {$i}",
                'mime_type' => 'audio/mpeg',
                'size' => 5 * 1024 * 1024,
                'status' => 'approved',
            ]);
        }

        // Las 3 canciones se conservan en su totalidad
        $this->assertSame(3, $talent->media()->where('type', 'mp3')->count());

        // canUpload('mp3') retorna false porque tiene >= 2 canciones
        $this->assertFalse($talent->canUpload('mp3'));

        // Intento de subir una 4ta canción es bloqueado explicando que tiene 3
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Cuarta Canción Bloqueada',
                'file' => UploadedFile::fake()->create('tema_4.mp3', 3 * 1024, 'audio/mpeg'),
            ]);

        $response->assertSessionHasErrors('type');
        $this->assertStringContainsString('límite de 2 canciones', session('errors')->first('type'));
        $this->assertStringContainsString('actualmente tienes 3', session('errors')->first('type'));
        $this->assertSame(3, $talent->media()->where('type', 'mp3')->count());

        // La cuenta NO tiene fotos aún (0 < 1), y el almacenamiento usado (15 MB) está dentro de los 50 MB permitidos.
        // La carga de su foto permitida NO se bloquea a pesar de exceder el cupo de canciones.
        $this->assertTrue($talent->canUpload('photo'));

        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'title' => 'Foto Oficial de Prensa',
                'file' => UploadedFile::fake()->image('foto_banda.jpg', 800, 600)->size(2048),
            ])
            ->assertRedirect(route('talents.media.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $talent->media()->where('type', 'photo')->count());
        $this->assertSame(3, $talent->media()->where('type', 'mp3')->count());
    }

    /**
     * 4. Sustitución y eliminación: al eliminar canciones históricas se reduce el conteo hasta permitir nuevas cargas cuando esté bajo el cupo.
     */
    public function test_historical_account_can_delete_content_and_replace(): void
    {
        $talent = $this->createFreeTalent();

        $song1 = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'tema_1.mp3',
            'disk' => 'r2',
            'backblaze_key' => "talents/{$talent->id}/media/mp3/t1.mp3",
            'url' => 'https://r2.test/t1.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024 * 1024,
            'status' => 'approved',
        ]);
        $song2 = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'tema_2.mp3',
            'disk' => 'r2',
            'backblaze_key' => "talents/{$talent->id}/media/mp3/t2.mp3",
            'url' => 'https://r2.test/t2.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024 * 1024,
            'status' => 'approved',
        ]);
        $song3 = TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'tema_3.mp3',
            'disk' => 'r2',
            'backblaze_key' => "talents/{$talent->id}/media/mp3/t3.mp3",
            'url' => 'https://r2.test/t3.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 1024 * 1024,
            'status' => 'approved',
        ]);

        Storage::disk('r2')->put($song3->backblaze_key, 'audio-content');

        // Eliminar 1 canción: pasa de 3 a 2
        $this->actingAs($talent, 'talent')
            ->delete(route('talents.media.destroy', $song3->id))
            ->assertRedirect(route('talents.media.index'));

        $this->assertSame(2, $talent->media()->where('type', 'mp3')->count());
        $this->assertFalse($talent->canUpload('mp3')); // 2 canciones aún es el límite máximo

        // Eliminar otra canción: pasa de 2 a 1
        Storage::disk('r2')->put($song2->backblaze_key, 'audio-content');
        $this->actingAs($talent, 'talent')
            ->delete(route('talents.media.destroy', $song2->id))
            ->assertRedirect(route('talents.media.index'));

        $this->assertSame(1, $talent->media()->where('type', 'mp3')->count());
        $this->assertTrue($talent->canUpload('mp3')); // Ahora sí puede subir 1 nueva canción para completar sus 2

        // Sube la nueva canción de reemplazo
        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Tema de Reemplazo',
                'file' => UploadedFile::fake()->create('nuevo_tema.mp3', 2 * 1024, 'audio/mpeg'),
            ])
            ->assertRedirect(route('talents.media.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $talent->media()->where('type', 'mp3')->count());
    }

    /**
     * 5. Almacenamiento total (50 MB) y tamaño máximo de archivo respetados.
     */
    public function test_free_plan_enforces_max_file_size_and_total_storage_limits(): void
    {
        $talent = $this->createFreeTalent();

        // Archivo que excede el tamaño máximo por canción (13 MB > 12 MB)
        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'mp3',
                'file' => UploadedFile::fake()->create('muy_pesado.mp3', 13 * 1024, 'audio/mpeg'),
            ])
            ->assertSessionHasErrors('file');

        // Foto que excede el tamaño máximo (11 MB > 10 MB)
        $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => UploadedFile::fake()->image('foto_pesada.jpg')->size(11 * 1024),
            ])
            ->assertSessionHasErrors('file');

        // Simular que el almacenamiento actual ya tiene 48 MB consumidos
        TalentMedia::query()->create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'cancion_pesada.mp3',
            'disk' => 'r2',
            'backblaze_key' => "talents/{$talent->id}/media/mp3/pesada.mp3",
            'url' => 'https://r2.test/pesada.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 48 * 1024 * 1024,
            'status' => 'approved',
        ]);

        // Intentar subir una foto de 3 MB (48 + 3 = 51 MB > 50 MB total)
        $response = $this->actingAs($talent, 'talent')
            ->post(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => UploadedFile::fake()->image('foto.jpg')->size(3 * 1024),
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('límite total de almacenamiento', session('errors')->first('file'));
    }

    /**
     * 6. Coherencia en vistas: registro, comparativas, dashboard y panel de medios.
     */
    public function test_ui_and_server_are_coherent_in_register_dashboard_and_media(): void
    {
        // Registro
        $registerResponse = $this->get(route('talents.register'));
        $registerResponse->assertOk();
        $registerResponse->assertSee('2 canciones MP3');
        $registerResponse->assertSee('1 fotografía');
        $registerResponse->assertSee('50 MB de almacenamiento total');
        $registerResponse->assertDontSee('3 canciones');

        // Dashboard
        $talent = $this->createFreeTalent();
        $dashboardResponse = $this->actingAs($talent, 'talent')->get(route('talents.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('0/2'); // canciones
        $dashboardResponse->assertSee('0/1'); // fotos
        $dashboardResponse->assertSee('50 MB'); // almacenamiento total

        // Panel de Medios
        $mediaResponse = $this->actingAs($talent, 'talent')->get(route('talents.media.index'));
        $mediaResponse->assertOk();
        $mediaResponse->assertSee('0 / 2'); // Canciones (MP3)
        $mediaResponse->assertSee('(Máx. 12MB c/u)');
        $mediaResponse->assertSee('0 / 1'); // Fotografías
        $mediaResponse->assertSee('(Máx. 10MB c/u)');
        $mediaResponse->assertSee('50 MB'); // Almacenamiento total
    }

    /**
     * 7. Ausencia de cambios en los demás planes (Básico, Pro, Premium).
     */
    public function test_other_plans_remain_completely_unchanged(): void
    {
        $basic = TalentPlan::definition('basic');
        $this->assertSame(3.50, $basic['price']);
        $this->assertSame(5, $basic['limits']['songs']);
        $this->assertSame(10, $basic['limits']['photos']);
        $this->assertSame(200, $basic['limits']['storage_mb']);
        $this->assertSame(15, $basic['max_file_size_mb']['songs']);

        $pro = TalentPlan::definition('pro');
        $this->assertSame(7.00, $pro['price']);
        $this->assertSame(15, $pro['limits']['songs']);
        $this->assertSame(50, $pro['limits']['photos']);
        $this->assertSame(10, $pro['limits']['documents']);
        $this->assertSame(3, $pro['limits']['videos']);
        $this->assertSame(400, $pro['limits']['storage_mb']);
        $this->assertSame(20, $pro['max_file_size_mb']['songs']);

        $premium = TalentPlan::definition('premium');
        $this->assertSame(11.00, $premium['price']);
        $this->assertSame(999, $premium['limits']['photos']);
    }
}
