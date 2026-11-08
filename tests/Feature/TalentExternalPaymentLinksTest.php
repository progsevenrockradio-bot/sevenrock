<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Talent;
use App\Models\User;
use App\Support\TalentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentExternalPaymentLinksTest extends TestCase
{
    use RefreshDatabase;

    private function createTalent(string $plan = 'free', array $overrides = []): Talent
    {
        return Talent::factory()->create(array_merge([
            'band_name' => 'Banda ' . uniqid(),
            'email' => 'banda_' . uniqid() . '@sevenrock.test',
            'plan' => $plan,
            'subscription_status' => 'active',
            'is_hidden' => false,
        ], $overrides));
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin_' . uniqid() . '@sevenrockradio.com',
            'is_admin' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_update_payment_links(): void
    {
        $response = $this->put(route('talents.profile.update'), [
            'payment_links' => [
                'support' => 'https://paypal.me/hack',
            ],
        ]);

        $response->assertRedirect(route('talents.login'));
    }

    public function test_band_can_save_and_edit_external_payment_links(): void
    {
        $talent = $this->createTalent('free');

        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'payment_links' => [
                    'support' => 'https://paypal.me/losrockeros',
                    'merch' => 'https://losrockeros.bandcamp.com',
                ],
            ])
            ->assertRedirect(route('talents.profile'))
            ->assertSessionHas('status');

        $talent->refresh();

        $this->assertSame('https://paypal.me/losrockeros', $talent->supportPaymentUrl());
        $this->assertSame('https://losrockeros.bandcamp.com', $talent->merchPaymentUrl());
        $this->assertSame([
            'support' => 'https://paypal.me/losrockeros',
            'merch' => 'https://losrockeros.bandcamp.com',
        ], $talent->paymentLinkMap());

        // Edición
        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'payment_links' => [
                    'support' => 'https://ko-fi.com/losrockeros',
                    'merch' => 'https://tienda.losrockeros.com/merch',
                ],
            ])
            ->assertRedirect(route('talents.profile'));

        $talent->refresh();
        $this->assertSame('https://ko-fi.com/losrockeros', $talent->supportPaymentUrl());
        $this->assertSame('https://tienda.losrockeros.com/merch', $talent->merchPaymentUrl());
    }

    public function test_band_can_remove_payment_links(): void
    {
        $talent = $this->createTalent('basic', [
            'payment_links' => [
                'support' => 'https://paypal.me/mirock',
                'merch' => 'https://mirock.bandcamp.com',
            ],
        ]);

        $this->assertSame('https://paypal.me/mirock', $talent->supportPaymentUrl());

        // Retirar el enlace de apoyo dejando el campo en blanco
        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'payment_links' => [
                    'support' => '',
                    'merch' => 'https://mirock.bandcamp.com',
                ],
            ])
            ->assertRedirect(route('talents.profile'));

        $talent->refresh();
        $this->assertNull($talent->supportPaymentUrl());
        $this->assertSame('https://mirock.bandcamp.com', $talent->merchPaymentUrl());

        // Retirar también el merch
        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'payment_links' => [
                    'support' => '',
                    'merch' => '',
                ],
            ])
            ->assertRedirect(route('talents.profile'));

        $talent->refresh();
        $this->assertNull($talent->supportPaymentUrl());
        $this->assertNull($talent->merchPaymentUrl());
        $this->assertSame([], $talent->paymentLinkMap());
    }

    public function test_invalid_urls_are_rejected(): void
    {
        $talent = $this->createTalent('pro');

        $invalidUrls = [
            'http://paypal.me/no-https' => 'HTTP sin cifrar',
            'javascript:alert(1)' => 'Esquema javascript',
            'data:text/html;base64,PHNjcmlwdD4=' => 'Esquema data',
            'https://localhost/donar' => 'Localhost',
            'https://127.0.0.1/donar' => 'Loopback IP',
            'https://192.168.1.100/tienda' => 'IP privada 192.168.*',
            'https://10.0.0.5/tienda' => 'IP privada 10.*',
            'https://mi-servidor.test/merch' => 'TLD de prueba .test',
            'https://mi-servidor.local/merch' => 'TLD local .local',
            'https://servidor-interno/tienda' => 'Host sin punto/red interna',
            'no-es-una-url' => 'Texto sin formato URL',
        ];

        foreach ($invalidUrls as $invalidUrl => $description) {
            $response = $this->actingAs($talent, 'talent')
                ->from(route('talents.profile'))
                ->put(route('talents.profile.update'), [
                    'name' => $talent->band_name,
                    'payment_links' => [
                        'support' => $invalidUrl,
                    ],
                ]);

            $response->assertSessionHasErrors(['payment_links.support'], "Fallo al validar caso: {$description}");
        }
    }

    public function test_public_profile_displays_buttons_only_when_configured(): void
    {
        // 1. Sin enlaces configurados: no debe mostrar el panel de apoyo y compra directa
        $talentNoLinks = $this->createTalent('free');
        $this->get(route('talents.show', $talentNoLinks->band_name))
            ->assertOk()
            ->assertDontSee('Apoyo y compra directa')
            ->assertDontSee('Apoyar a la banda')
            ->assertDontSee('Comprar música / Merch');

        // 2. Solo enlace de apoyo
        $talentSupport = $this->createTalent('basic', [
            'payment_links' => [
                'support' => 'https://paypal.me/solosoporte',
            ],
        ]);
        $resSupport = $this->get(route('talents.show', $talentSupport->band_name));
        $resSupport->assertOk()
            ->assertSee('Apoyo y compra directa')
            ->assertSee('Apoyar a la banda')
            ->assertSee('https://paypal.me/solosoporte')
            ->assertSee('El pago se realiza en una plataforma externa.')
            ->assertDontSee('Comprar música / Merch');

        // 3. Solo enlace de merch
        $talentMerch = $this->createTalent('pro', [
            'payment_links' => [
                'merch' => 'https://solomerch.bandcamp.com',
            ],
        ]);
        $resMerch = $this->get(route('talents.show', $talentMerch->band_name));
        $resMerch->assertOk()
            ->assertSee('Apoyo y compra directa')
            ->assertSee('Comprar música / Merch')
            ->assertSee('https://solomerch.bandcamp.com')
            ->assertSee('El pago se realiza en una plataforma externa.')
            ->assertDontSee('Apoyar a la banda');

        // 4. Ambos enlaces configurados
        $talentBoth = $this->createTalent('free', [
            'payment_links' => [
                'support' => 'https://ko-fi.com/losdos',
                'merch' => 'https://losdos.com/tienda',
            ],
        ]);
        $resBoth = $this->get(route('talents.show', $talentBoth->band_name));
        $resBoth->assertOk()
            ->assertSee('Apoyo y compra directa')
            ->assertSee('Apoyar a la banda')
            ->assertSee('https://ko-fi.com/losdos')
            ->assertSee('Comprar música / Merch')
            ->assertSee('https://losdos.com/tienda')
            ->assertSee('rel="noopener noreferrer nofollow"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('El pago se realiza en una plataforma externa.');
    }

    public function test_functionality_available_equally_across_free_basic_and_pro(): void
    {
        foreach (['free', 'basic', 'pro'] as $plan) {
            $talent = $this->createTalent($plan);

            $this->actingAs($talent, 'talent')
                ->put(route('talents.profile.update'), [
                    'name' => $talent->band_name,
                    'payment_links' => [
                        'support' => "https://paypal.me/{$plan}band",
                        'merch' => "https://{$plan}band.bandcamp.com",
                    ],
                ])
                ->assertRedirect(route('talents.profile'))
                ->assertSessionHasNoErrors();

            $talent->refresh();
            $this->assertSame("https://paypal.me/{$plan}band", $talent->supportPaymentUrl());
            $this->assertSame("https://{$plan}band.bandcamp.com", $talent->merchPaymentUrl());

            $this->get(route('talents.show', $talent->band_name))
                ->assertOk()
                ->assertSee('Apoyar a la banda')
                ->assertSee('Comprar música / Merch');
        }
    }

    public function test_external_links_do_not_interfere_with_seven_rock_radio_plan_payments(): void
    {
        $talent = $this->createTalent('basic', [
            'payment_links' => [
                'support' => 'https://paypal.me/banda-propia',
                'merch' => 'https://tienda-propia.com',
            ],
        ]);

        // Los enlaces de cobro oficial de Seven Rock Radio permanecen intactos
        $this->assertSame('https://www.paypal.com/ncp/payment/FTNT3HTH6KXR2', TalentPlan::paypalUrl('basic'));
        $this->assertSame('https://www.paypal.com/ncp/payment/SHQVV9LS29V8U', TalentPlan::paypalUrl('pro'));

        // Y no coinciden en ningún caso con los de la banda
        $this->assertNotSame(TalentPlan::paypalUrl('basic'), $talent->supportPaymentUrl());
        $this->assertNotSame(TalentPlan::paypalUrl('pro'), $talent->merchPaymentUrl());
    }

    public function test_ownership_and_permission_isolation(): void
    {
        $bandA = $this->createTalent('free');
        $bandB = $this->createTalent('free', [
            'payment_links' => [
                'support' => 'https://paypal.me/banda-b',
            ],
        ]);

        // Band A autenticada solo puede modificar su propio perfil
        $this->actingAs($bandA, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $bandA->band_name,
                'payment_links' => [
                    'support' => 'https://paypal.me/banda-a',
                ],
            ])
            ->assertRedirect(route('talents.profile'));

        $bandA->refresh();
        $bandB->refresh();

        $this->assertSame('https://paypal.me/banda-a', $bandA->supportPaymentUrl());
        $this->assertSame('https://paypal.me/banda-b', $bandB->supportPaymentUrl());

        // Usuario no admin no puede usar admin.talents.update
        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($regularUser, 'web')
            ->put(route('admin.talents.update', $bandB), [
                'band_name' => $bandB->band_name,
                'plan' => $bandB->plan,
                'subscription_status' => $bandB->subscription_status,
                'payment_links' => [
                    'support' => 'https://paypal.me/malicioso',
                ],
            ])
            ->assertForbidden();

        // Administrador autorizado sí puede modificar los enlaces de la banda
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'web')
            ->put(route('admin.talents.update', $bandB), [
                'band_name' => $bandB->band_name,
                'plan' => $bandB->plan,
                'subscription_status' => $bandB->subscription_status,
                'payment_links' => [
                    'support' => 'https://paypal.me/banda-b-actualizada',
                    'merch' => 'https://banda-b.bandcamp.com',
                ],
            ])
            ->assertRedirect(route('admin.talents.index'));

        $bandB->refresh();
        $this->assertSame('https://paypal.me/banda-b-actualizada', $bandB->supportPaymentUrl());
        $this->assertSame('https://banda-b.bandcamp.com', $bandB->merchPaymentUrl());
    }

    public function test_preservation_of_historical_payment_links(): void
    {
        // Banda con datos históricos: mercadopago y paypal tradicional
        $talent = $this->createTalent('basic', [
            'payment_links' => [
                'paypal' => 'https://paypal.me/historico',
                'mercadopago' => 'https://mercadopago.com/checkout/123456',
                'otro' => 'https://historico.bandcamp.com',
            ],
        ]);

        // Las funciones de mapeo reconocen paypal como apoyo y otro como merch
        $this->assertSame('https://paypal.me/historico', $talent->supportPaymentUrl());
        $this->assertSame('https://historico.bandcamp.com', $talent->merchPaymentUrl());

        // La banda actualiza solo su merch mediante el formulario
        $this->actingAs($talent, 'talent')
            ->put(route('talents.profile.update'), [
                'name' => $talent->band_name,
                'payment_links' => [
                    'support' => 'https://paypal.me/historico',
                    'merch' => 'https://nueva-tienda.com',
                ],
            ])
            ->assertRedirect(route('talents.profile'));

        $talent->refresh();

        // mercadopago se preserva intacto sin ser eliminado ni alterado
        $raw = $talent->payment_links;
        $this->assertIsArray($raw);
        $this->assertSame('https://mercadopago.com/checkout/123456', $raw['mercadopago']);
        $this->assertSame('https://nueva-tienda.com', $raw['merch']);
    }
}

