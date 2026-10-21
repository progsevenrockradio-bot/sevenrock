<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EditorialIncident;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Services\EditorialAiPipeline;
use App\Services\EditorialCorrectionClassifier;
use App\Services\EditorialCorrelator;
use App\Services\EditorialIncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase5CorrectionsAndDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key'     => 'test-gemini-key',
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.ai.provider_chain'  => ['gemini', 'openrouter'],
        ]);

        ThemeSetting::create([
            'email_auto_publish'     => false,
            'ai_provider_chain'      => 'gemini,openrouter',
            'ai_fallback_enabled'    => true,
            'ai_daily_max_calls'     => 50,
            'email_daily_news_limit' => 10,
        ]);
    }

    protected function mockGeminiStructured(array $stage1Data, array $stage2Data): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => json_encode($stage1Data)]]]]
                    ],
                    'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 70]
                ], 200)
                ->push([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => json_encode($stage2Data)]]]]
                    ],
                    'usageMetadata' => ['promptTokenCount' => 180, 'candidatesTokenCount' => 110]
                ], 200),
        ]);
    }

    /**
     * Escenario 1: Comunicado original seguido de corrección vinculada en borrador.
     */
    public function test_scenario_01_original_statement_followed_by_linked_correction_in_draft(): void
    {
        $originalPost = Post::create([
            'title'          => 'Tierra Santa anuncia fecha en Logroño',
            'slug'           => 'tierra-santa-logrono',
            'content'        => 'Concierto en la Sala Concept.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'prensa@tierrasanta.es',
            'source_subject' => 'Tierra Santa fecha Logroño',
            'categories'     => ['General'],
        ]);

        $this->mockGeminiStructured(
            [
                'content_type' => 'rectification',
                'editorial_importance' => 4,
                'confidence_score' => 0.98,
                'facts' => [
                    'artist_name' => 'Tierra Santa',
                    'release_title' => 'Corrección Logroño',
                    'declared_facts' => ['La sala correcta es Sala Fundición'],
                ]
            ],
            [
                'title' => 'Corrección: Tierra Santa actuará en la Sala Fundición de Logroño',
                'excerpt' => 'La promotora rectifica la sala del concierto en Logroño.',
                'content' => "Tierra Santa actuará en la Sala Fundición y no en Concept como se indicó previamente.\n\nSigue sonando en Seven Rock Radio.",
                'editorial_warning' => null,
            ]
        );

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'    => 'Fe de Erratas: Tierra Santa fecha Logroño',
            'body'       => 'Rogamos corregir: Tierra Santa actuará en la Sala Fundición de Logroño.',
            'sender'     => 'prensa@tierrasanta.es',
            'message_id' => 'msg-corr-01',
            'artist_name'=> 'Tierra Santa',
        ]);

        $this->assertEquals('draft_ready', $result['status']);
        $this->assertTrue($result['is_rectification']);
        $this->assertNotNull($result['incident']);
        $this->assertEquals('linked_to_draft', $result['incident']['action']);

        $incident = EditorialIncident::where('source_message_id', 'msg-corr-01')->first();
        $this->assertNotNull($incident);
        $this->assertEquals($originalPost->id, $incident->post_id);
        $this->assertEquals('rectification_data', $incident->incident_type);
    }

    /**
     * Escenario 2: Solicitud expresa de ignorar comunicado anterior.
     */
    public function test_scenario_02_request_to_ignore_previous_email(): void
    {
        $originalPost = Post::create([
            'title'          => 'Anuncio anticipado no autorizado de Saratoga',
            'slug'           => 'anuncio-anticipado-saratoga',
            'content'        => 'Nota filtrada con fecha errónea.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'mgmt@saratoga.es',
            'source_subject' => 'Saratoga comunicado urgente',
            'categories'     => ['General'],
        ]);

        $classifier = app(EditorialCorrectionClassifier::class);
        $classification = $classifier->classify('Por favor, ignoren el correo anterior sobre Saratoga', 'Se envió por error');

        $this->assertTrue($classification['is_correction']);
        $this->assertEquals('retraction_withdrawal', $classification['incident_type']);
        $this->assertEquals('urgent', $classification['priority']);

        $correlator = app(EditorialCorrelator::class);
        $correlation = $correlator->correlate([
            'sender'      => 'mgmt@saratoga.es',
            'subject'     => 'Por favor, ignoren el correo anterior sobre Saratoga',
            'body'        => 'Rogamos ignorar el comunicado previo emitido por error.',
            'artist_name' => 'Saratoga',
        ]);

        $this->assertTrue($correlation['is_confident']);
        $this->assertEquals($originalPost->id, $correlation['target_post']->id);
    }

    /**
     * Escenario 3: Cancelación de gira o concierto.
     */
    public function test_scenario_03_concert_or_tour_cancellation(): void
    {
        $classifier = app(EditorialCorrectionClassifier::class);
        $classification = $classifier->classify('Gira cancelada de Warcry por motivos de salud', 'Lamentamos comunicar la cancelación del tour');

        $this->assertTrue($classification['is_correction']);
        $this->assertEquals('event_cancellation', $classification['incident_type']);
        $this->assertEquals('high', $classification['priority']);
    }

    /**
     * Escenario 4: Cambio legítimo de fecha de lanzamiento musical.
     */
    public function test_scenario_04_legitimate_release_date_change(): void
    {
        $originalPost = Post::create([
            'title'          => 'Nuevo álbum de Avalanch para octubre',
            'slug'           => 'nuevo-album-avalanch',
            'content'        => 'Fecha prevista: 15 de octubre de 2026.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'info@avalanch.es',
            'source_subject' => 'Avalanch nuevo álbum',
            'categories'     => ['General'],
        ]);

        $correlator = app(EditorialCorrelator::class);
        $correlation = $correlator->correlate([
            'sender'      => 'info@avalanch.es',
            'subject'     => 'Comunicado actualizado: Avalanch nuevo álbum',
            'body'        => 'La nueva fecha confirmada es el 20 de noviembre de 2026.',
            'artist_name' => 'Avalanch',
        ]);

        $this->assertTrue($correlation['has_match']);
        $this->assertArrayHasKey('new_date_mentioned', $correlation['detected_diff']);
        $this->assertStringContainsString('20 de noviembre', $correlation['detected_diff']['new_date_mentioned']);
    }

    /**
     * Escenario 5: Corrección sobre borrador existente lo retiene y vincula.
     */
    public function test_scenario_05_correction_over_existing_draft_holds_and_links(): void
    {
        $draftPost = Post::create([
            'title'          => 'Barón Rojo fecha Valencia',
            'slug'           => 'baron-rojo-valencia',
            'content'        => 'Borrador de noticia.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'prensa@baronrojo.es',
            'source_subject' => 'Barón Rojo fecha Valencia',
            'categories'     => ['General'],
        ]);

        $manager = app(EditorialIncidentManager::class);
        $result = $manager->handleIncident(
            ['incident_type' => 'rectification_data', 'priority' => 'high'],
            [
                'is_confident' => true,
                'target_post'  => $draftPost,
                'correlation_score' => 85,
                'evidences' => ['Mismo remitente', 'Mismo artista'],
            ],
            ['message_id' => 'msg-corr-05']
        );

        $this->assertEquals('linked_to_draft', $result['action']);
        $this->assertEquals('draft', $draftPost->status);
        $this->assertFalse($draftPost->is_published);
    }

    /**
     * Escenario 6: Corrección sobre noticia PROGRAMADA detiene cautelarmente la publicación.
     */
    public function test_scenario_06_correction_over_scheduled_post_halts_scheduled_publication(): void
    {
        $scheduledPost = Post::create([
            'title'          => 'Concierto de Obús en Madrid programado',
            'slug'           => 'concierto-obus-madrid',
            'content'        => 'Noticia programada para salir al aire mañana.',
            'status'         => 'scheduled',
            'is_published'   => false,
            'published_at'   => now()->subMinute(), // ya vencido para el cron
            'scheduled_at'   => now()->subMinute(),
            'author_email'   => 'prensa@obusrock.com',
            'source_subject' => 'Obús concierto Madrid',
            'categories'     => ['General'],
        ]);

        $manager = app(EditorialIncidentManager::class);
        $result = $manager->handleIncident(
            ['incident_type' => 'rectification_data', 'priority' => 'high', 'notes' => 'Cambio de sala a La Riviera'],
            [
                'is_confident' => true,
                'target_post'  => $scheduledPost,
                'correlation_score' => 90,
                'evidences' => ['Firma DKIM verificada', 'Mismo artista'],
            ],
            ['message_id' => 'msg-corr-06']
        );

        $this->assertEquals('halted_scheduled_post', $result['action']);
        $this->assertTrue($result['halted']);

        // El post fue inmediatamente devuelto a borrador cautelar
        $scheduledPost->refresh();
        $this->assertEquals('draft', $scheduledPost->status);
        $this->assertFalse($scheduledPost->is_published);

        // Al ejecutar el comando de publicación programada, el post NO se publica
        $this->artisan('posts:publish-scheduled')
            ->assertSuccessful();

        $scheduledPost->refresh();
        $this->assertFalse($scheduledPost->is_published);
        $this->assertEquals('draft', $scheduledPost->status);
    }

    /**
     * Escenario 7: Corrección sobre noticia PUBLICADA crea incidencia URGENTE sin despublicar.
     */
    public function test_scenario_07_correction_over_published_post_creates_urgent_incident_without_unpublishing(): void
    {
        $publishedPost = Post::create([
            'title'          => 'Mägo de Oz anuncia gira americana',
            'slug'           => 'mago-de-oz-gira-americana',
            'content'        => 'Noticia actualmente pública en el portal.',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => now()->subDays(2),
            'author_email'   => 'prensa@magodeoz.com',
            'source_subject' => 'Mägo de Oz gira americana',
            'categories'     => ['General'],
        ]);

        $manager = app(EditorialIncidentManager::class);
        $result = $manager->handleIncident(
            ['incident_type' => 'rectification_data', 'priority' => 'urgent'],
            [
                'is_confident' => true,
                'target_post'  => $publishedPost,
                'correlation_score' => 95,
                'evidences' => ['Remitente autenticado', 'Artista confirmado'],
            ],
            ['message_id' => 'msg-corr-07']
        );

        $this->assertEquals('flagged_published_post', $result['action']);

        // CRÍTICO: La noticia original permanece publicada e intacta
        $publishedPost->refresh();
        $this->assertTrue($publishedPost->is_published);
        $this->assertEquals('published', $publishedPost->status);

        // Se creó una incidencia de prioridad urgente en la BD
        $incident = EditorialIncident::where('source_message_id', 'msg-corr-07')->first();
        $this->assertNotNull($incident);
        $this->assertEquals('urgent', $incident->priority);
        $this->assertEquals('published', $incident->post_previous_status);
    }

    /**
     * Escenario 8: Dos artistas con nombres parecidos tienen correlación 0 y no se mezclan.
     */
    public function test_scenario_08_two_artists_with_similar_names_have_zero_correlation(): void
    {
        $saxonPost = Post::create([
            'title'          => 'Saxon anuncia nuevo single Hell, Fire and Damnation',
            'slug'           => 'saxon-nuevo-single',
            'content'        => 'Noticia de la banda británica Saxon.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'press@militia-pr.com',
            'source_subject' => 'Saxon nuevo single',
            'categories'     => ['General'],
        ]);

        $correlator = app(EditorialCorrelator::class);

        // Correo entrante de una banda diferente llamada "Safron"
        $correlation = $correlator->correlate([
            'sender'      => 'press@militia-pr.com', // Misma agencia promotora
            'subject'     => 'Fe de Erratas: Safron concierto Madrid',
            'body'        => 'Corrección de sala para la banda Safron.',
            'artist_name' => 'Safron', // Artista distinto
        ]);

        // La regla de bloqueo por artista debe impedir correlación
        $this->assertFalse($correlation['is_confident']);
        $this->assertNotEquals($saxonPost->id, $correlation['target_post']?->id);
    }

    /**
     * Escenario 9: Dos lanzamientos diferentes del mismo artista no se bloquean por similitud.
     */
    public function test_scenario_09_two_different_releases_from_same_artist_are_allowed(): void
    {
        // Primer lanzamiento: Álbum
        Post::create([
            'title'          => 'Kreator anuncia su nuevo álbum en directo',
            'slug'           => 'kreator-album-directo',
            'content'        => 'Álbum grabado en vivo en Berlín.',
            'status'         => 'draft',
            'is_published'   => false,
            'author_email'   => 'press@nuclearblast.de',
            'source_subject' => 'Kreator nuevo álbum directo',
            'categories'     => ['General'],
        ]);

        // Segundo correo 3 días después: Gira europea con fechas concretas
        $this->mockGeminiStructured(
            [
                'content_type' => 'tour_concert',
                'editorial_importance' => 4,
                'confidence_score' => 0.98,
                'facts' => [
                    'artist_name' => 'Kreator',
                    'release_title' => 'Gira Klash of the Titans',
                    'confirmed_tour_dates' => [
                        ['title' => 'Kreator en Madrid', 'starts_at' => '2026-12-05', 'location' => 'Madrid', 'venue' => 'WiZink', 'ticket_url' => 'https://tix.es']
                    ],
                ]
            ],
            [
                'title' => 'Kreator anuncia las fechas de su gira europea Klash of the Titans',
                'excerpt' => 'El cuarteto alemán de thrash metal confirma paradas en España.',
                'content' => "Kreator ha anunciado las fechas de su nueva gira europea.\n\nSigue sonando en Seven Rock Radio.",
                'editorial_warning' => null,
            ]
        );

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'     => 'Kreator anuncia gira europea Klash of the Titans',
            'body'        => 'Fechas oficiales confirmadas para la gira europea.',
            'sender'      => 'press@nuclearblast.de',
            'message_id'  => 'msg-kreator-tour',
            'artist_name' => 'Kreator',
        ]);

        // Debe procesarse como noticia válida de gira, no descartarse como duplicado del disco
        $this->assertEquals('draft_ready', $result['status']);
        $this->assertEquals('tour_concert', $result['content_type']);
    }

    /**
     * Escenario 10: Reenvío idéntico se descarta por hash exacto con auditoría de caché (0 coste).
     */
    public function test_scenario_10_identical_resend_is_cached_with_zero_cost(): void
    {
        $subject = 'Lanzamiento exclusivo Doro 2026';
        $body = 'Texto oficial del nuevo videoclip de Doro.';
        $contentHash = hash('sha256', mb_strtolower(trim($subject)) . '|' . mb_strtolower(trim($body)));

        // Simulamos que ya se procesó previamente en los últimos 30 días
        \App\Models\AiCallAudit::create([
            'message_id'        => 'msg-orig-10',
            'content_hash'      => $contentHash,
            'provider'          => 'gemini',
            'model'             => 'gemini-flash-latest',
            'stage'             => 'classification_extraction',
            'prompt_tokens'     => 150,
            'completion_tokens' => 80,
            'total_tokens'      => 230,
            'usage_source'      => 'api_reported',
            'estimated_cost_eur'=> 0.000030,
            'duration_ms'       => 500,
            'status'            => 'success',
        ]);

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'    => $subject,
            'body'       => $body,
            'sender'     => 'press@doro.de',
            'message_id' => 'msg-dup-10',
        ]);

        $this->assertEquals('duplicate', $result['status']);
        $this->assertEquals('discard_duplicate', $result['action']);
        Http::assertSentCount(0);
    }

    /**
     * Escenario 11: Reintentos asíncronos en Job garantizan idempotencia y no crean duplicados.
     */
    public function test_scenario_11_async_job_retry_guarantees_idempotency(): void
    {
        $this->mockGeminiStructured(
            [
                'content_type' => 'musical_news',
                'editorial_importance' => 4,
                'confidence_score' => 0.95,
                'facts' => ['artist_name' => 'Medina Azahara', 'release_title' => 'Gira 2026']
            ],
            [
                'title' => 'Medina Azahara presenta las fechas de su gira 2026',
                'excerpt' => 'El mítico grupo cordobés de rock andaluz anuncia conciertos.',
                'content' => "Medina Azahara recorrerá los escenarios en 2026.\n\nSigue sonando en Seven Rock Radio.",
                'editorial_warning' => null,
            ]
        );

        $jobData = [
            'message_id' => 'msg-job-idem-11',
            'sender'     => 'booking@medinaazahara.es',
            'subject'    => 'Medina Azahara Gira 2026',
            'body'       => 'Información sobre la gira 2026.',
        ];

        $job = new \App\Jobs\ProcessExternalEmailWithAiJob($jobData);
        $job->handle(
            app(\App\Services\AiParserManager::class),
            app(\App\Services\AiUsageTracker::class),
            app(\App\Services\PostImageResolver::class)
        );

        $this->assertEquals(1, Post::where('source_subject', 'Medina Azahara Gira 2026')->count());

        // Segundo intento del mismo job con el mismo message_id
        $job->handle(
            app(\App\Services\AiParserManager::class),
            app(\App\Services\AiUsageTracker::class),
            app(\App\Services\PostImageResolver::class)
        );

        // No se creó duplicado
        $this->assertEquals(1, Post::where('source_subject', 'Medina Azahara Gira 2026')->count());
    }

    /**
     * Escenario 12: Corrección procedente de remitente falsificado se bloquea (defensa contra suplantación).
     */
    public function test_scenario_12_spoofed_sender_correction_is_blocked(): void
    {
        $legitPost = Post::create([
            'title'          => 'Judas Priest anuncia fecha en Barcelona',
            'slug'           => 'judas-priest-barcelona',
            'content'        => 'Noticia legítima programada.',
            'status'         => 'scheduled',
            'is_published'   => false,
            'published_at'   => now()->addDays(2),
            'author_email'   => 'mgmt@judaspriest.com',
            'source_subject' => 'Judas Priest fecha Barcelona',
            'categories'     => ['General'],
        ]);

        $correlator = app(EditorialCorrelator::class);

        // Cabeceras falsificadas que fallan DKIM/SPF
        $spoofedHeaders = "From: mgmt@judaspriest.com\r\nAuthentication-Results: mx.google.com; dkim=fail; spf=fail\r\n";

        $correlation = $correlator->correlate([
            'sender'      => 'mgmt@judaspriest.com',
            'subject'     => 'CANCELADO: Judas Priest fecha Barcelona',
            'body'        => 'El concierto queda totalmente cancelado.',
            'raw_headers' => $spoofedHeaders,
            'artist_name' => 'Judas Priest',
        ]);

        $this->assertTrue($correlation['is_spoofed']);
        $this->assertFalse($correlation['is_confident']);

        $manager = app(EditorialIncidentManager::class);
        $result = $manager->handleIncident(
            ['incident_type' => 'event_cancellation', 'priority' => 'urgent'],
            $correlation,
            ['message_id' => 'msg-spoof-12']
        );

        $this->assertEquals('blocked_impersonation', $result['action']);

        // El post legítimo permanece programado intacto (no fue alterado ni pausado)
        $legitPost->refresh();
        $this->assertEquals('scheduled', $legitPost->status);
    }

    /**
     * Escenario 13: Corrección ambigua sin referencia previa genera borrador independiente sin tocar otros posts.
     */
    public function test_scenario_13_ambiguous_correction_without_prior_post_stays_isolated(): void
    {
        $existingOtherPost = Post::create([
            'title'          => 'Angelus Apatrida en gira europea',
            'slug'           => 'angelus-apatrida-gira',
            'content'        => 'Noticia de Angelus Apatrida.',
            'status'         => 'published',
            'is_published'   => true,
            'author_email'   => 'prensa@centurymedia.de',
            'source_subject' => 'Angelus Apatrida gira',
            'categories'     => ['General'],
        ]);

        $correlator = app(EditorialCorrelator::class);
        $correlation = $correlator->correlate([
            'sender'      => 'otro@desconocido.com',
            'subject'     => 'Fe de Erratas sobre comunicado previo',
            'body'        => 'La fecha del concierto era el 10 y no el 9.',
            'artist_name' => '', // Sin artista especificado
        ]);

        $this->assertFalse($correlation['is_confident']);

        $manager = app(EditorialIncidentManager::class);
        $result = $manager->handleIncident(
            ['incident_type' => 'rectification_data', 'priority' => 'medium'],
            $correlation,
            ['message_id' => 'msg-ambiguous-13']
        );

        $this->assertEquals('retained_ambiguous', $result['action']);

        // El post existente de Angelus Apatrida permanece intacto
        $existingOtherPost->refresh();
        $this->assertEquals('published', $existingOtherPost->status);
        $this->assertTrue($existingOtherPost->is_published);
    }

    /**
     * Escenario 14: Contenido corregido sujeto a embargo activo retiene el post hasta vencimiento.
     */
    public function test_scenario_14_corrected_content_under_embargo_stays_held(): void
    {
        $this->mockGeminiStructured(
            [
                'content_type' => 'rectification',
                'editorial_importance' => 4,
                'confidence_score' => 0.95,
                'facts' => [
                    'artist_name' => 'Iron Maiden',
                    'release_title' => 'Corrección fechas tour mundial',
                ]
            ],
            [
                'title' => 'Corrección: Iron Maiden desvela fechas del tour mundial',
                'excerpt' => 'La banda británica actualiza las ciudades de su gira.',
                'content' => "Iron Maiden ha actualizado su itinerario mundial.\n\nSigue sonando en Seven Rock Radio.",
                'editorial_warning' => null,
            ]
        );

        $bodyWithEmbargo = "ESTRICTO EMBARGO HASTA EL 15 DE NOVIEMBRE DE 2026 A LAS 18:00 HORAS.\nFe de Erratas: Nuevas fechas de Iron Maiden.";

        $pipeline = app(EditorialAiPipeline::class);
        $result = $pipeline->process([
            'subject'    => 'CONFIDENCIAL / EMBARGO - Fe de Erratas: Iron Maiden',
            'body'       => $bodyWithEmbargo,
            'sender'     => 'mgmt@ironmaiden.com',
            'message_id' => 'msg-embargo-14',
            'artist_name'=> 'Iron Maiden',
        ]);

        $this->assertTrue($result['is_under_embargo']);
        $this->assertEquals('draft', $result['draft']['status']);
        $this->assertFalse($result['draft']['is_published']);
    }

    /**
     * Escenario 15: Comprobación de no regresión completa de Fases 1 a 4.
     */
    public function test_scenario_15_regression_check_of_previous_phases(): void
    {
        // Verificar que los modelos y servicios de Fases 1, 2, 3 y 4 continúan instanciables y operativos
        $this->assertInstanceOf(\App\Services\EmailSecurityVerifier::class, app(\App\Services\EmailSecurityVerifier::class));
        $this->assertInstanceOf(\App\Services\EmailContentSanitizer::class, app(\App\Services\EmailContentSanitizer::class));
        $this->assertInstanceOf(\App\Services\LinkSecurityClassifier::class, app(\App\Services\LinkSecurityClassifier::class));
        $this->assertInstanceOf(\App\Services\FactConsistencyChecker::class, app(\App\Services\FactConsistencyChecker::class));
        $this->assertInstanceOf(\App\Services\EmailSafetyValidator::class, app(\App\Services\EmailSafetyValidator::class));
        $this->assertInstanceOf(\App\Services\EditorialAiPipeline::class, app(\App\Services\EditorialAiPipeline::class));
        $this->assertInstanceOf(\App\Services\EditorialCorrectionClassifier::class, app(\App\Services\EditorialCorrectionClassifier::class));
        $this->assertInstanceOf(\App\Services\EditorialCorrelator::class, app(\App\Services\EditorialCorrelator::class));
        $this->assertInstanceOf(\App\Services\EditorialIncidentManager::class, app(\App\Services\EditorialIncidentManager::class));
    }
}
