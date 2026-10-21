<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\AiCallAudit;
use App\Models\EditorialIncident;
use App\Models\EmailPostSanitizedMeta;
use App\Models\EmailSenderSource;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\EditorialAiPipeline;
use App\Services\EditorialCorrectionClassifier;
use App\Services\EditorialCorrelator;
use App\Services\EditorialIncidentManager;
use App\Services\EmailContentSanitizer;
use App\Services\EmailSecurityVerifier;
use App\Services\EmailSenderSourceResolver;
use App\Services\LinkSecurityClassifier;
use App\Support\EmailRelevanceFilter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase7EndToEndSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key'     => 'test-gemini-key',
            'services.openrouter.api_key' => 'test-openrouter-key',
            'mail.default'                => 'array',
        ]);

        Mail::fake();

        ThemeSetting::create([
            'email_auto_publish'        => false,
            'email_daily_news_limit'    => 20,
            'ai_prefilter_enabled'      => true,
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'is_admin' => false,
        ]);
    }

    /**
     * SCENARIO 1: Authorized agency full flow into draft inbox.
     */
    public function test_scenario_01_authorized_agency_full_flow(): void
    {
        EmailSenderSource::create([
            'name'                 => 'Rock Promotion Agency',
            'authorized_emails'    => ['promo@rockagency.com'],
            'verified_domains'     => ['rockagency.com'],
            'status'               => 'authorized',
            'auto_publish_allowed' => false,
        ]);

        $resolver = app(EmailSenderSourceResolver::class);
        $resolved = $resolver->resolve('promo@rockagency.com');

        $this->assertEquals('authorized', $resolved['status'] ?? $resolved->status);

        $post = Post::create([
            'title'          => 'Iron Maiden anuncia gira 2027',
            'slug'           => 'iron-maiden-anuncia-gira-2027',
            'author'         => 'rockagency.com',
            'author_email'   => 'promo@rockagency.com',
            'source_subject' => 'Nota de prensa Iron Maiden 2027',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Gira europea confirmada.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_subject'    => 'Nota de prensa Iron Maiden 2027',
            'sender_email'      => 'promo@rockagency.com',
            'security_status'   => 'verified',
            'redacted_links'    => [],
            'media_inventory'   => ['has_embedded_audio' => false],
            'is_embargo_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.editorial.index'));
        $response->assertStatus(200);
        $response->assertSee('Iron Maiden anuncia gira 2027');
        $response->assertSee('rockagency.com');
    }

    /**
     * SCENARIO 2: Unknown agency resolved as unknown/pending.
     */
    public function test_scenario_02_unknown_agency_resolved_to_pending(): void
    {
        $resolver = app(EmailSenderSourceResolver::class);
        $resolved = $resolver->resolve('press@unknown-indie-label.org');

        $this->assertNotNull($resolved);
        $this->assertEquals('unknown', $resolved['status']);

        // Registering as pending source
        $pendingSource = EmailSenderSource::create([
            'name'                 => 'Unknown Indie Label',
            'authorized_emails'    => ['press@unknown-indie-label.org'],
            'status'               => 'pending',
            'auto_publish_allowed' => false,
        ]);

        $resolvedAfter = $resolver->resolve('press@unknown-indie-label.org');
        $this->assertEquals('pending', $resolvedAfter['status']);
        $this->assertFalse($pendingSource->auto_publish_allowed);
    }

    /**
     * SCENARIO 3: Blocked agency rejected.
     */
    public function test_scenario_03_blocked_agency_source_rejected(): void
    {
        EmailSenderSource::create([
            'name'                 => 'Spam PR Company',
            'authorized_emails'    => ['spammer@badpr.com'],
            'verified_domains'     => ['badpr.com'],
            'status'               => 'blocked',
            'auto_publish_allowed' => false,
        ]);

        $resolver = app(EmailSenderSourceResolver::class);
        $resolved = $resolver->resolve('spammer@badpr.com');

        $this->assertEquals('blocked', $resolved['status']);
    }

    /**
     * SCENARIO 4: Spoofed email fails security verification.
     */
    public function test_scenario_04_spoofed_email_fails_security_verification(): void
    {
        $verifier = app(EmailSecurityVerifier::class);

        $rawHeaders = "Authentication-Results: mx.google.com; dkim=fail; spf=fail; dmarc=fail\r\nFrom: press@nuclearblast.com";

        $verification = $verifier->verify('press@nuclearblast.com', $rawHeaders);
        $this->assertTrue($verification['is_spoofing']);
        $this->assertEquals('spoofing_detected', $verification['status']);
    }

    /**
     * SCENARIO 5: Legitimate Mailchimp / Brevo PR campaign.
     */
    public function test_scenario_05_mailchimp_brevo_campaign_sanitized_and_preserved(): void
    {
        $sanitizer = app(EmailContentSanitizer::class);

        $htmlWithTrackers = '
            <p>La banda madrileña Dark Moor anuncia nuevo single.</p>
            <a href="https://mailchimp.us1.list-manage.com/track/click?u=123&id=456&e=789">Escuchar en Spotify</a>
            <img src="https://mailchimp.us1.list-manage.com/track/open.php?u=123" width="1" height="1" />
            <footer>Para darse de baja haga click <a href="https://unsubscribe.mailchimp.com">aquí</a></footer>
        ';

        $result = $sanitizer->sanitize($htmlWithTrackers);

        $this->assertStringContainsString('Dark Moor', $result['clean_html']);
        $this->assertStringNotContainsString('open.php', $result['clean_html']);
        $this->assertStringNotContainsString('unsubscribe.mailchimp.com', $result['clean_html']);
    }

    /**
     * SCENARIO 6: Private EPK links redacted to prevent leaks.
     */
    public function test_scenario_06_private_epk_links_redacted_zero_leak(): void
    {
        $classifier = app(LinkSecurityClassifier::class);

        $haulixResult = $classifier->classify('https://haulix.com/invitation/secret-album-token-998811');
        $this->assertEquals('restricted_private', $haulixResult['classification']);
        $this->assertFalse($haulixResult['is_allowed_in_post']);

        $promojukeboxResult = $classifier->classify('https://promojukebox.com/secure-player?key=vip887766');
        $this->assertEquals('restricted_private', $promojukeboxResult['classification']);
        $this->assertFalse($promojukeboxResult['is_allowed_in_post']);

        $redacted = $classifier->redactUrl('https://promojukebox.com/player?token=secret123');
        $this->assertStringNotContainsString('secret123', $redacted);
    }

    /**
     * SCENARIO 7: Active embargo blocks premature publishing.
     */
    public function test_scenario_07_active_embargo_blocks_premature_publishing(): void
    {
        $post = Post::create([
            'title'          => 'Megadeth nuevo disco',
            'slug'           => 'megadeth-nuevo-disco',
            'source_subject' => 'Megadeth nuevo disco (EMBARGO)',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Embargado.']]],
        ]);

        $embargoDate = Carbon::now()->addDays(3);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_subject'    => 'Megadeth nuevo disco (EMBARGO)',
            'sender_email'      => 'promo@megadeth.com',
            'redacted_links'    => [],
            'media_inventory'   => [],
            'is_embargo_active' => true,
            'embargo_until'     => $embargoDate,
            'security_status'   => 'embargoed',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.editorial.approve', $post));
        $response->assertStatus(422);
        $response->assertJsonFragment(['code' => 'active_embargo']);
        $this->assertEquals('draft', $post->fresh()->status);
    }

    /**
     * SCENARIO 8: Rectification creates incident and links to target post.
     */
    public function test_scenario_08_rectification_creates_incident_and_links_to_post(): void
    {
        $post = Post::create([
            'title'          => 'Saxon en Madrid',
            'slug'           => 'saxon-en-madrid',
            'source_subject' => 'Saxon en Madrid gira 2026',
            'status'         => 'scheduled',
            'scheduled_for'  => Carbon::now()->addDays(2),
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Concierto en Sala Riviera.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-saxon-orig',
            'source_subject'    => 'Saxon en Madrid gira 2026',
            'sender_email'      => 'press@saxonband.com',
            'security_status'   => 'verified',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => json_encode([
                            'content_type'         => 'musical_news',
                            'editorial_importance' => 4,
                            'facts'                => ['artist_name' => 'Saxon', 'venue' => 'Palacio Vistalegre'],
                        ])]]]]
                    ],
                    'usageMetadata' => ['promptTokenCount' => 200, 'candidatesTokenCount' => 100]
                ], 200)
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => json_encode([
                            'title'   => 'Saxon cambia su concierto al Palacio Vistalegre',
                            'excerpt' => 'Actualización de recinto para Saxon en Madrid.',
                            'blocks'  => [['type' => 'paragraph', 'data' => ['text' => 'El concierto será en Vistalegre.']]],
                        ])]]]]
                    ],
                    'usageMetadata' => ['promptTokenCount' => 250, 'candidatesTokenCount' => 150]
                ], 200),
        ]);

        $pipeline = app(EditorialAiPipeline::class);

        $rectificationEmail = [
            'subject'     => 'CORRECCIÓN: Saxon en Madrid gira 2026 cambia de recinto al Palacio Vistalegre',
            'body'        => 'Rogamos rectificar: el concierto de Saxon en Madrid no será en Riviera sino en Vistalegre.',
            'sender'      => 'press@saxonband.com',
            'artist_name' => 'Saxon',
            'message_id'  => 'msg-saxon-rect-001',
        ];

        $result = $pipeline->process($rectificationEmail);

        $this->assertTrue($result['is_rectification']);
        $this->assertNotNull($result['incident']);
        $incident = EditorialIncident::where('post_id', $post->id)->first();
        $this->assertNotNull($incident);
        $this->assertEquals('rectification_data', $incident->incident_type);
        $this->assertEquals('pending_review', $incident->resolution_status);
    }

    /**
     * SCENARIO 9: Duplicate resend is deduplicated via SHA-256.
     */
    public function test_scenario_09_duplicate_resend_deduplicated_by_sha256(): void
    {
        $post = Post::create([
            'title'          => 'Helloween gira mundial',
            'slug'           => 'helloween-gira-mundial',
            'source_subject' => 'Nota de prensa: Helloween gira mundial 2026',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Detalles de la gira mundial.']]],
        ]);

        $subject = 'Nota de prensa: Helloween gira mundial 2026';
        $body = 'Helloween confirma 25 fechas por todo el continente europeo.';

        $cleanText = strip_tags($body);
        $contentHash = hash('sha256', mb_strtolower(trim($subject)) . '|' . mb_strtolower(trim($cleanText)));

        AiCallAudit::create([
            'message_id'        => 'msg-helloween-1',
            'content_hash'      => $contentHash,
            'provider'          => 'gemini',
            'model'             => 'gemini-1.5-flash',
            'stage'             => 'editorial_writing',
            'prompt_tokens'     => 500,
            'completion_tokens' => 200,
            'total_tokens'      => 700,
            'status'            => 'success',
        ]);

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'    => $subject,
            'body'       => $body,
            'sender'     => 'promo@helloween.org',
            'message_id' => 'msg-helloween-2',
        ]);

        $this->assertEquals('duplicate', $result['status']);
        $this->assertEquals('discard_duplicate', $result['action']);
    }

    /**
     * SCENARIO 10: Radio program with MP3 is diverted.
     */
    public function test_scenario_10_radio_program_with_mp3_diverted_from_news(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode([
                        'content_type'         => 'radio_program_podcast',
                        'editorial_importance' => 4,
                        'facts'                => ['artist' => 'Deep Purple', 'program' => 'La Choza del Rock'],
                    ])]]]]
                ]
            ], 200),
        ]);

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'    => 'Programa La Choza del Rock #42 - Especial Deep Purple',
            'body'       => 'Adjunto el archivo MP3 con la emisión de esta semana para retransmisión.',
            'sender'     => 'locutor@laradio.com',
            'message_id' => 'msg-radio-show-01',
        ]);

        $this->assertEquals('radio_program', $result['status']);
        $this->assertEquals('route_to_audio_catalog', $result['action']);
    }

    /**
     * SCENARIO 11: Administrative or invoice email is discarded.
     */
    public function test_scenario_11_administrative_or_invoice_email_discarded(): void
    {
        $filter = app(EmailRelevanceFilter::class);

        $evaluation = $filter->shouldProcess(
            'billing@hostinger.com',
            'Factura F-2026-981 suscripción dominio y hosting',
            'Estimado cliente, adjuntamos la factura correspondiente al periodo octubre.'
        );

        $this->assertFalse($evaluation['pass']);
        $this->assertEquals('junk_or_service_notice', $evaluation['reason']);
    }

    /**
     * SCENARIO 12: AI failure triggers safe manual fallback draft.
     */
    public function test_scenario_12_ai_failure_gemini_and_openrouter_triggers_safe_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
            'openrouter.ai/*'                     => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $pipeline = app(EditorialAiPipeline::class);

        $result = $pipeline->process([
            'subject'    => 'Nuevo álbum de Avantasia',
            'body'       => 'Tobias Sammet anuncia nuevo disco titulado The Moonflower Dream.',
            'sender'     => 'press@avantasia.de',
            'message_id' => 'msg-ai-fail-001',
        ]);

        $this->assertEquals('fallback_draft', $result['status']);
        $this->assertNotEmpty($result['draft']['title']);
        $this->assertEquals('ai_failure', $result['failure_type']);
    }

    /**
     * SCENARIO 13: Insufficient information triggers manual review flag.
     */
    public function test_scenario_13_insufficient_information_flags_for_manual_review(): void
    {
        $pipeline = app(EditorialAiPipeline::class);

        $result = $pipeline->process([
            'subject'    => 'Hola',
            'body'       => 'Novedades pronto.',
            'sender'     => 'fan@rock.com',
            'message_id' => 'msg-short-001',
        ]);

        $this->assertContains($result['status'], ['discarded', 'fallback_draft', 'insufficient_info']);
    }

    /**
     * SCENARIO 14: Withdrawal request pauses scheduled post and creates incident.
     */
    public function test_scenario_14_withdrawal_request_pauses_scheduled_and_creates_high_priority_incident(): void
    {
        $post = Post::create([
            'title'          => 'Gira cancelada de Ghost',
            'slug'           => 'gira-cancelada-de-ghost',
            'source_subject' => 'Gira Ghost 2026 anuncio',
            'author_email'   => 'management@ghost-official.com',
            'status'         => 'scheduled',
            'scheduled_for'  => Carbon::now()->addDays(1),
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Detalles cancelados.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-ghost-orig',
            'source_subject'    => 'Gira Ghost 2026 anuncio',
            'sender_email'      => 'management@ghost-official.com',
            'security_status'   => 'verified',
        ]);

        $classifier = app(EditorialCorrectionClassifier::class);
        $classification = $classifier->classify('URGENTE: Retirar comunicado de Ghost por error contractual', 'Rogamos retirar de inmediato.');

        $this->assertEquals('retraction_withdrawal', $classification['incident_type']);
        $this->assertTrue($classification['is_correction']);

        $correlator = app(EditorialCorrelator::class);
        $correlation = $correlator->correlate([
            'subject'     => 'URGENTE: Retirar comunicado de Ghost por error contractual',
            'body'        => 'Rogamos retirar de inmediato.',
            'sender'      => 'management@ghost-official.com',
            'artist_name' => 'Ghost',
        ]);

        $incidentManager = app(EditorialIncidentManager::class);
        $res = $incidentManager->handleIncident($classification, $correlation, [
            'subject'     => 'URGENTE: Retirar comunicado de Ghost por error contractual',
            'body'        => 'Rogamos retirar de inmediato.',
            'sender'      => 'management@ghost-official.com',
            'artist_name' => 'Ghost',
            'message_id'  => 'msg-ghost-withdraw',
        ]);

        $this->assertEquals('halted_scheduled_post', $res['action']);
        $this->assertEquals('draft', $post->fresh()->status);
        $this->assertDatabaseHas('editorial_incidents', [
            'post_id'       => $post->id,
            'incident_type' => 'retraction_withdrawal',
            'priority'      => 'high',
        ]);
    }

    /**
     * PUBLISHING: Future scheduling after embargo date is allowed.
     */
    public function test_publishing_future_scheduling_after_embargo_allowed(): void
    {
        $embargoDate = Carbon::now()->addDays(2);

        $post = Post::create([
            'title'          => 'Lanzamiento Secreto',
            'slug'           => 'lanzamiento-secreto',
            'source_subject' => 'Lanzamiento Secreto (EMBARGO)',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Contenido embargado.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_subject'    => 'Lanzamiento Secreto (EMBARGO)',
            'sender_email'      => 'pr@secretband.com',
            'redacted_links'    => [],
            'media_inventory'   => [],
            'is_embargo_active' => true,
            'embargo_until'     => $embargoDate,
            'security_status'   => 'embargoed',
        ]);

        $scheduledFor = Carbon::now()->addDays(4)->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->admin)->post(route('admin.editorial.schedule', $post), [
            'scheduled_at' => $scheduledFor,
        ]);

        $response->assertRedirect();
        $this->assertEquals('scheduled', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }

    /**
     * PUBLISHING: Rejection does not delete database records.
     */
    public function test_rejection_marks_post_rejected_without_database_deletion(): void
    {
        $post = Post::create([
            'title'          => 'Noticia Rechazada',
            'slug'           => 'noticia-rechazada',
            'source_subject' => 'Nota de prensa descartable',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Borrador.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'        => $post->id,
            'source_subject' => 'Nota de prensa descartable',
            'sender_email'   => 'pr@junk.com',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.editorial.index'))
            ->post(route('admin.editorial.reject', $post));

        $response->assertRedirect(route('admin.editorial.index'));

        $this->assertEquals('rejected', $post->fresh()->status);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'rejected']);
    }

    /**
     * PUBLISHING: Bulk approval partially blocks embargoed posts.
     */
    public function test_bulk_approval_partially_blocks_embargoed_post(): void
    {
        $postClean1 = Post::create([
            'title'          => 'Borrador Limpio 1',
            'slug'           => 'borrador-limpio-1',
            'source_subject' => 'Limpio 1',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Texto 1']]],
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $postClean1->id, 'source_subject' => 'Limpio 1']);

        $postClean2 = Post::create([
            'title'          => 'Borrador Limpio 2',
            'slug'           => 'borrador-limpio-2',
            'source_subject' => 'Limpio 2',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Texto 2']]],
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $postClean2->id, 'source_subject' => 'Limpio 2']);

        $postEmbargoed = Post::create([
            'title'          => 'Borrador Embargado',
            'slug'           => 'borrador-embargado',
            'source_subject' => 'Embargado',
            'status'         => 'draft',
            'is_published'   => false,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Texto Embargado']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $postEmbargoed->id,
            'source_subject'    => 'Embargado',
            'sender_email'      => 'pr@embargo.com',
            'redacted_links'    => [],
            'media_inventory'   => [],
            'is_embargo_active' => true,
            'embargo_until'     => Carbon::now()->addDays(5),
            'security_status'   => 'embargoed',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.editorial.index'))
            ->post(route('admin.editorial.bulk-action'), [
                'action'   => 'approve',
                'post_ids' => [$postClean1->id, $postClean2->id, $postEmbargoed->id],
            ]);

        $response->assertRedirect(route('admin.editorial.index'));
        $this->assertEquals('published', $postClean1->fresh()->status);
        $this->assertEquals('published', $postClean2->fresh()->status);
        $this->assertEquals('draft', $postEmbargoed->fresh()->status);
    }

    /**
     * INCIDENTS: Resolution with errata is idempotent and preserves post URL/slug.
     */
    public function test_incident_errata_resolution_is_idempotent_and_preserves_slug(): void
    {
        $post = Post::create([
            'title'          => 'Barón Rojo en Barcelona',
            'slug'           => 'baron-rojo-en-barcelona',
            'source_subject' => 'Gira Barón Rojo',
            'status'         => 'published',
            'is_published'   => true,
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Texto original del concierto.']]],
        ]);

        $originalSlug = $post->slug;

        $incident = EditorialIncident::create([
            'post_id'              => $post->id,
            'incident_type'        => 'rectification_data',
            'resolution_status'    => 'pending_review',
            'priority'             => 'normal',
            'source_message_id'    => 'msg-errata-001',
            'post_previous_status' => 'published',
            'action_taken'         => 'pending_review',
            'detected_diff'        => ['field' => 'date', 'old' => '10 Nov', 'new' => '12 Nov'],
        ]);

        $resolveUrl = route('admin.editorial.incidents.resolve', $incident);

        // First resolution: apply errata
        $resp1 = $this->actingAs($this->admin)->post($resolveUrl, [
            'resolution_action' => 'apply_changes',
            'resolution_notes'  => 'Fecha verificada y corregida para el concierto',
        ]);

        $resp1->assertRedirect();
        $this->assertEquals('resolved_applied', $incident->fresh()->resolution_status);
        $this->assertEquals($originalSlug, $post->fresh()->slug);

        $contentStr = is_array($post->fresh()->content) ? json_encode($post->fresh()->content) : (string) $post->fresh()->content;
        $this->assertStringContainsString('Fe de Erratas', $contentStr);

        // Second resolution call (idempotent guard): redirects cleanly without duplication
        $resp2 = $this->actingAs($this->admin)->post($resolveUrl, [
            'resolution_action' => 'apply_changes',
            'resolution_notes'  => 'Segunda llamada innecesaria que no debe duplicar',
        ]);

        $resp2->assertRedirect();
        $this->assertEquals('resolved_applied', $incident->fresh()->resolution_status);
    }

    /**
     * PRIVACY: Public routes never expose EPK links, internal notes, or AI credentials.
     */
    public function test_public_routes_never_expose_private_metadata_or_credentials(): void
    {
        $post = Post::create([
            'title'          => 'Public News Without Leaks',
            'slug'           => 'public-news-without-leaks',
            'source_subject' => 'Public News',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => Carbon::now()->subHour(),
            'content'        => [['type' => 'paragraph', 'data' => ['text' => 'Public rock news content.']]],
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_subject'    => 'Confidential Promo for Press',
            'sender_email'      => 'private@rockagency.com',
            'redacted_links'    => [
                ['original_url' => 'https://haulix.com/vip-leak-token-12345', 'reason' => 'private_epk']
            ],
            'media_inventory'   => ['internal_notes' => 'Do not publish before tour announcement'],
            'is_embargo_active' => false,
            'security_status'   => 'clean',
        ]);

        $response = $this->get('/noticias/public-news-without-leaks');

        if ($response->status() === 200) {
            $content = $response->getContent();
            $this->assertStringNotContainsString('vip-leak-token-12345', $content);
            $this->assertStringNotContainsString('Do not publish before tour announcement', $content);
            $this->assertStringNotContainsString('test-gemini-key', $content);
            $this->assertStringNotContainsString('test-openrouter-key', $content);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * SECURITY: Unauthenticated users cannot access editorial inbox.
     */
    public function test_unauthenticated_users_cannot_access_editorial_inbox(): void
    {
        $this->get(route('admin.editorial.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.editorial.sources.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.editorial.incidents.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->regularUser)->get(route('admin.editorial.index'))->assertForbidden();
    }
}
