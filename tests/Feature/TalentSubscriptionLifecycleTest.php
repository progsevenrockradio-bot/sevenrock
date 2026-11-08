<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Talent;
use App\Models\TalentMedia;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Services\TalentSubscriptionService;
use App\Support\TalentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TalentSubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createTalent(array $attributes = []): Talent
    {
        return Talent::create(array_merge([
            'band_name'           => 'Test Band',
            'email'               => 'band@test.com',
            'password'            => Hash::make('Secret123!'),
            'subscription_status' => 'pending',
            'plan'                => 'basic',
            'is_hidden'           => true,
        ], $attributes));
    }

    /**
     * 1. Primer periodo: empieza hoy y termina exactamente un mes después sin desbordamiento.
     */
    public function test_first_activation_starts_today_and_ends_one_month_later_without_overflow(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 10, 0, 0));

        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent(['plan' => 'basic']);

        $service = app(TalentSubscriptionService::class);
        $subscription = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'REC-001'
        );

        $this->assertEquals('2026-11-05', $subscription->start_date->toDateString());
        $this->assertEquals('2026-12-05', $subscription->end_date->toDateString());
        $this->assertEquals($admin->id, $subscription->confirmed_by_user_id);
        $this->assertEquals('REC-001', $subscription->payment_reference);
        $this->assertNotNull($subscription->confirmed_at);

        $talent->refresh();
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertFalse((bool) $talent->is_hidden);
        $this->assertNull($talent->expires_grace_at);

        Carbon::setTestNow();
    }

    /**
     * 2. Renovación anticipada: si sigue vigente, añade un mes a la fecha de vencimiento actual sin perder días pagados.
     */
    public function test_advance_renewal_adds_one_month_to_current_end_date_without_losing_paid_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 10, 0, 0));

        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent([
            'plan' => 'pro',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        // Suscripción vigente que vence el 2026-12-05 (faltan 15 días)
        $talent->subscriptions()->create([
            'plan' => 'pro',
            'amount' => 7.00,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => Carbon::create(2026, 11, 5),
            'end_date' => Carbon::create(2026, 12, 5),
            'status' => 'active',
        ]);

        $service = app(TalentSubscriptionService::class);
        $subscription = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'REC-002'
        );

        // Debe sumar un mes a 2026-12-05 -> 2027-01-05 (conservando los 15 días ya pagados)
        $this->assertEquals('2026-11-05', $subscription->start_date->toDateString());
        $this->assertEquals('2027-01-05', $subscription->end_date->toDateString());

        Carbon::setTestNow();
    }

    /**
     * 3. Renovación tras vencimiento: inicia el nuevo período hoy y añade un mes.
     */
    public function test_expired_renewal_starts_today_and_ends_one_month_later(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 10, 10, 0, 0));

        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent([
            'plan' => 'basic',
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        // Suscripción vencida hace 5 días (venció el 2026-12-05)
        $talent->subscriptions()->create([
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => Carbon::create(2026, 11, 5),
            'end_date' => Carbon::create(2026, 12, 5),
            'status' => 'expired',
        ]);

        $service = app(TalentSubscriptionService::class);
        $subscription = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'REC-003'
        );

        $this->assertEquals('2026-12-10', $subscription->start_date->toDateString());
        $this->assertEquals('2027-01-10', $subscription->end_date->toDateString());

        $talent->refresh();
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertFalse((bool) $talent->is_hidden);

        Carbon::setTestNow();
    }

    /**
     * 4. Manejo de fin de mes y año bisiesto sin desbordamiento (no salta de enero a marzo).
     */
    public function test_calendar_month_end_of_month_and_leap_year_no_overflow(): void
    {
        $service = app(TalentSubscriptionService::class);

        // Caso A: 31 de enero de año no bisiesto (2025) -> debe terminar el 28 de febrero de 2025 (NO el 2 o 3 de marzo)
        $dates2025 = $service->calculateDatesFrom(
            currentEndDate: Carbon::create(2025, 1, 31),
            isCurrentlyActive: true,
            baseDate: Carbon::create(2025, 1, 20)
        );
        $this->assertEquals('2025-01-31', $dates2025['start_date']->toDateString());
        $this->assertEquals('2025-02-28', $dates2025['end_date']->toDateString());

        // Caso B: 31 de enero de año bisiesto (2024) -> debe terminar el 29 de febrero de 2024
        $dates2024 = $service->calculateDatesFrom(
            currentEndDate: Carbon::create(2024, 1, 31),
            isCurrentlyActive: true,
            baseDate: Carbon::create(2024, 1, 20)
        );
        $this->assertEquals('2024-01-31', $dates2024['start_date']->toDateString());
        $this->assertEquals('2024-02-29', $dates2024['end_date']->toDateString());

        // Caso C: 31 de marzo -> 30 de abril
        $datesApril = $service->calculateDatesFrom(
            currentEndDate: Carbon::create(2025, 3, 31),
            isCurrentlyActive: true,
            baseDate: Carbon::create(2025, 3, 15)
        );
        $this->assertEquals('2025-03-31', $datesApril['start_date']->toDateString());
        $this->assertEquals('2025-04-30', $datesApril['end_date']->toDateString());

        // Caso D: 31 de mayo -> 30 de junio
        $datesJune = $service->calculateDatesFrom(
            currentEndDate: Carbon::create(2025, 5, 31),
            isCurrentlyActive: true,
            baseDate: Carbon::create(2025, 5, 10)
        );
        $this->assertEquals('2025-05-31', $datesJune['start_date']->toDateString());
        $this->assertEquals('2025-06-30', $datesJune['end_date']->toDateString());
    }

    /**
     * 5. Límite exacto de vencimiento (end_date inclusive):
     * Durante el día de end_date el servicio sigue incluido. Expira al día siguiente.
     */
    public function test_exact_expiration_boundary_inclusive_for_basic_and_pro(): void
    {
        Mail::fake();

        $talent = $this->createTalent([
            'band_name' => 'Banda Límite',
            'plan' => 'basic',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);

        $talent->subscriptions()->create([
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => Carbon::create(2026, 10, 5),
            'end_date' => Carbon::create(2026, 11, 5),
            'status' => 'active',
        ]);

        // A) En el propio día del vencimiento (2026-11-05 a las 02:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 2, 0, 0));
        $this->artisan('talents:expire')->assertSuccessful();

        $talent->refresh();
        $this->assertFalse((bool) $talent->is_hidden, 'El día de end_date el servicio debe permanecer ACTIVO (inclusive).');
        $this->assertEquals('active', $talent->subscription_status);

        // B) Al día siguiente del vencimiento (2026-11-06 a las 02:00 AM)
        Carbon::setTestNow(Carbon::create(2026, 11, 6, 2, 0, 0));
        $this->artisan('talents:expire')->assertSuccessful();

        $talent->refresh();
        $this->assertTrue((bool) $talent->is_hidden, 'Al día siguiente de end_date el perfil pasa a OCULTO.');
        $this->assertEquals('expired', $talent->subscription_status);
        $this->assertNull($talent->expires_grace_at, 'Básico y Pro no tienen fecha de purga en expires_grace_at.');

        Carbon::setTestNow();
    }

    /**
     * 6. Protección contra doble clic e idempotencia:
     * La misma confirmación de pago no puede extender dos veces la suscripción.
     */
    public function test_idempotency_token_and_double_click_protection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent(['plan' => 'pro']);

        $service = app(TalentSubscriptionService::class);
        $token = 'idempotency-token-test-123';

        // Primer clic / envío: exitoso
        $sub1 = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'REC-DUP-1',
            idempotencyToken: $token
        );
        $this->assertNotNull($sub1);
        $this->assertEquals(1, $talent->subscriptions()->count());

        // Segundo clic con el mismo token: devuelve el mismo registro sin duplicar ni extender
        $sub2 = $service->confirmPaymentRenewal(
            talent: $talent,
            admin: $admin,
            paymentReference: 'REC-DUP-1',
            idempotencyToken: $token
        );
        $this->assertEquals($sub1->id, $sub2->id);
        $this->assertEquals(1, $talent->subscriptions()->count(), 'No debe crearse una segunda suscripción.');
        $this->assertEquals($sub1->end_date->toDateString(), $sub2->end_date->toDateString());
    }

    /**
     * 7. Desacoplamiento de edición de datos:
     * Modificar la biografía o datos de la banda no debe renovar ni alterar fechas.
     */
    public function test_profile_editing_does_not_modify_dates_or_confirm_payment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent([
            'plan' => 'basic',
            'subscription_status' => 'active',
            'is_hidden' => false,
            'bio' => 'Biografía original',
        ]);

        $sub = $talent->subscriptions()->create([
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => Carbon::create(2026, 11, 1),
            'end_date' => Carbon::create(2026, 12, 1),
            'status' => 'active',
        ]);

        // Simular PUT desde el panel de administración
        $response = $this->actingAs($admin)->put(route('admin.talents.update', $talent), [
            'band_name' => 'Banda Modificada',
            'bio' => 'Nueva biografía editada',
            'plan' => 'basic',
            'subscription_status' => 'active',
        ]);

        $response->assertRedirect(route('admin.talents.index'));

        $sub->refresh();
        $this->assertEquals('2026-11-01', $sub->start_date->toDateString(), 'start_date no debe cambiar al editar el perfil.');
        $this->assertEquals('2026-12-01', $sub->end_date->toDateString(), 'end_date no debe cambiar al editar el perfil.');
        $this->assertNull($sub->confirmed_at, 'No debe confirmarse pago implícito al editar el perfil.');

        $talent->refresh();
        $this->assertEquals('Nueva biografía editada', $talent->bio);
        $this->assertEquals('Banda Modificada', $talent->band_name);
    }

    /**
     * 8. Preservación absoluta de archivos en Básico y Pro:
     * Tras vencer y pasar los 15 días, el material de Básico y Pro NUNCA se borra.
     */
    public function test_basic_and_pro_files_are_never_purged_after_15_days(): void
    {
        Mail::fake();
        Storage::fake('r2');

        $talent = $this->createTalent([
            'band_name' => 'Banda Pro Segura',
            'plan' => 'pro',
            'subscription_status' => 'expired',
            'is_hidden' => true,
            'expires_grace_at' => null,
        ]);

        $media = TalentMedia::create([
            'talent_id' => $talent->id,
            'type' => 'mp3',
            'title' => 'Canción Inmortal',
            'filename' => 'cancion_inmortal.mp3',
            'disk' => 'r2',
            'url' => 'https://example.com/cancion.mp3',
            'path' => 'talents/media/cancion.mp3',
            'backblaze_key' => 'cancion.mp3',
            'mime_type' => 'audio/mpeg',
            'size' => 2048,
        ]);

        // Simular que venció hace 30 días
        $talent->subscriptions()->create([
            'plan' => 'pro',
            'amount' => 7.00,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(60),
            'end_date' => today()->subDays(30),
            'status' => 'expired',
        ]);

        $this->artisan('talents:expire')->assertSuccessful();

        // El medio de la banda Pro sigue existiendo intacto en base de datos
        $this->assertDatabaseHas('talent_media', [
            'id' => $media->id,
            'talent_id' => $talent->id,
        ]);

        $talent->refresh();
        $this->assertTrue((bool) $talent->is_hidden);
        $this->assertEquals('expired', $talent->subscription_status);
    }

    /**
     * 9. Bloqueo de nuevas subidas en cuentas vencidas u ocultas manteniendo el acceso al panel.
     */
    public function test_upload_blocked_when_subscription_is_expired_or_hidden_dashboard_accessible(): void
    {
        Storage::fake('r2');

        $talent = $this->createTalent([
            'band_name' => 'Banda Pausada',
            'plan' => 'basic',
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        // La banda puede acceder a su dashboard
        $response = $this->actingAs($talent, 'talent')->get(route('talents.dashboard'));
        $response->assertOk();
        $response->assertSee('Banda Pausada');

        // Pero si intenta subir una canción nueva, es rechazada
        $file = UploadedFile::fake()->create('nueva.mp3', 2000, 'audio/mpeg');

        $uploadResponse = $this->actingAs($talent, 'talent')->post(route('talents.media.upload'), [
            'type' => 'mp3',
            'title' => 'Intento en cuenta pausada',
            'file' => $file,
        ]);

        $uploadResponse->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('talent_media', [
            'talent_id' => $talent->id,
            'title' => 'Intento en cuenta pausada',
        ]);
    }

    /**
     * 10. Acción web del administrador: «Confirmar pago y renovar».
     */
    public function test_admin_can_confirm_payment_and_renewal_via_web_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $talent = $this->createTalent([
            'plan' => 'basic',
            'subscription_status' => 'expired',
            'is_hidden' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.talents.renew', $talent), [
            'plan' => 'basic',
            'payment_reference' => 'TRANSF-ES12345678',
            'notes' => 'Abono verificado en cuenta',
            'idempotency_token' => 'web-token-' . uniqid(),
        ]);

        $response->assertRedirect(route('admin.talents.edit', $talent));
        $response->assertSessionHas('status');

        $talent->refresh();
        $this->assertEquals('active', $talent->subscription_status);
        $this->assertFalse((bool) $talent->is_hidden);

        $this->assertDatabaseHas('talent_subscriptions', [
            'talent_id' => $talent->id,
            'plan' => 'basic',
            'amount' => 3.50,
            'payment_reference' => 'TRANSF-ES12345678',
            'confirmed_by_user_id' => $admin->id,
            'status' => 'active',
        ]);
    }

    /**
     * 11. Las cuotas y reglas de Gratuito, Pro y Básico se corresponden exactamente con las aprobadas.
     */
    public function test_plan_limits_and_pricing_correspond_to_approved_specifications(): void
    {
        $basic = TalentPlan::definition('basic');
        $this->assertEquals(3.50, $basic['price']);
        $this->assertEquals(5, $basic['limits']['songs']);
        $this->assertEquals(10, $basic['limits']['photos']);
        $this->assertEquals(0, $basic['limits']['documents']);
        $this->assertEquals(0, $basic['limits']['videos']);
        $this->assertEquals(200, $basic['limits']['storage_mb']);

        $pro = TalentPlan::definition('pro');
        $this->assertEquals(7.00, $pro['price']);
        $this->assertEquals(15, $pro['limits']['songs']);
        $this->assertEquals(50, $pro['limits']['photos']);
        $this->assertEquals(10, $pro['limits']['documents']);
        $this->assertEquals(3, $pro['limits']['videos']);
        $this->assertEquals(400, $pro['limits']['storage_mb']);

        $free = TalentPlan::definition('free');
        $this->assertEquals(0.00, $free['price']);
        $this->assertEquals(2, $free['limits']['songs']);
        $this->assertEquals(1, $free['limits']['photos']);
        $this->assertEquals(50, $free['limits']['storage_mb']);
    }

    /**
     * 12. Avisos de vencimiento:
     * Básico y Pro reciben avisos previos de pausa (-7 y -1 día), pero NUNCA el aviso de purga (-3 días de gracia).
     * El plan Gratuito sí recibe el aviso de purga final.
     */
    public function test_expiry_warnings_sent_before_expiry_but_never_purge_warning_for_paid_plans(): void
    {
        Mail::fake();

        // A) Banda Básico que vence en 7 días
        $basicTalent = $this->createTalent([
            'band_name' => 'Banda Aviso 7 Dias',
            'email' => 'aviso7@test.com',
            'plan' => 'basic',
            'subscription_status' => 'active',
            'is_hidden' => false,
        ]);
        $basicTalent->subscriptions()->create([
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(23),
            'end_date' => today()->addDays(7),
            'status' => 'active',
        ]);

        // B) Banda Pro expirada hace 12 días (a 3 días de los 15 días hipotéticos)
        $proTalent = $this->createTalent([
            'band_name' => 'Banda Pro Pausada',
            'email' => 'propausada@test.com',
            'plan' => 'pro',
            'subscription_status' => 'expired',
            'is_hidden' => true,
            'expires_grace_at' => today()->addDays(3),
        ]);
        $proTalent->subscriptions()->create([
            'plan' => 'pro',
            'amount' => 7.00,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(42),
            'end_date' => today()->subDays(12),
            'status' => 'expired',
        ]);

        // C) Banda Gratuita en gracia que purgará en 3 días
        $freeTalent = $this->createTalent([
            'band_name' => 'Banda Free Purgable',
            'email' => 'freepurge@test.com',
            'plan' => 'free',
            'subscription_status' => 'expired',
            'is_hidden' => true,
            'expires_grace_at' => today()->addDays(3),
        ]);
        $freeTalent->subscriptions()->create([
            'plan' => 'free',
            'amount' => 0,
            'currency' => 'EUR',
            'payment_provider' => 'manual',
            'start_date' => today()->subDays(57),
            'end_date' => today()->subDays(12),
            'status' => 'expired',
        ]);

        // Ejecutar aviso de 7 días antes del vencimiento
        $this->artisan('talents:send-expiry-warnings', ['--days' => 7])->assertSuccessful();

        Mail::assertSent(\App\Mail\TalentExpiryWarningMail::class, function ($mail) use ($basicTalent) {
            return $mail->hasTo($basicTalent->email);
        });

        // Ejecutar aviso de 3 días antes de la purga
        $this->artisan('talents:send-expiry-warnings', ['--days' => 3])->assertSuccessful();

        // La banda Pro NUNCA debe recibir aviso de purga
        Mail::assertNotSent(\App\Mail\TalentExpiryWarningMail::class, function ($mail) use ($proTalent) {
            return $mail->hasTo($proTalent->email);
        });

        // La banda Free SÍ debe recibir el aviso de purga final
        Mail::assertSent(\App\Mail\TalentExpiryWarningMail::class, function ($mail) use ($freeTalent) {
            return $mail->hasTo($freeTalent->email);
        });
    }
}
