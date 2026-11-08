<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DuplicatePaymentOperationException;
use App\Models\Talent;
use App\Models\TalentSubscription;
use App\Models\User;
use App\Services\TalentSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ManualPaymentIdentificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function createBand(string $name, string $plan = 'basic', string $email = null): Talent
    {
        $user = User::factory()->create();

        return Talent::query()->create([
            'user_id' => $user->id,
            'band_name' => $name,
            'email' => $email ?? (strtolower(str_replace(' ', '', $name)) . '@band.test'),
            'password' => 'secret1234',
            'plan' => $plan,
            'subscription_status' => 'pending',
            'is_hidden' => true,
        ]);
    }

    /**
     * 1. Pagador con correo diferente al de la banda:
     * El comprador/pagador puede ser un tercero (representante, familiar) con correo y cuenta PayPal distinta.
     * No se exige coincidencia entre el pagador y la cuenta de la banda.
     */
    public function test_payment_confirmed_when_payer_email_differs_from_band_account(): void
    {
        Carbon::setTestNow('2026-11-06 12:00:00');
        $admin = User::factory()->create(['name' => 'Admin Jose', 'is_admin' => true]);
        $band = $this->createBand('Los Prisioneros Tributo', 'basic', 'banda.oficial@prisioneros.test');

        $service = app(TalentSubscriptionService::class);

        // El representante pagó desde carlos.manager@gmail.com con referencia PayPal 'PP-TX-998877'
        $sub = $service->confirmPaymentRenewal(
            talent: $band,
            admin: $admin,
            paymentReference: 'pp-tx-998877',
            notes: 'Abonado por Carlos Gómez (carlos.manager@gmail.com) vía PayPal. Verificado en cuenta.',
            idempotencyToken: 'token-abc-1'
        );

        $this->assertNotNull($sub);
        $this->assertSame('PP-TX-998877', $sub->payment_reference, 'La referencia se normaliza de forma canónica en mayúsculas.');
        $this->assertSame($admin->id, $sub->confirmed_by_user_id);
        $this->assertSame('paypal', $sub->payment_provider);
        $this->assertSame('active', $sub->status);
        $this->assertSame('2026-12-06', $sub->end_date->toDateString());
        $this->assertSame('active', $band->fresh()->subscription_status);
        $this->assertFalse($band->fresh()->is_hidden);

        Carbon::setTestNow();
    }

    /**
     * 2. Mismo pago repetido con un token nuevo:
     * Impide reutilizar una operación PayPal ya confirmada, aun cuando se genere un token de idempotencia distinto.
     */
    public function test_same_payment_rejected_when_repeated_with_new_idempotency_token(): void
    {
        Carbon::setTestNow('2026-11-06 12:00:00');
        $admin = User::factory()->create(['name' => 'Admin Principal', 'is_admin' => true]);
        $band = $this->createBand('Banda Alpha', 'pro');

        $service = app(TalentSubscriptionService::class);

        // Confirmación legítima inicial
        $sub1 = $service->confirmPaymentRenewal(
            talent: $band,
            admin: $admin,
            paymentReference: 'PAYPAL-OP-1111',
            idempotencyToken: 'token-primer-envio'
        );
        $this->assertSame('2026-12-06', $sub1->end_date->toDateString());

        // Intento de reutilizar la misma operación PayPal con un nuevo token de confirmación
        $this->expectException(DuplicatePaymentOperationException::class);
        $this->expectExceptionMessage("La operación 'PAYPAL-OP-1111' ya fue registrada previamente para la banda 'Banda Alpha'");

        try {
            $service->confirmPaymentRenewal(
                talent: $band,
                admin: $admin,
                paymentReference: 'PAYPAL-OP-1111',
                idempotencyToken: 'token-segundo-envio-diferente'
            );
        } finally {
            // Asegurar que no se creó una segunda suscripción ni se extendió la vigencia
            $this->assertSame(1, $band->subscriptions()->count());
            $this->assertSame('2026-12-06', $band->fresh()->activeSubscription()?->end_date->toDateString());
            Carbon::setTestNow();
        }
    }

    /**
     * 3. Mismo pago presentado para otra banda:
     * Una operación de PayPal no puede ser transferida ni reutilizada para activar otra banda.
     */
    public function test_same_payment_rejected_when_presented_for_different_band(): void
    {
        Carbon::setTestNow('2026-11-06 12:00:00');
        $admin = User::factory()->create(['name' => 'Admin Auditor', 'is_admin' => true]);
        $bandA = $this->createBand('Banda Original', 'basic');
        $bandB = $this->createBand('Banda Copiona', 'basic');

        $service = app(TalentSubscriptionService::class);

        // Banda A confirma legítimamente con la operación 'PP-SHARE-999'
        $service->confirmPaymentRenewal(
            talent: $bandA,
            admin: $admin,
            paymentReference: 'PP-SHARE-999',
            idempotencyToken: 'token-band-a'
        );
        $this->assertSame('active', $bandA->fresh()->subscription_status);

        // Banda B intenta reutilizar la misma operación 'PP-SHARE-999'
        $this->expectException(DuplicatePaymentOperationException::class);
        $this->expectExceptionMessage("La operación 'PP-SHARE-999' ya fue registrada previamente para la banda 'Banda Original'");

        try {
            $service->confirmPaymentRenewal(
                talent: $bandB,
                admin: $admin,
                paymentReference: 'PP-SHARE-999',
                idempotencyToken: 'token-band-b'
            );
        } finally {
            // Banda B permanece inactiva sin suscripciones
            $this->assertSame(0, $bandB->subscriptions()->count());
            $this->assertSame('pending', $bandB->fresh()->subscription_status);
            Carbon::setTestNow();
        }
    }

    /**
     * 4. Protección persistente a nivel de base de datos contra solicitudes simultáneas / carreras:
     * Si dos transacciones intentan insertar el mismo payment_reference, la restricción UNIQUE de BD lo bloquea.
     */
    public function test_database_unique_constraint_enforces_anti_collision(): void
    {
        $band = $this->createBand('Banda Concurrente', 'basic');
        $admin = User::factory()->create();

        // 1. Registro directo en BD
        TalentSubscription::query()->create([
            'talent_id' => $band->id,
            'plan' => 'basic',
            'amount' => 3.50,
            'currency' => 'EUR',
            'payment_provider' => 'paypal',
            'payment_id' => 'TX-COLLISION-1',
            'payment_reference' => 'TX-COLLISION-1',
            'confirmed_by_user_id' => $admin->id,
            'confirmed_at' => now(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        // 2. Intento de insertar otro registro con el mismo payment_reference
        $service = app(TalentSubscriptionService::class);

        $this->expectException(DuplicatePaymentOperationException::class);
        $this->expectExceptionMessage("La operación 'TX-COLLISION-1' ya fue registrada previamente");

        $service->confirmPaymentRenewal(
            talent: $band,
            admin: $admin,
            paymentReference: 'TX-COLLISION-1',
            idempotencyToken: 'nuevo-token-concurrente'
        );
    }

    /**
     * 5. Dos pagos distintos válidos:
     * Cada pago legítimo e independiente con su propia referencia de PayPal renueva correctamente.
     */
    public function test_two_distinct_valid_payments_are_both_processed(): void
    {
        Carbon::setTestNow('2026-11-06 12:00:00');
        $admin = User::factory()->create();
        $band1 = $this->createBand('Banda Uno', 'basic');
        $band2 = $this->createBand('Banda Dos', 'pro');

        $service = app(TalentSubscriptionService::class);

        $sub1 = $service->confirmPaymentRenewal(
            talent: $band1,
            admin: $admin,
            paymentReference: 'PP-LEGIT-001',
            idempotencyToken: 'token-b1'
        );

        $sub2 = $service->confirmPaymentRenewal(
            talent: $band2,
            admin: $admin,
            paymentReference: 'PP-LEGIT-002',
            idempotencyToken: 'token-b2'
        );

        $this->assertNotNull($sub1);
        $this->assertNotNull($sub2);
        $this->assertSame('PP-LEGIT-001', $sub1->payment_reference);
        $this->assertSame('PP-LEGIT-002', $sub2->payment_reference);
        $this->assertSame('active', $band1->fresh()->subscription_status);
        $this->assertSame('active', $band2->fresh()->subscription_status);

        Carbon::setTestNow();
    }

    /**
     * 6. Referencia ausente y confirmación previamente procesada:
     * - Referencia ausente para PayPal lanza InvalidArgumentException.
     * - Reintento con el mismo idempotencyToken devuelve la suscripción existente sin error ni duplicación.
     */
    public function test_missing_reference_is_rejected_and_same_token_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $band = $this->createBand('Banda Idempotente', 'basic');
        $service = app(TalentSubscriptionService::class);

        // A) Referencia ausente para PayPal: rechazada
        try {
            $service->confirmPaymentRenewal(
                talent: $band,
                admin: $admin,
                paymentReference: '   ' // vacío
            );
            $this->fail('Debe rechazar referencia vacía para PayPal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('es obligatorio ingresar el ID canónico', $e->getMessage());
        }

        // B) Confirmación legítima
        $token = 'idempotent-token-xyz';
        $subFirst = $service->confirmPaymentRenewal(
            talent: $band,
            admin: $admin,
            paymentReference: 'PP-CLEAN-777',
            idempotencyToken: $token
        );
        $this->assertSame(1, $band->subscriptions()->count());

        // C) Re-envío exacto bajo el mismo token (ej. doble clic o retry de red): devuelve el mismo registro sin duplicar
        $subSecond = $service->confirmPaymentRenewal(
            talent: $band,
            admin: $admin,
            paymentReference: 'PP-CLEAN-777',
            idempotencyToken: $token
        );

        $this->assertSame($subFirst->id, $subSecond->id);
        $this->assertSame(1, $band->subscriptions()->count(), 'No se crea una segunda suscripción.');
    }

    /**
     * 7. Verificación en el controlador web admin:
     * El formulario web reporta el duplicado como error sin alterar la cuenta, y procesa referencias válidas.
     */
    public function test_admin_web_controller_handles_duplicate_and_valid_confirmations(): void
    {
        Carbon::setTestNow('2026-11-06 12:00:00');
        $admin = User::factory()->create(['name' => 'Admin Web', 'is_admin' => true]);
        $bandA = $this->createBand('Banda A', 'basic');
        $bandB = $this->createBand('Banda B', 'basic');

        // Confirmar primer abono legítimo para Banda A
        $response1 = $this->actingAs($admin)->post(route('admin.talents.renew', $bandA), [
            'payment_reference' => 'PP-WEB-555',
            'notes' => 'Recibido en Seven Rock Radio',
            'idempotency_token' => 'web-tok-1',
        ]);

        $response1->assertRedirect(route('admin.talents.edit', $bandA));
        $response1->assertSessionHas('status');
        $this->assertSame('active', $bandA->fresh()->subscription_status);

        // Intento desde el formulario web de usar la misma referencia para Banda B
        $response2 = $this->actingAs($admin)->post(route('admin.talents.renew', $bandB), [
            'payment_reference' => 'PP-WEB-555',
            'idempotency_token' => 'web-tok-2',
        ]);

        $response2->assertRedirect(route('admin.talents.edit', $bandB));
        $response2->assertSessionHasErrors('renewal');
        $this->assertSame('pending', $bandB->fresh()->subscription_status);
        $this->assertSame(0, $bandB->subscriptions()->count());

        $errorMsg = session('errors')->first('renewal');
        $this->assertStringContainsString('PP-WEB-555', $errorMsg);
        $this->assertStringContainsString('ya fue registrada previamente para la banda \'Banda A\'', $errorMsg);

        Carbon::setTestNow();
    }
}

