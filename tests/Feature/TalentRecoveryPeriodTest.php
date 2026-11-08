<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\TalentExpiryWarningMail;
use App\Mail\TalentHiddenNotificationMail;
use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Models\TalentSubscriptionReminder;
use App\Models\User;
use App\Services\TalentSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TalentRecoveryPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidTalent(string $plan = 'basic', array $attributes = []): Talent
    {
        return Talent::create(array_merge([
            'band_name'           => 'Rockers ' . uniqid(),
            'email'               => 'banda_' . uniqid() . '@sevenrock.test',
            'password'            => Hash::make('Secret123!'),
            'subscription_status' => 'active',
            'plan'                => $plan,
            'is_hidden'           => false,
        ], $attributes));
    }

    private function attachSubscription(Talent $talent, string $endDate, string $status = 'active'): TalentSubscription
    {
        return $talent->subscriptions()->create([
            'plan'             => $talent->plan,
            'amount'           => $talent->plan === 'pro' ? 7.00 : 3.50,
            'currency'         => 'EUR',
            'payment_provider' => 'paypal',
            'payment_reference'=> 'PP-' . uniqid(),
            'start_date'       => Carbon::parse($endDate)->subMonthNoOverflow(),
            'end_date'         => Carbon::parse($endDate),
            'status'           => $status,
            'confirmed_at'     => now(),
        ]);
    }

    /**
     * 1. Al terminar el último día pagado, el perfil se oculta, se bloquean cargas
     * y se calcula la fecha explícita del período de recuperación (30 días).
     */
    public function test_paid_talent_expires_on_day_after_end_date_and_calculates_30_day_recovery_date(): void
    {
        Mail::fake();

        $talent = $this->createPaidTalent('basic');
        $this->attachSubscription($talent, '2026-11-05');

        // A) En el propio día pagado (2026-11-05 inclusive): sigue activo y visible
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 14, 0, 0));
        $this->artisan('talents:expire')->assertSuccessful();
        $talent->refresh();
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertSame('active', $talent->subscription_status);

        // B) Al día siguiente (2026-11-06 a las 02:00 AM): el servicio ha terminado
        Carbon::setTestNow(Carbon::create(2026, 11, 6, 2, 0, 0));
        $this->artisan('talents:expire')->assertSuccessful();
        $talent->refresh();

        $this->assertTrue((bool) $talent->is_hidden, 'El perfil pasa a estar oculto.');
        $this->assertSame('expired', $talent->subscription_status);

        // Fecha explícita de fin de recuperación: 30 días después del 2026-11-05
        $recoveryEnd = $talent->recoveryPeriodEndsAt();
        $this->assertNotNull($recoveryEnd);
        $this->assertSame('2026-12-05', $recoveryEnd->toDateString());
        $this->assertTrue($talent->isInRecoveryPeriod());

        Carbon::setTestNow();
    }

    /**
     * 2. Bloqueo de nuevas cargas al estar pausado/vencido, pero acceso al panel y descarga de archivos.
     */
    public function test_upload_blocked_when_expired_but_dashboard_and_download_accessible(): void
    {
        Storage::fake('r2');

        $talent = $this->createPaidTalent('pro', [
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);
        $this->attachSubscription($talent, '2026-11-01', 'expired');

        // Subir archivo existente para verificar descarga
        $fileContent = 'Fake MP3 Content';
        Storage::disk('r2')->put('talents/cancion_test.mp3', $fileContent);
        $media = TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'title' => 'Canción de Prueba',
            'filename' => 'cancion_test.mp3',
            'disk' => 'r2',
            'url' => 'https://example.com/cancion.mp3',
            'path' => 'talents/cancion_test.mp3',
            'backblaze_key' => 'talents/cancion_test.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => strlen($fileContent),
            'status' => 'approved',
        ]);

        // A) Intentar subir nuevo archivo debe ser rechazado
        $photo = UploadedFile::fake()->image('nueva_foto.jpg', 400, 400);
        $this->actingAs($talent, 'talent')
            ->postJson(route('talents.media.upload'), [
                'type' => 'photo',
                'file' => $photo,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // B) Acceso al dashboard y sección de medios se mantiene disponible
        $this->actingAs($talent, 'talent')
            ->get(route('talents.dashboard'))
            ->assertOk()
            ->assertSee('Perfil pausado en el Muro del Rock')
            ->assertSee('Período de recuperación (30 días)')
            ->assertSee('01/12/2026') // 2026-11-01 + 30 días en formato d/m/Y
            ->assertSee('Descargar mis archivos')
            ->assertSee('Renovar suscripción');

        $this->actingAs($talent, 'talent')
            ->get(route('talents.media.index'))
            ->assertOk()
            ->assertSee('Subida de archivos pausada')
            ->assertSee('Período de recuperación activo');

        // C) Descargar archivo existente funciona perfectamente
        $response = $this->actingAs($talent, 'talent')
            ->get(route('talents.media.download', $media->id));

        $response->assertOk();
    }

    /**
     * 3. Aviso 1 (Al vencer): envío de notificación inicial con fecha límite de recuperación e instrucciones.
     */
    public function test_notice_1_sent_at_expiry_with_recovery_deadline_and_no_duplicates(): void
    {
        Mail::fake();

        $talent = $this->createPaidTalent('basic');
        $this->attachSubscription($talent, '2026-11-01');

        Carbon::setTestNow(Carbon::create(2026, 11, 2, 2, 0, 0));

        $this->artisan('talents:expire')->assertSuccessful();

        // Verifica envío de TalentHiddenNotificationMail
        Mail::assertSent(TalentHiddenNotificationMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email)
                && str_contains($mail->envelope()->subject, 'período de recuperación iniciado')
                && $mail->recoveryEndDate?->toDateString() === '2026-12-01';
        });

        // Verifica registro en base de datos
        $this->assertTrue(TalentSubscriptionReminder::query()
            ->where('talent_id', $talent->id)
            ->where('stage', TalentSubscriptionReminder::STAGE_AT_EXPIRY)
            ->whereDate('cycle_end_date', '2026-11-01')
            ->exists());

        // Reejecución no duplica el aviso
        Mail::fake();
        $this->artisan('talents:expire')->assertSuccessful();
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    /**
     * 4. Aviso 2 (7 días antes del fin de recuperación) y Aviso 3 (1 día antes del fin de recuperación).
     */
    public function test_notice_2_and_3_sent_at_7_and_1_days_before_recovery_ends(): void
    {
        Mail::fake();

        // Banda Básico vencida el 2026-11-01. Fin de recuperación (30 días): 2026-12-01.
        $talent = $this->createPaidTalent('basic', [
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);
        $this->attachSubscription($talent, '2026-11-01', 'expired');

        // A) A falta de 7 días para el 2026-12-01 -> Hoy es 2026-11-24
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 8, 0, 0));

        $this->artisan('talents:send-expiry-warnings', ['--days' => 7])->assertSuccessful();

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email)
                && $mail->stage === TalentSubscriptionReminder::STAGE_RECOVERY_7_DAYS_BEFORE
                && $mail->daysLeft === 7
                && str_contains($mail->envelope()->subject, 'finaliza en 7 días');
        });

        $this->assertTrue(TalentSubscriptionReminder::query()
            ->where('talent_id', $talent->id)
            ->where('stage', TalentSubscriptionReminder::STAGE_RECOVERY_7_DAYS_BEFORE)
            ->whereDate('cycle_end_date', '2026-11-01')
            ->exists());

        // Reejecución en el mismo día no duplica
        Mail::fake();
        $this->artisan('talents:send-expiry-warnings', ['--days' => 7])->assertSuccessful();
        Mail::assertNothingSent();

        // B) A falta de 1 día para el 2026-12-01 -> Hoy es 2026-11-30
        Carbon::setTestNow(Carbon::create(2026, 11, 30, 8, 0, 0));

        $this->artisan('talents:send-expiry-warnings', ['--days' => 1])->assertSuccessful();

        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email)
                && $mail->stage === TalentSubscriptionReminder::STAGE_RECOVERY_1_DAY_BEFORE
                && $mail->daysLeft === 1
                && str_contains($mail->envelope()->subject, 'finaliza mañana');
        });

        $this->assertTrue(TalentSubscriptionReminder::query()
            ->where('talent_id', $talent->id)
            ->where('stage', TalentSubscriptionReminder::STAGE_RECOVERY_1_DAY_BEFORE)
            ->whereDate('cycle_end_date', '2026-11-01')
            ->exists());

        Carbon::setTestNow();
    }

    /**
     * 5. Renovación durante el período de recuperación reactiva la cuenta y cancela avisos pendientes del ciclo anterior.
     */
    public function test_renewal_during_recovery_reactivates_account_and_cancels_pending_reminders(): void
    {
        Mail::fake();

        // Venció el 2026-11-01. Fin recuperación: 2026-12-01.
        $talent = $this->createPaidTalent('pro', [
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);
        $this->attachSubscription($talent, '2026-11-01', 'expired');

        // El día 2026-11-15 (día 14 de recuperación), el administrador confirma renovación
        Carbon::setTestNow(Carbon::create(2026, 11, 15, 10, 0, 0));
        $admin = User::factory()->create(['is_admin' => true]);

        $service = app(TalentSubscriptionService::class);
        $newSub = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'PP-RENOV-RECOVERY'
        );

        $talent->refresh();

        // Cuenta reactivada
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertSame('active', $talent->subscription_status);
        $this->assertSame('2026-11-15', $newSub->start_date->toDateString());
        $this->assertSame('2026-12-15', $newSub->end_date->toDateString());

        // Ahora avanzamos al 2026-11-24 (día en que tocaba el aviso de 7 días del ciclo anterior)
        Carbon::setTestNow(Carbon::create(2026, 11, 24, 8, 0, 0));
        $this->artisan('talents:send-expiry-warnings', ['--days' => 7])->assertSuccessful();

        // NO debe enviarse ningún aviso de recuperación para la cuenta renovada
        Mail::assertNotSent(TalentExpiryWarningMail::class, function ($mail) use ($talent) {
            return $mail->hasTo($talent->email);
        });

        Carbon::setTestNow();
    }

    /**
     * 6. Conservación de archivos después de superar los 30 días:
     * Tras cumplirse los 30 días, NO se ejecuta ninguna purga para Básico y Pro.
     */
    public function test_basic_and_pro_files_are_never_purged_even_after_30_days_passed(): void
    {
        Mail::fake();
        Storage::fake('r2');

        $talent = $this->createPaidTalent('basic', [
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        // Venció hace 45 días
        $this->attachSubscription($talent, today()->subDays(45)->toDateString(), 'expired');

        $fileContent = 'Rock MP3 Data';
        Storage::disk('r2')->put('talents/cancion_45d.mp3', $fileContent);
        $media = TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'title' => 'Canción 45 Días',
            'filename' => 'cancion_45d.mp3',
            'disk' => 'r2',
            'url' => 'https://example.com/cancion_45d.mp3',
            'path' => 'talents/cancion_45d.mp3',
            'backblaze_key' => 'talents/cancion_45d.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => strlen($fileContent),
            'status' => 'approved',
        ]);

        // Ejecutar proceso de expiración y purga
        $this->artisan('talents:expire')->assertSuccessful();

        $talent->refresh();
        $this->assertSame('expired', $talent->subscription_status);
        $this->assertTrue((bool) $talent->is_hidden);

        // El medio continúa intacto en base de datos y disco
        $this->assertDatabaseHas('talent_media', [
            'id' => $media->id,
            'talent_id' => $talent->id,
        ]);
        Storage::disk('r2')->assertExists('talents/cancion_45d.mp3');
    }

    /**
     * 7. Las cuentas Free conservan sus reglas originales (45 días + 15 de gracia con purga a los 3 días).
     */
    public function test_free_plan_maintains_its_original_rules_unaltered(): void
    {
        Mail::fake();

        $freeTalent = Talent::create([
            'band_name'           => 'Free Band',
            'email'               => 'free@sevenrock.test',
            'password'            => Hash::make('Secret123!'),
            'subscription_status' => 'expired',
            'plan'                => 'free',
            'is_hidden'           => true,
            'expires_grace_at'    => today()->addDays(3),
        ]);
        $freeTalent->subscriptions()->create([
            'plan' => 'free',
            'amount' => 0.00,
            'currency' => 'EUR',
            'payment_provider' => 'free',
            'start_date' => today()->subDays(45),
            'end_date' => today()->subDays(12),
            'status' => 'expired',
        ]);

        $this->artisan('talents:send-expiry-warnings', ['--days' => 3])->assertSuccessful();

        // La cuenta Free SÍ recibe el aviso previo a la purga de 15 días
        Mail::assertSent(TalentExpiryWarningMail::class, function ($mail) use ($freeTalent) {
            return $mail->hasTo($freeTalent->email)
                && $mail->stage === TalentSubscriptionReminder::STAGE_3_DAYS_BEFORE_PURGE;
        });
    }
}
