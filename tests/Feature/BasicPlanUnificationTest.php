<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Talent\MediaController;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Services\FileUploadService;
use App\Support\TalentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class BasicPlanUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Simular FileUploadService en pruebas para no requerir credenciales R2 externas
        $mock = \Mockery::mock(FileUploadService::class);
        $mock->shouldReceive('isR2Configured')->andReturn(true);
        $mock->shouldReceive('isR2PrivateConfigured')->andReturn(true);
        $mock->shouldReceive('upload')->andReturnUsing(function (UploadedFile $file, string $folder, string $disk) {
            return [
                'key' => $folder . '/' . $file->getClientOriginalName(),
                'url' => 'https://cdn.sevenrockradio.test/' . $folder . '/' . $file->getClientOriginalName(),
            ];
        });
        $this->app->instance(FileUploadService::class, $mock);
    }

    private function createBasicTalent(array $overrides = []): Talent
    {
        return Talent::create(array_merge([
            'band_name' => 'Banda Básico Unificada',
            'email' => 'basico@unificado.test',
            'password' => Hash::make('Secret123!'),
            'plan' => 'basic',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ], $overrides));
    }

    public function test_basic_plan_definitions_and_model_limits_are_unified_without_touching_other_plans(): void
    {
        $basicDef = TalentPlan::definition('basic');
        $this->assertSame(3.50, $basicDef['price']);
        $this->assertSame('3,50 €/mes', $basicDef['monthly_label']);
        $this->assertSame([
            'photos' => 10,
            'songs' => 5,
            'documents' => 0,
            'videos' => 0,
            'storage_mb' => 200,
        ], $basicDef['limits']);

        $basicTalent = $this->createBasicTalent();
        $this->assertSame($basicDef['limits'], $basicTalent->planLimits());
        $this->assertSame(15 * 1024, $basicTalent->maxFileSizeKb('mp3'));
        $this->assertSame(10 * 1024, $basicTalent->maxFileSizeKb('photo'));

        // Gratuito, Pro y Premium unificados o intactos
        $freeTalent = $this->createBasicTalent(['band_name' => 'Free Band', 'email' => 'free@test.com', 'plan' => 'free']);
        $this->assertSame(['photos' => 1, 'songs' => 2, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50], $freeTalent->planLimits());

        $proTalent = $this->createBasicTalent(['band_name' => 'Pro Band', 'email' => 'pro@test.com', 'plan' => 'pro']);
        $this->assertSame(['photos' => 50, 'songs' => 15, 'documents' => 10, 'videos' => 3, 'storage_mb' => 400], $proTalent->planLimits());

        $premiumTalent = $this->createBasicTalent(['band_name' => 'Prem Band', 'email' => 'prem@test.com', 'plan' => 'premium']);
        $this->assertSame(['photos' => 999, 'songs' => 50, 'documents' => 999, 'videos' => 999, 'storage_mb' => 20480], $premiumTalent->planLimits());
    }

    public function test_basic_plan_enforces_5_songs_and_15mb_per_song_limit(): void
    {
        $talent = $this->createBasicTalent();

        // 1. Subir un MP3 de 16 MB (> 15 MB permitidos en Básico) falla en el servidor
        $tooBigSong = UploadedFile::fake()->create('demasiado_grande.mp3', 16 * 1024, 'audio/mpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Tema gigante',
                'file' => $tooBigSong,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // 2. Subir 5 canciones válidas de 2 MB cada una funciona
        for ($i = 1; $i <= 5; $i++) {
            $song = UploadedFile::fake()->create("tema_{$i}.mp3", 2048, 'audio/mpeg');
            $this->actingAs($talent, 'talent')
                ->postJson(route('talents.media.upload'), [
                    'type' => 'mp3',
                    'title' => "Canción {$i}",
                    'file' => $song,
                ])
                ->assertOk();
        }

        $this->assertSame(5, $talent->media()->where('type', 'mp3')->count());
        $this->assertFalse($talent->canUpload('mp3'));

        // 3. Intentar subir una 6.ª canción se bloquea en el servidor
        $sixthSong = UploadedFile::fake()->create('tema_6.mp3', 1024, 'audio/mpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'mp3',
                'title' => 'Canción 6',
                'file' => $sixthSong,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        $this->assertSame(5, $talent->media()->where('type', 'mp3')->count());
    }

    public function test_basic_plan_enforces_10_photos_and_10mb_per_photo_limit(): void
    {
        $talent = $this->createBasicTalent();

        // 1. Foto de 11 MB (> 10 MB por foto) es rechazada
        $tooBigPhoto = UploadedFile::fake()->create('foto_pesada.jpg', 11 * 1024, 'image/jpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => $tooBigPhoto,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // 2. Subir 10 fotos válidas funciona
        for ($i = 1; $i <= 10; $i++) {
            $photo = UploadedFile::fake()->create("foto_{$i}.jpg", 512, 'image/jpeg');
            $this->actingAs($talent, 'talent')
                ->postJson(route('talents.media.upload'), [
                    'type' => 'photo',
                    'title' => "Foto {$i}",
                    'file' => $photo,
                ])
                ->assertOk();
        }

        $this->assertSame(10, $talent->media()->where('type', 'photo')->count());
        $this->assertFalse($talent->canUpload('photo'));

        // 3. La foto 11 es rechazada por superar el límite de 10 fotografías
        $photo11 = UploadedFile::fake()->create('foto_11.jpg', 512, 'image/jpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => $photo11,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_basic_plan_enforces_200mb_total_storage_and_forbids_video_uploads_while_allowing_youtube_links(): void
    {
        $talent = $this->createBasicTalent();

        // 1. Subir archivo de vídeo en Básico está prohibido
        $video = UploadedFile::fake()->create('videoclip.mp4', 2048, 'video/mp4');
        $resVideo = $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'video',
                'file' => $video,
            ]);
        $resVideo->assertStatus(422)->assertJsonValidationErrors(['type']);
        $this->assertStringContainsString('YouTube', (string) $resVideo->json('message'));

        // 2. Guardar enlace a YouTube en el perfil funciona y no consume almacenamiento
        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'bio' => 'Hard rock desde el sur.',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'instagram_url' => 'https://instagram.com/bandabasico',
            ])
            ->assertRedirect(route('talents.profile'));

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $talent->fresh()->youtube_url);
        $this->assertSame(0, $talent->fresh()->storageUsed());

        // 3. Almacenamiento de 200 MB: si ya ocupa 195 MB, un MP3 de 10 MB se rechaza, pero uno de 4 MB entra
        TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'filename' => 'previo.mp3',
            'disk' => 'r2',
            'backblaze_key' => 'talents/1/media/mp3/previo.mp3',
            'url' => 'https://cdn.sevenrockradio.test/previo.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 195 * 1024 * 1024,
        ]);

        $song10Mb = UploadedFile::fake()->create('diez_mb.mp3', 10 * 1024, 'audio/mpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'mp3',
                'file' => $song10Mb,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $song4Mb = UploadedFile::fake()->create('cuatro_mb.mp3', 4 * 1024, 'audio/mpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'mp3',
                'file' => $song4Mb,
            ])
            ->assertOk();
    }

    public function test_existing_files_exceeding_new_basic_limits_are_preserved_and_only_new_uploads_are_blocked(): void
    {
        $talent = $this->createBasicTalent([
            'band_name' => 'Banda Histórica Con 7 Temas',
            'email' => 'historica7@test.com',
        ]);

        // Cuenta que ya tenía 7 canciones subidas con el límite antiguo del modelo
        for ($i = 1; $i <= 7; $i++) {
            TalentMedia::create([
                'talent_id' => $talent->id,
                'type' => 'mp3',
                'title' => "Tema Histórico {$i}",
                'filename' => "historico_{$i}.mp3",
                'disk' => 'r2',
                'backblaze_key' => "talents/1/media/mp3/historico_{$i}.mp3",
                'url' => "https://cdn.sevenrockradio.test/historico_{$i}.mp3",
                'mime_type' => 'audio/mpeg',
                'size' => 5 * 1024 * 1024,
            ]);
        }

        // Al consultar el panel y la biblioteca, los 7 archivos siguen intactos
        $this->actingAs($talent, 'talent')
            ->get(route('talents.media.index'))
            ->assertOk()
            ->assertSee('7 / 5')
            ->assertSee('Tema Histórico 7');

        $this->assertSame(7, $talent->media()->where('type', 'mp3')->count());

        // Las especificaciones reactivas indican quota_full = true y remaining = 0 sin borrar nada
        $specs = MediaController::getMediaTypeSpecs($talent);
        $this->assertSame(5, $specs['types']['mp3']['limit']);
        $this->assertSame(7, $specs['types']['mp3']['used']);
        $this->assertSame(0, $specs['types']['mp3']['remaining']);
        $this->assertTrue($specs['types']['mp3']['quota_full']);

        // Bloquea una nueva subida de canción mientras supere el límite de 5, pero permite subir fotos (0/10)
        $newSong = UploadedFile::fake()->create('intento_8.mp3', 1024, 'audio/mpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'mp3',
                'file' => $newSong,
            ])
            ->assertStatus(422);

        $newPhoto = UploadedFile::fake()->create('foto_permitida.jpg', 512, 'image/jpeg');
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => $newPhoto,
            ])
            ->assertOk();

        // Las 7 canciones históricas siguen intactas
        $this->assertSame(7, $talent->media()->where('type', 'mp3')->count());
        $this->assertSame(1, $talent->media()->where('type', 'photo')->count());
    }

    public function test_public_basic_plan_info_page_and_interfaces_match_server_restrictions_and_manual_inquiry_works(): void
    {
        Mail::fake();

        // 1. Página informativa pública del Plan Básico (simplificada)
        $page = $this->get(route('talents.plans.basic'));
        $page->assertOk()
            ->assertSee('Tu banda en el Muro del Rock')
            ->assertSee('Reúne tu música, biografía, fotografías y redes en una ficha pública fácil de compartir.')
            ->assertSee('3,50 €/mes')
            ->assertSee('5 canciones.')
            ->assertSee('10 fotografías.')
            ->assertSee('200 MB de almacenamiento.')
            ->assertSee('Biografía, redes y enlaces a YouTube.')
            ->assertSee('Pagar un mes de Básico con PayPal')
            ->assertSee('Solicitar información')
            ->assertSee('El abono mediante PayPal es un')
            ->assertSee('pago único de un mes (3,50 €)')
            ->assertSee('El plan no garantiza emisión en radio.')
            ->assertSee('Formatos y tamaños de archivo')
            ->assertSee('Cómo funciona la contratación')
            ->assertSee('Publicación web y programación de radio')
            ->assertSee(route('privacy-policy'))
            ->assertSee(route('copyright-policy'));

        // 2. Formulario de registro y selector de planes muestran las mismas prestaciones
        $this->get(route('talents.register'))
            ->assertOk()
            ->assertSee('3,50 €/mes')
            ->assertSee('Hasta 5 canciones MP3 (máx. 15 MB c/u)')
            ->assertSee('Hasta 10 fotografías (máx. 10 MB c/u)')
            ->assertSee('200 MB de almacenamiento total');

        $talent = $this->createBasicTalent();
        $this->actingAs($talent, 'talent')
            ->get(route('talents.subscriptions.plans'))
            ->assertOk()
            ->assertSee('3,50 €/mes')
            ->assertSee('Hasta 5 canciones MP3 (máx. 15 MB c/u)')
            ->assertSee('Hasta 10 fotografías (máx. 10 MB c/u)')
            ->assertSee('200 MB de almacenamiento total');

        $this->actingAs($talent, 'talent')
            ->get(route('talents.dashboard'))
            ->assertOk()
            ->assertSee('0/10')
            ->assertSee('0/5')
            ->assertSee('0.00 / 200 MB')
            ->assertSee('máx. 15 MB c/u')
            ->assertSee('máx. 10 MB c/u');

        // 3. Envío de solicitud de información desde la página pública funciona sin cobro automático
        $this->from(route('talents.plans.basic'))
            ->post(route('contact.send'), [
                'name' => 'Carlos Guitarrista',
                'band_name' => 'Riff del Sur',
                'email' => 'riffdelsur@example.com',
                'phone' => '+34 600 111 222',
                'subject' => 'general',
                'message' => 'Hola, queremos solicitar la activación manual del Plan Básico (3,50 €/mes).',
            ])
            ->assertRedirect(route('talents.plans.basic'))
            ->assertSessionHas('success');
    }
}
