<?php

declare(strict_types=1);

namespace Tests\Feature\Talent;

use App\Services\Gateways\MercadoPago\MercadoPagoWebhookAuthenticatorInterface;
use App\Services\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MercadoPagoWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;
    private const DUMMY_SECRET = 'dummy_secret_for_feature_test_only_12345';
    private const DUMMY_REQUEST_ID = '00000000-0000-0000-0000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('mercadopago.webhook_secret', self::DUMMY_SECRET);
        Config::set('mercadopago.webhook_tolerance', null);
    }

    private function generateValidHeader(string $secret, ?string $dataId, ?string $requestId, string $ts): string
    {
        $parts = [];
        if ($dataId !== null && $dataId !== '') {
            $parts[] = 'id:' . strtolower($dataId);
        }
        if ($requestId !== null && $requestId !== '') {
            $parts[] = 'request-id:' . $requestId;
        }
        $parts[] = 'ts:' . $ts;
        $manifest = implode(';', $parts) . ';';
        $hash = hash_hmac('sha256', $manifest, $secret);

        return "ts={$ts},v1={$hash}";
    }

    public function test_unauthenticated_request_with_valid_query_id_returns_http_401(): void
    {
        // Valid query ID present, but x-signature header is absent -> must return 401 Unauthorized
        $response = $this->postJson('/talentos/webhook/mercadopago?data.id=123456', [
            'type' => 'payment.approved',
            'data' => ['id' => '123456'],
        ]);

        $response->assertStatus(401);
        $this->assertSame('Unauthorized', $response->getContent());
    }

    public function test_request_missing_signed_query_id_returns_http_400(): void
    {
        // R01/V05: Missing signed query ID must be rejected with exact 400 Bad Request
        $response = $this->postJson('/talentos/webhook/mercadopago', [
            'type' => 'payment.approved',
            'data' => ['id' => '999999'],
        ]);

        $response->assertStatus(400);
        $this->assertSame('Bad Request', $response->getContent());
    }

    public function test_conflicting_parameters_returns_http_400(): void
    {
        $ts = (string) (int) (microtime(true) * 1000);
        $sig = $this->generateValidHeader(self::DUMMY_SECRET, '11111', self::DUMMY_REQUEST_ID, $ts);

        $response = $this->call(
            'POST',
            '/talentos/webhook/mercadopago?data.id=11111',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $sig,
                'HTTP_X_REQUEST_ID' => self::DUMMY_REQUEST_ID,
            ],
            json_encode(['data' => ['id' => '22222']]) // conflicting ID in body
        );

        $response->assertStatus(400);
        $this->assertSame('Bad Request', $response->getContent());
    }

    public function test_unconfigured_webhook_secret_returns_http_500(): void
    {
        Config::set('mercadopago.webhook_secret', '');

        $response = $this->postJson('/talentos/webhook/mercadopago?data.id=123456', [
            'type' => 'payment.approved',
            'data' => ['id' => '123456'],
        ]);

        $response->assertStatus(500);
        $this->assertSame('Configuration Error', $response->getContent());
    }

    public function test_empty_verified_identifier_throws_and_returns_http_400(): void
    {
        // V01: When authenticator returns empty string or whitespace, gateway must throw MercadoPagoPayloadValidationException
        $mockAuthenticator = $this->createMock(MercadoPagoWebhookAuthenticatorInterface::class);
        $mockAuthenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn('');

        $this->app->instance(MercadoPagoWebhookAuthenticatorInterface::class, $mockAuthenticator);

        $response = $this->postJson('/talentos/webhook/mercadopago?data.id=123456', [
            'type' => 'payment.approved',
            'data' => ['id' => '123456'],
        ]);

        $response->assertStatus(400);
        $this->assertSame('Bad Request', $response->getContent());
    }

    public function test_valid_authenticated_request_returns_http_200(): void
    {
        // Mock the authenticator to verify that when authentication succeeds, the barrier allows flow and returns 200 for unhandled events
        $mockAuthenticator = $this->createMock(MercadoPagoWebhookAuthenticatorInterface::class);
        $mockAuthenticator->expects($this->once())
            ->method('authenticate')
            ->willReturn('VERIFIED_ID_123');

        $this->app->instance(MercadoPagoWebhookAuthenticatorInterface::class, $mockAuthenticator);

        $response = $this->postJson('/talentos/webhook/mercadopago?data.id=VERIFIED_ID_123', [
            'type' => 'unknown.event',
        ]);

        $response->assertStatus(200);
        $this->assertSame('OK', $response->getContent());
    }

    public function test_payment_manager_instantiates_all_gateways_without_mercadopago_secret(): void
    {
        Config::set('mercadopago.webhook_secret', null);
        Config::set('mercadopago.access_token', null);

        /** @var PaymentManager $manager */
        $manager = $this->app->make(PaymentManager::class);

        $this->assertNotNull($manager->driver('stripe'));
        $this->assertNotNull($manager->driver('paypal'));
        $this->assertNotNull($manager->driver('mercadopago'));
    }

    public function test_stripe_and_paypal_gateways_operate_independently(): void
    {
        Config::set('mercadopago.webhook_secret', null);

        /** @var PaymentManager $manager */
        $manager = $this->app->make(PaymentManager::class);

        $this->assertSame('stripe', $manager->driver('stripe')->getName());
        $this->assertSame('paypal', $manager->driver('paypal')->getName());
    }
}
