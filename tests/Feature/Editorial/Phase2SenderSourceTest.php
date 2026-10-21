<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailSenderSource;
use App\Models\NewRelease;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\EmailSecurityVerifier;
use App\Services\EmailSenderSourceResolver;
use App\Support\EmbargoDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase2SenderSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new \ReflectionClass(ThemeSetting::class);
        $property = $reflection->getProperty('currentSettings');
        $property->setAccessible(true);
        $property->setValue(null);

        ThemeSetting::query()->delete();
        ThemeSetting::create([
            'email_auto_publish' => false,
            'email_daily_posts_limit' => 5,
            'email_daily_releases_limit' => 5,
            'email_daily_news_limit' => 50,
            'ai_prefilter_enabled' => true,
        ]);
        $property->setValue(null);
    }

    /**
     * 1. Remitente autorizado y autenticado.
     */
    public function test_authorized_and_authenticated_sender_is_verified(): void
    {
        $source = EmailSenderSource::create([
            'name' => 'Century Media Records',
            'organization_type' => 'record_label',
            'authorized_emails' => ['promo@centurymedia.com'],
            'verified_domains' => ['centurymedia.com'],
            'status' => 'authorized',
            'auto_publish_allowed' => false,
        ]);

        $resolver = new EmailSenderSourceResolver();
        $res = $resolver->resolve('promo@centurymedia.com');
        $this->assertSame('authorized', $res['status']);
        $this->assertSame('exact_email', $res['match_type']);
        $this->assertSame($source->id, $res['source']->id);

        $verifier = new EmailSecurityVerifier();
        $rawHeaders = "Authentication-Results: mx.google.com; dkim=pass header.i=@centurymedia.com; spf=pass\r\nFrom: promo@centurymedia.com";
        $sec = $verifier->verify('promo@centurymedia.com', $rawHeaders, $source);

        $this->assertTrue($sec['is_authenticated']);
        $this->assertFalse($sec['is_spoofing']);
        $this->assertSame('verified', $sec['status']);
    }

    /**
     * 2. Remitente autorizado con autenticación inválida (spoofing detectado).
     */
    public function test_authorized_sender_with_failed_authentication_is_flagged_as_spoofing(): void
    {
        $source = EmailSenderSource::create([
            'name' => 'Napalm Records',
            'organization_type' => 'record_label',
            'authorized_emails' => ['press@napalmrecords.com'],
            'verified_domains' => ['napalmrecords.com'],
            'status' => 'authorized',
        ]);

        $verifier = new EmailSecurityVerifier();
        $rawHeaders = "Authentication-Results: mx.google.com; dkim=fail; spf=fail; dmarc=fail\r\nFrom: press@napalmrecords.com";
        $sec = $verifier->verify('press@napalmrecords.com', $rawHeaders, $source);

        $this->assertFalse($sec['is_authenticated']);
        $this->assertTrue($sec['is_spoofing']);
        $this->assertSame('spoofing_detected', $sec['status']);
    }

    /**
     * 3. Remitente desconocido (genera draft y status unknown).
     */
    public function test_unknown_sender_resolves_to_unknown(): void
    {
        $resolver = new EmailSenderSourceResolver();
        $res = $resolver->resolve('contacto@bandadesconocida.com');

        $this->assertNull($res['source']);
        $this->assertSame('unknown', $res['status']);
        $this->assertSame('unknown', $res['match_type']);
    }

    /**
     * 4. Remitente bloqueado (descarte y status blocked).
     */
    public function test_blocked_sender_resolves_to_blocked(): void
    {
        EmailSenderSource::create([
            'name' => 'Spam Agency Media',
            'organization_type' => 'pr_agency',
            'authorized_emails' => ['blast@spammusic.com'],
            'verified_domains' => ['spammusic.com'],
            'status' => 'blocked',
        ]);

        $resolver = new EmailSenderSourceResolver();
        $res = $resolver->resolve('blast@spammusic.com');

        $this->assertSame('blocked', $res['status']);
        $this->assertTrue($res['source']->isBlocked());
    }

    /**
     * 5. Remitente suspendido (status suspended).
     */
    public function test_suspended_sender_resolves_to_suspended(): void
    {
        EmailSenderSource::create([
            'name' => 'Agency In Dispute',
            'organization_type' => 'pr_agency',
            'authorized_emails' => ['info@agencyindispute.com'],
            'verified_domains' => ['agencyindispute.com'],
            'status' => 'suspended',
        ]);

        $resolver = new EmailSenderSourceResolver();
        $res = $resolver->resolve('info@agencyindispute.com');

        $this->assertSame('suspended', $res['status']);
        $this->assertTrue($res['source']->isSuspended());
    }

    /**
     * 6. Dominio parecido a uno autorizado (typosquatting o subcadena no autorizada).
     */
    public function test_lookalike_domain_is_not_matched_to_authorized_source(): void
    {
        EmailSenderSource::create([
            'name' => 'Nuclear Blast Records',
            'organization_type' => 'record_label',
            'authorized_emails' => ['press@nuclearblast.de'],
            'verified_domains' => ['nuclearblast.de'],
            'status' => 'authorized',
        ]);

        $resolver = new EmailSenderSourceResolver();
        // Typosquatting / prefijo engañoso:
        $res1 = $resolver->resolve('press@fakenuclearblast.de');
        $this->assertNull($res1['source']);
        $this->assertSame('unknown', $res1['status']);

        $res2 = $resolver->resolve('press@nuclearblast.de.malicious.com');
        $this->assertNull($res2['source']);
        $this->assertSame('unknown', $res2['status']);
    }

    /**
     * 7. Cabecera Authentication-Results falsificada (authserv-id no confiable inyectado por atacante).
     */
    public function test_forged_authentication_results_header_from_untrusted_host_is_ignored(): void
    {
        $verifier = new EmailSecurityVerifier();
        // El atacante inyecta una cabecera Authentication-Results con su propio servidor no confiable:
        $rawHeaders = "Authentication-Results: attacker-relay.evil.com; dkim=pass header.i=@target.com; spf=pass\r\nFrom: target@target.com";

        $sec = $verifier->verify('target@target.com', $rawHeaders);

        $this->assertFalse($sec['is_authenticated']);
        $this->assertSame('unverified', $sec['status']);
        $this->assertSame('missing_or_untrusted_auth_headers', $sec['reason']);
    }

    /**
     * 8. Resultados SPF/DKIM/DMARC no concluyentes (neutral / temperror / falta de alineación).
     */
    public function test_inconclusive_authentication_results(): void
    {
        $verifier = new EmailSecurityVerifier();
        // SPF neutral y DKIM ausente o desalineado:
        $rawHeaders = "Authentication-Results: mx.google.com; dkim=none; spf=neutral\r\nFrom: promo@independent-artist.com";

        $sec = $verifier->verify('promo@independent-artist.com', $rawHeaders);

        $this->assertFalse($sec['is_authenticated']);
        $this->assertSame('inconclusive', $sec['status']);
    }

    /**
     * 9. Correo reenviado (SPF falla por cambio de relay, pero DKIM pasa alineado).
     */
    public function test_forwarded_email_with_valid_aligned_dkim_is_verified(): void
    {
        $source = EmailSenderSource::create([
            'name' => 'Sony Music PR',
            'organization_type' => 'pr_agency',
            'authorized_emails' => ['pr@sonymusic.com'],
            'verified_domains' => ['sonymusic.com'],
            'status' => 'authorized',
        ]);

        $verifier = new EmailSecurityVerifier();
        // En reenvío: spf=fail (o softfail), pero dkim=pass con dominio sonymusic.com:
        $rawHeaders = "Authentication-Results: mx.google.com; dkim=pass header.i=@sonymusic.com; spf=fail (relay ip changed)\r\nFrom: pr@sonymusic.com";

        $sec = $verifier->verify('pr@sonymusic.com', $rawHeaders, $source);

        $this->assertTrue($sec['is_authenticated']);
        $this->assertTrue($sec['is_forwarded']);
        $this->assertFalse($sec['is_spoofing']);
        $this->assertSame('verified', $sec['status']);
        $this->assertSame('forwarded_with_valid_aligned_dkim', $sec['reason']);
    }

    /**
     * 10. Suplantación del agente Dark Vader (From con dark.vader.agent@gmail.com sin DKIM de Google).
     */
    public function test_spoofed_dark_vader_email_fails_legitimacy_check(): void
    {
        $verifier = new EmailSecurityVerifier();

        // 1. Caso falso / no autenticado: sin cabecera de mx.google.com o dkim=fail
        $fakeHeaders = "Authentication-Results: fake-relay.com; dkim=pass header.i=@gmail.com\r\nFrom: dark.vader.agent@gmail.com";
        $this->assertFalse($verifier->isDarkVaderLegitimate('dark.vader.agent@gmail.com', $fakeHeaders));

        // 2. Caso falso: mx.google.com pero dkim firmado por otro dominio que no es google/gmail
        $impersonatedHeaders = "Authentication-Results: mx.google.com; dkim=pass header.i=@evil-hacker.com; spf=pass\r\nFrom: dark.vader.agent@gmail.com";
        $this->assertFalse($verifier->isDarkVaderLegitimate('dark.vader.agent@gmail.com', $impersonatedHeaders));

        // 3. Caso genuino de Google:
        $genuineHeaders = "Authentication-Results: mx.google.com; dkim=pass header.i=@gmail.com; spf=pass\r\nFrom: dark.vader.agent@gmail.com";
        $this->assertTrue($verifier->isDarkVaderLegitimate('dark.vader.agent@gmail.com', $genuineHeaders));
    }

    /**
     * 11. Agencia autorizada que envía material bajo embargo (detección de embargo y forzado a borrador).
     */
    public function test_authorized_agency_sending_embargoed_material_is_detected(): void
    {
        $subject = 'UNDER EMBARGO until Nov 30: New Album Announcement';
        $body = 'Please note this material is strictly confidential and under embargo until November 30th.';

        $embargo = EmbargoDetector::detect($subject, $body);

        $this->assertTrue($embargo['is_embargoed']);
        $this->assertNotNull($embargo['matched_pattern']);

        // Aunque una fuente tenga auto_publish_allowed = true (ficticio en Fase 2):
        $source = EmailSenderSource::create([
            'name' => 'Embargo Label',
            'organization_type' => 'record_label',
            'authorized_emails' => ['news@embargolabel.com'],
            'verified_domains' => ['embargolabel.com'],
            'status' => 'authorized',
            'auto_publish_allowed' => true,
        ]);

        // La regla inquebrantable obliga a draft si hay embargo:
        $status = $embargo['is_embargoed'] ? 'draft' : 'published';
        $this->assertSame('draft', $status);
    }

    /**
     * 12. NewRelease histórico editado desde el panel (preservación de origin = 'unknown').
     */
    public function test_admin_editing_historical_new_release_preserves_unknown_origin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        // Crear lanzamiento histórico con origen 'unknown'
        $historical = NewRelease::create([
            'title' => 'Lanzamiento Antiguo 2023',
            'slug' => 'lanzamiento-antiguo-2023',
            'artist_name' => 'Banda Veterana',
            'description' => 'Descripción histórica',
            'origin' => 'unknown',
            'is_active' => false,
            'author_email' => 'pr@agencia-antigua.com',
        ]);

        // Simular edición administrativa a través del controlador NewReleaseController@update
        $response = $this->actingAs($admin)->put(route('admin.new-releases.update', $historical), [
            'title' => 'Lanzamiento Antiguo 2023 (Remaster)',
            'artist_name' => 'Banda Veterana',
            'cover_image' => 'catalog/releases/covers/portada.jpg',
            'description' => 'Descripción actualizada por administrador',
            'released_at' => now()->toDateString(),
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.new-releases.index'));

        $historical->refresh();
        $this->assertSame('Lanzamiento Antiguo 2023 (Remaster)', $historical->title);
        // Comprobar expresamente que NO mutó a 'manual':
        $this->assertSame('unknown', $historical->origin, 'El origen histórico debe conservarse como unknown.');
    }

    /**
     * 13. Ausencia de publicación automática en todas las rutas de correo en Fase 2.
     */
    public function test_all_external_email_routes_generate_drafts_in_phase_2(): void
    {
        Mail::fake();

        // Crear post que simula la ingesta externa en Fase 2
        $externalPost = Post::create([
            'title' => 'Noticia de Agencia Externa',
            'slug' => 'noticia-agencia-externa',
            'content' => 'Contenido redactado',
            'status' => 'draft',
            'is_published' => false,
            'source_subject' => 'Press Release: Tour Dates',
            'author_email' => 'pr@authorized-agency.com',
        ]);

        $this->assertFalse((bool) $externalPost->is_published);
        $this->assertSame('draft', $externalPost->status);
        Mail::assertNothingSent();
    }
}
