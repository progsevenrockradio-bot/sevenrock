<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AirplaySchedule;
use App\Models\AirplayWeek;
use App\Models\MarketingContact;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ArtistEmailMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirplayApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ThemeSetting::set('airplay_api_token', 'valid-test-token-1234567890');
    }

    public function test_post_schedule_without_token_returns_401(): void
    {
        $response = $this->postJson(route('api.airplay.schedule'), [
            'semana'  => '2026-W40',
            'bloques' => [
                [
                    'dia'      => 1,
                    'hora'     => 10,
                    'posicion' => 1,
                    'artista'  => 'Test Artist',
                    'titulo'   => 'Test Song',
                ],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_post_schedule_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'X-Token' => 'invalid-token-value',
        ])->postJson(route('api.airplay.schedule'), [
            'semana'  => '2026-W40',
            'bloques' => [
                [
                    'dia'      => 1,
                    'hora'     => 10,
                    'posicion' => 1,
                    'artista'  => 'Test Artist',
                    'titulo'   => 'Test Song',
                ],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_post_schedule_with_valid_token_returns_200_and_creates_records(): void
    {
        $token = 'valid-test-token-1234567890';

        $payload = [
            'semana'               => '2026-W40',
            'generado_en'          => '2026-10-02 12:00:00',
            'hueco_publicidad_min' => 8,
            'bloques'              => [
                [
                    'dia'            => 1,
                    'hora'           => 14,
                    'posicion'       => 1,
                    'artista'        => 'Judas Priest',
                    'titulo'         => 'Painkiller',
                    'album'          => 'Painkiller',
                    'es_novedad'     => true,
                    'es_primer_pase' => false,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson(route('api.airplay.schedule'), $payload);

        $response->assertStatus(200)
            ->assertJson([
                'ok'     => true,
                'semana' => '2026-W40',
                'total'  => 1,
            ]);

        $this->assertDatabaseHas('airplay_weeks', [
            'semana'       => '2026-W40',
            'pistas_total' => 1,
            'novedades'    => 1,
        ]);

        $this->assertDatabaseHas('airplay_schedule', [
            'semana'     => '2026-W40',
            'dia'        => 1,
            'hora'       => 14,
            'posicion'   => 1,
            'artista'    => 'Judas Priest',
            'titulo'     => 'Painkiller',
            'es_novedad' => 1,
        ]);
    }

    public function test_artist_email_matcher_matches_iron_maiden_and_not_arbitrary_strings(): void
    {
        MarketingContact::create([
            'email'           => 'contact@ironmaiden.com',
            'name'            => 'Management Team',
            'company_or_band' => 'IRON MAIDEN MANAGEMENT',
            'is_active'       => true,
        ]);

        $matcher = app(ArtistEmailMatcher::class);

        $result = $matcher->matchArtist('Iron Maiden');
        $this->assertSame('contact@ironmaiden.com', $result['email_artista']);

        $resultNonMatching = $matcher->matchArtist('Metallica');
        $this->assertNull($resultNonMatching['email_artista']);

        $resultArbitrary = $matcher->matchArtist('Random Noise ABC');
        $this->assertNull($resultArbitrary['email_artista']);
    }

    public function test_schedule_store_populates_missing_emails_via_matcher(): void
    {
        MarketingContact::create([
            'email'           => 'ironmaiden@band.com',
            'name'            => 'Iron Maiden',
            'company_or_band' => 'IRON MAIDEN MANAGEMENT',
            'is_active'       => true,
        ]);

        $token = 'valid-test-token-1234567890';

        $payload = [
            'semana'  => '2026-W41',
            'bloques' => [
                [
                    'dia'           => 2,
                    'hora'          => 16,
                    'posicion'      => 1,
                    'artista'       => 'Iron Maiden',
                    'titulo'        => 'The Trooper',
                    'email_artista' => null,
                    'email_sello'   => null,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-Token' => $token,
        ])->postJson(route('api.airplay.schedule'), $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('airplay_schedule', [
            'semana'        => '2026-W41',
            'artista'       => 'Iron Maiden',
            'email_artista' => 'ironmaiden@band.com',
        ]);
    }

    public function test_guest_is_redirected_from_programacion_to_login(): void
    {
        $response = $this->get(route('airplay.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_airplay_index_without_500_error(): void
    {
        $admin = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@sevenrock.test',
            'password' => 'secret123',
            'is_admin' => true,
            'role'     => 'admin',
        ]);

        AirplayWeek::create([
            'semana'       => '2026-W40',
            'pistas_total' => 10,
            'novedades'    => 2,
        ]);

        $response = $this->actingAs($admin)->get(route('airplay.index'));

        $response->assertStatus(200);
        $response->assertSee('Programación Semanal (Airplay)');
        $response->assertSee('2026-W40');
    }

    public function test_admin_can_view_airplay_notices_without_error(): void
    {
        $admin = User::create([
            'name'     => 'Admin User 2',
            'email'    => 'admin2@sevenrock.test',
            'password' => 'secret123',
            'is_admin' => true,
            'role'     => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('airplay.notices'));

        $response->assertStatus(200);
        $response->assertSee('Historial de Avisos de Programación');
    }
}
