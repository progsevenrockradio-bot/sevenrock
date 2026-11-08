<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ContactMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Services\FileUploadService;
use App\Support\TalentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class ProPlanUnificationTest extends TestCase
{
    use RefreshDatabase;

    private const MB = 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();

        // Almacenamiento aislado: ningún archivo llega a R2 ni a disco real.
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

    private function talent(string $plan = 'pro', array $overrides = []): Talent
    {
        return Talent::create(array_merge([
            'band_name' => 'Banda ' . $plan,
            'email' => $plan . '@plan.test',
            'password' => Hash::make('Secret123!'),
            'plan' => $plan,
            'subscription_status' => 'active',
            'is_hidden' => false,
        ], $overrides));
    }

    private function seedMedia(Talent $talent, string $type, int $count, int $sizeBytes = 1024): void
    {
        for ($i = 1; $i <= $count; $i++) {
            TalentMedia::create([
                'talent_id' => $talent->id,
                'type' => $type,
                'filename' => "{$type}_{$i}",
                'disk' => 'r2',
                'backblaze_key' => "talents/{$talent->id}/media/{$type}/{$type}_{$i}",
                'url' => "https://cdn.sevenrockradio.test/{$type}_{$i}",
                'mime_type' => 'application/octet-stream',
                'size' => $sizeBytes,
            ]);
        }
    }

    private function upload(Talent $talent, string $type, UploadedFile $file)
    {
        return $this->actingAs($talent, 'talent')->postJson(route('talents.media.upload'), [
            'type' => $type,
            'file' => $file,
        ]);
    }

    public function test_pro_definition_and_model_share_one_source_and_other_plans_are_unchanged(): void
    {
        $pro = TalentPlan::definition('pro');
        $this->assertSame(7.00, $pro['price']);
        $this->assertSame('7 €/mes', $pro['monthly_label']);
        $this->assertSame(['photos' => 50, 'songs' => 15, 'documents' => 10, 'videos' => 3, 'storage_mb' => 400], $pro['limits']);
        $this->assertSame(['songs' => 20, 'photos' => 10, 'documents' => 10, 'videos' => 50], $pro['max_file_size_mb']);

        $proTalent = $this->talent('pro');
        $this->assertSame($pro['limits'], $proTalent->planLimits());
        $this->assertSame(20 * 1024, $proTalent->maxFileSizeKb('mp3'));
        $this->assertSame(10 * 1024, $proTalent->maxFileSizeKb('photo'));
        $this->assertSame(10 * 1024, $proTalent->maxFileSizeKb('document'));
        $this->assertSame(50 * 1024, $proTalent->maxFileSizeKb('video'));

        $this->assertStringNotContainsString('stadísticas', json_encode($pro, JSON_UNESCAPED_UNICODE));

        // Gratuito (definición aprobada: 2 canciones, 1 foto, 50MB), Básico y Premium.
        $free = $this->talent('free');
        $this->assertSame(['photos' => 1, 'songs' => 2, 'documents' => 0, 'videos' => 0, 'storage_mb' => 50], $free->planLimits());
        $this->assertSame(12 * 1024, $free->maxFileSizeKb('mp3'));
        $this->assertSame(100 * 1024, $free->maxFileSizeKb('video'));

        $basic = $this->talent('basic');
        $this->assertSame(['photos' => 10, 'songs' => 5, 'documents' => 0, 'videos' => 0, 'storage_mb' => 200], $basic->planLimits());
        $this->assertSame(15 * 1024, $basic->maxFileSizeKb('mp3'));
        $this->assertSame(100 * 1024, $basic->maxFileSizeKb('video'));

        $premium = $this->talent('premium');
        $this->assertSame(['photos' => 999, 'songs' => 50, 'documents' => 999, 'videos' => 999, 'storage_mb' => 20480], $premium->planLimits());
        $this->assertSame(25 * 1024, $premium->maxFileSizeKb('mp3'));
        $this->assertSame(100 * 1024, $premium->maxFileSizeKb('video'));

        $this->assertSame(3.50, TalentPlan::definition('basic')['price']);
        $this->assertSame(200, TalentPlan::definition('basic')['limits']['storage_mb']);
    }

    public function test_server_enforces_quantity_limits_for_each_type(): void
    {
        $talent = $this->talent();

        $this->seedMedia($talent, 'mp3', 15);
        $this->upload($talent, 'mp3', UploadedFile::fake()->create('extra.mp3', 512, 'audio/mpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->seedMedia($talent, 'photo', 50);
        $this->upload($talent, 'photo', UploadedFile::fake()->create('extra.jpg', 100, 'image/jpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->seedMedia($talent, 'document', 10);
        $this->upload($talent, 'document', UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        // Vídeos: se aceptan 3 y se bloquea el 4.º.
        foreach ([1, 2, 3] as $i) {
            $this->upload($talent, 'video', UploadedFile::fake()->create("clip_{$i}.mp4", 1024, 'video/mp4'))->assertOk();
        }
        $this->upload($talent, 'video', UploadedFile::fake()->create('clip_4.mp4', 1024, 'video/mp4'))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->assertSame(3, $talent->media()->where('type', 'video')->count());
        $this->assertSame(15, $talent->media()->where('type', 'mp3')->count());
        $this->assertSame(50, $talent->media()->where('type', 'photo')->count());
        $this->assertSame(10, $talent->media()->where('type', 'document')->count());
    }

    public function test_server_enforces_individual_file_size_limits(): void
    {
        $talent = $this->talent();

        $this->upload($talent, 'mp3', UploadedFile::fake()->create('grande.mp3', 21 * 1024, 'audio/mpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload($talent, 'photo', UploadedFile::fake()->create('grande.jpg', 11 * 1024, 'image/jpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload($talent, 'document', UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload($talent, 'video', UploadedFile::fake()->create('grande.mp4', 51 * 1024, 'video/mp4'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertSame(0, $talent->media()->count());

        // En el límite exacto se acepta cada tipo.
        $this->upload($talent, 'mp3', UploadedFile::fake()->create('limite.mp3', 20 * 1024, 'audio/mpeg'))->assertOk();
        $this->upload($talent, 'photo', UploadedFile::fake()->create('limite.jpg', 10 * 1024, 'image/jpeg'))->assertOk();
        $this->upload($talent, 'document', UploadedFile::fake()->create('limite.pdf', 10 * 1024, 'application/pdf'))->assertOk();
        $this->upload($talent, 'video', UploadedFile::fake()->create('limite.mp4', 50 * 1024, 'video/mp4'))->assertOk();
        $this->assertSame(4, $talent->media()->count());
    }

    public function test_400mb_storage_quota_is_shared_across_all_types(): void
    {
        $talent = $this->talent();

        // 380 MB ya ocupados entre vídeos, fotos y documentos.
        $this->seedMedia($talent, 'video', 1, 100 * self::MB);
        $this->seedMedia($talent, 'photo', 1, 130 * self::MB);
        $this->seedMedia($talent, 'document', 1, 150 * self::MB);

        // Quedan 20 MB: un MP3 de 20 MB entra justo; después, nada más.
        $this->upload($talent, 'mp3', UploadedFile::fake()->create('cabe.mp3', 20 * 1024, 'audio/mpeg'))->assertOk();

        $this->upload($talent, 'photo', UploadedFile::fake()->create('ya_no_cabe.jpg', 2 * 1024, 'image/jpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload($talent, 'video', UploadedFile::fake()->create('ya_no_cabe.mp4', 2 * 1024, 'video/mp4'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertSame(4, $talent->media()->count());
    }

    public function test_existing_files_above_new_limits_are_preserved_and_only_new_uploads_are_blocked(): void
    {
        $talent = $this->talent();

        // Cuenta antigua: 20 canciones, 5 vídeos y 450 MB (supera 15 / 3 / 400 MB).
        $this->seedMedia($talent, 'mp3', 20, 10 * self::MB);
        $this->seedMedia($talent, 'video', 5, 50 * self::MB);

        $before = $talent->media()->count();
        $this->assertSame(25, $before);

        $this->upload($talent, 'mp3', UploadedFile::fake()->create('nueva.mp3', 512, 'audio/mpeg'))
            ->assertStatus(422);
        $this->upload($talent, 'video', UploadedFile::fake()->create('nuevo.mp4', 512, 'video/mp4'))
            ->assertStatus(422);
        // Fotos (0/50) están bajo el cupo, pero la cuota de almacenamiento conjunto sigue superada.
        $this->upload($talent, 'photo', UploadedFile::fake()->create('foto.jpg', 512, 'image/jpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->assertSame($before, $talent->media()->count());
        $this->assertSame(20, $talent->media()->where('type', 'mp3')->count());
        $this->assertSame(5, $talent->media()->where('type', 'video')->count());

        $specs = \App\Http\Controllers\Talent\MediaController::getMediaTypeSpecs($talent);
        $this->assertSame(0, $specs['types']['mp3']['remaining']);
        $this->assertTrue($specs['types']['video']['quota_full']);
        $this->assertSame(0.0, (float) $specs['storage']['remaining_mb']);
    }

    public function test_other_plans_keep_their_server_behaviour(): void
    {
        $basic = $this->talent('basic');
        $this->upload($basic, 'video', UploadedFile::fake()->create('v.mp4', 1024, 'video/mp4'))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
        $this->upload($basic, 'mp3', UploadedFile::fake()->create('grande.mp3', 16 * 1024, 'audio/mpeg'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $premium = $this->talent('premium');
        $this->upload($premium, 'mp3', UploadedFile::fake()->create('ok.mp3', 25 * 1024, 'audio/mpeg'))->assertOk();
        $this->upload($premium, 'video', UploadedFile::fake()->create('v100.mp4', 100 * 1024, 'video/mp4'))->assertOk();
    }

    public function test_pro_page_register_comparison_and_request_form_are_consistent(): void
    {
        Mail::fake();

        $this->get(route('talents.plans.pro'))
            ->assertOk()
            ->assertSee('Tu banda en el Muro del Rock')
            ->assertSee('7 €/mes')
            ->assertSee('15 canciones MP3.')
            ->assertSee('50 fotografías.')
            ->assertSee('10 documentos PDF.')
            ->assertSee('3 vídeos MP4/WebM.')
            ->assertSee('400 MB de almacenamiento compartido.')
            ->assertSee('compartidos entre todos los archivos')
            ->assertSee('El plan no garantiza emisión en radio.')
            ->assertSee('name="plan_interest" value="pro"', false)
            ->assertSee(route('privacy-policy'))
            ->assertSee(route('copyright-policy'))
            ->assertDontSee('stadísticas detalladas')
            ->assertDontSee('Plan Básico');

        $this->get(route('talents.register'))
            ->assertOk()
            ->assertSee('Hasta 15 canciones MP3 (máx. 20 MB c/u)', false)
            ->assertSee('Hasta 3 vídeos MP4/WebM (máx. 50 MB c/u)', false)
            ->assertSee('400 MB de almacenamiento total compartido')
            ->assertSee(route('talents.plans.pro'))
            ->assertDontSee('Estadísticas detalladas');

        // El panel muestra los mismos límites que valida el servidor.
        $talent = $this->talent();
        $this->actingAs($talent, 'talent')
            ->get(route('talents.dashboard'))
            ->assertOk()
            ->assertSee('0 / 400 MB', false)
            ->assertSee('máx. 20 MB c/u')
            ->assertSee('máx. 50 MB c/u');

        // Solicitud válida: queda identificada como Pro aunque se borre el texto sugerido.
        $this->from(route('talents.plans.pro'))
            ->post(route('contact.send'), [
                'name' => 'Ana Bajista',
                'band_name' => 'Los Pro',
                'email' => 'ana@lospro.test',
                'subject' => 'general',
                'plan_interest' => 'pro',
                'message' => 'Quiero información, gracias.',
            ])
            ->assertRedirect(route('talents.plans.pro'))
            ->assertSessionHas('success');

        Mail::assertSent(ContactMail::class, fn (ContactMail $m) => str_contains($m->messageBody, 'SOLICITUD PLAN PRO'));

        // Las validaciones existentes siguen activas.
        $this->from(route('talents.plans.pro'))
            ->post(route('contact.send'), [
                'name' => 'Ana', 'email' => 'no-es-email', 'subject' => 'general', 'plan_interest' => 'pro', 'message' => 'corto',
            ])
            ->assertSessionHasErrors(['email', 'message']);

        // Protección antispam: el campo trampa no envía correo.
        Mail::fake();
        $this->from(route('talents.plans.pro'))
            ->post(route('contact.send'), [
                'name' => 'Bot', 'email' => 'bot@spam.test', 'subject' => 'general', 'plan_interest' => 'pro',
                'message' => 'Mensaje automático de prueba.', 'user_website' => 'http://spam.test',
            ]);
        Mail::assertNothingSent();
    }

    public function test_followup_invitation_still_offers_only_the_basic_plan_and_stays_disabled(): void
    {
        $this->assertFalse((bool) config('muro_outreach.enabled'));
        $this->assertStringContainsString('/muro-del-rock/plan-basico', (string) config('muro_outreach.cta_url'));
    }
}
