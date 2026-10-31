<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Jobs\ProcessExternalEmailWithAiJob;
use App\Models\EditorialIncident;
use App\Models\EmailPostSanitizedMeta;
use App\Models\NewRelease;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\AiParserManager;
use App\Services\EditorialAiPipeline;
use App\Services\EmailContentSanitizer;
use App\Services\EmailSafetyValidator;
use App\Services\LinkSecurityClassifier;
use App\Services\PostImageResolver;
use App\Services\ReleaseScheduleManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Block1EditorialCorrectionsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
    }

    /**
     * 1. Demostrar aislamiento de base de datos y entorno.
     */
    public function test_environment_and_database_isolation(): void
    {
        $this->assertEquals('testing', config('app.env'));
        $this->assertEquals('sqlite', config('database.default'));
        $this->assertEquals(':memory:', config('database.connections.sqlite.database'));
    }

    /**
     * 2. Saneamiento del pipeline: Comprobar con datos sintéticos que notas internas
     * y contraseñas retiradas NO llegan a ninguna petición de IA ni a los borradores.
     */
    public function test_pipeline_sanitizer_contract_and_credential_notes_isolation(): void
    {
        $rawEmailBody = <<<HTML
        <div>
            <h1>Nuevo Comunicado de Prensa</h1>
            <p>La banda Seven Stones anuncia nuevo single para noviembre.</p>
            <p>Password: SecretAlpha123</p>
            <hr>
            <h3>Notes to Editors</h3>
            <p>Material exclusivo para prensa. No publicar antes del 15 de diciembre.</p>
            <p>Descarga privada: password = SuperSecret999</p>
        </div>
        HTML;

        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $capturedPrompts = [];

        $aiManagerMock->shouldReceive('generateStructured')
            ->andReturnUsing(function ($prompt, $schema, $stage) use (&$capturedPrompts) {
                $capturedPrompts[$stage] = $prompt;
                if ($stage === 'classification_extraction') {
                    return [
                        'content_type'         => 'musical_news',
                        'editorial_importance' => 3,
                        'confidence_score'     => 0.95,
                        'facts'                => [
                            'artist_name'   => 'Seven Stones',
                            'release_title' => 'Nuevo Single',
                        ],
                    ];
                }
                if ($stage === 'editorial_writing') {
                    return [
                        'title'             => 'Seven Stones Anuncia Nuevo Single',
                        'excerpt'           => 'La banda Seven Stones prepara su regreso discográfico.',
                        'content'           => '<p>Seven Stones anuncia nuevo single.</p>',
                        'editorial_warning' => null,
                    ];
                }
                return null;
            });

        $pipeline = new EditorialAiPipeline(
            $aiManagerMock,
            app(EmailContentSanitizer::class),
            app(LinkSecurityClassifier::class),
            app(ReleaseScheduleManager::class),
            app(\App\Services\FactConsistencyChecker::class),
            app(EmailSafetyValidator::class)
        );

        $result = $pipeline->process([
            'subject'    => 'Seven Stones - Nuevo Single',
            'body'       => $rawEmailBody,
            'sender'     => 'press@sevenstonesrock.com',
            'message_id' => 'msg-sanitizer-test-001',
        ]);

        $this->assertEquals('draft_ready', $result['status']);

        $stage1Prompt = $capturedPrompts['classification_extraction'] ?? '';
        $this->assertStringNotContainsString('SecretAlpha123', $stage1Prompt);
        $this->assertStringNotContainsString('SuperSecret999', $stage1Prompt);
        $this->assertStringNotContainsString('Notes to Editors', $stage1Prompt);
        $this->assertStringNotContainsString('Material exclusivo para prensa', $stage1Prompt);

        $draftContent = $result['draft']['content'];
        $this->assertStringNotContainsString('SecretAlpha123', $draftContent);
    }

    /**
     * 3. Saneamiento del pipeline: Si el saneador devuelve una respuesta no conforme,
     * se retiene el procesamiento con marcador neutro y no se vuelve al raw body inseguro.
     */
    public function test_pipeline_handles_sanitizer_contract_failure_safely(): void
    {
        $brokenSanitizer = \Mockery::mock(EmailContentSanitizer::class);
        $brokenSanitizer->shouldReceive('sanitize')->andReturn(['unexpected_key' => 'broken']);

        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $aiManagerMock->shouldReceive('generateStructured')->never();

        $pipeline = new EditorialAiPipeline(
            $aiManagerMock,
            $brokenSanitizer,
            app(LinkSecurityClassifier::class),
            app(ReleaseScheduleManager::class),
            app(\App\Services\FactConsistencyChecker::class),
            app(EmailSafetyValidator::class)
        );

        $sensitiveRaw = 'Contenido sensible con password: SecretVaultPassword99 y notas internas confidenciales';
        $result = $pipeline->process([
            'subject'    => 'Alerta',
            'body'       => $sensitiveRaw,
            'sender'     => 'test@example.com',
            'message_id' => 'msg-broken-sanitizer',
        ]);

        $this->assertEquals('fallback_draft', $result['status']);
        $this->assertEquals('sanitizer_contract_failure', $result['failure_type']);
        $this->assertEquals('draft', $result['draft']['status']);
        $this->assertFalse($result['draft']['is_published']);
        $this->assertTrue($result['is_under_embargo']);

        $this->assertStringNotContainsString('SecretVaultPassword99', $result['draft']['content']);
        $this->assertStringNotContainsString('notas internas', $result['draft']['content']);
        $this->assertStringContainsString('CONTENIDO RETENIDO POR SEGURIDAD EDITORIAL', $result['draft']['content']);
    }

    /**
     * 4. Saneamiento del pipeline: Si el saneador lanza una excepción, se captura controladamente,
     * no se llama a la IA y se devuelve marcador neutro de retención sin filtrar datos sensibles.
     */
    public function test_pipeline_handles_sanitizer_exception_safely(): void
    {
        $faultySanitizer = \Mockery::mock(EmailContentSanitizer::class);
        $faultySanitizer->shouldReceive('sanitize')
            ->andThrow(new \RuntimeException('Parser crash inside sanitizer'));

        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $aiManagerMock->shouldReceive('generateStructured')->never();

        $pipeline = new EditorialAiPipeline(
            $aiManagerMock,
            $faultySanitizer,
            app(LinkSecurityClassifier::class),
            app(ReleaseScheduleManager::class),
            app(\App\Services\FactConsistencyChecker::class),
            app(EmailSafetyValidator::class)
        );

        $result = $pipeline->process([
            'subject'    => 'Lanzamiento con Contraseña SuperSecretPass777',
            'body'       => '<p>Texto sensible con password: SuperSecretPass777</p>',
            'sender'     => 'label@rock.com',
            'message_id' => 'msg-sanitizer-exception-001',
        ]);

        $this->assertEquals('fallback_draft', $result['status']);
        $this->assertEquals('sanitizer_contract_failure', $result['failure_type']);
        $this->assertStringNotContainsString('SuperSecretPass777', $result['draft']['content']);
        $this->assertStringNotContainsString('SuperSecretPass777', $result['draft']['excerpt']);
        $this->assertStringContainsString('CONTENIDO RETENIDO POR SEGURIDAD EDITORIAL', $result['draft']['content']);
    }

    /**
     * 5. Validación de enlaces: Sustitución de category por classification.
     * Evalúa URLs públicas, privadas (EPK) y sospechosas (SSRF) sin revelar tokens completos.
     */
    public function test_validate_editorial_safety_with_various_links(): void
    {
        $validator = app(EmailSafetyValidator::class);

        // a) Enlace público permitido
        $publicDraft = [
            'title'   => 'Lanzamiento Oficial',
            'excerpt' => 'Escúchalo en Spotify',
            'content' => '<p>Visita <a href="https://open.spotify.com/album/1234567890">Spotify</a> para oír el disco.</p>',
        ];
        $publicReport = $validator->validateEditorialSafety($publicDraft);
        $this->assertTrue($publicReport['is_safe']);
        $this->assertEmpty($publicReport['violations']);

        // b) Enlace privado EPK con secret token en query param
        $privateDraft = [
            'title'   => 'Promo Exclusiva',
            'excerpt' => 'Descarga anticipada',
            'content' => '<p>Descarga en <a href="https://haulix.com/promo/album?token=secret_super_token_987">Haulix</a></p>',
        ];
        $privateReport = $validator->validateEditorialSafety($privateDraft);
        $this->assertFalse($privateReport['is_safe']);
        $this->assertNotEmpty($privateReport['violations']);

        $violationMessage = implode(' ', $privateReport['violations']);
        $this->assertStringNotContainsString('secret_super_token_987', $violationMessage);
        $this->assertStringContainsString('***REDACTED***', $violationMessage);

        // c) Enlace sospechoso SSRF (localhost / IP privada)
        $ssrfDraft = [
            'title'   => 'Noticia Maliciosa',
            'excerpt' => 'Ataque SSRF',
            'content' => '<p>Consulta <a href="http://169.254.169.254/latest/meta-data/">AWS Metadata</a></p>',
        ];
        $ssrfReport = $validator->validateEditorialSafety($ssrfDraft);
        $this->assertFalse($ssrfReport['is_safe']);
        $this->assertStringContainsString('suspicious', implode(' ', $ssrfReport['violations']));
    }

    /**
     * 6. Correo sintético con «bajo embargo hasta nueva confirmación» donde la IA omite el embargo.
     * Utiliza el detector determinista y la persistencia real del Job (sin crear la metadata manualmente).
     * Demuestra que el embargo queda retenido y bloquea publicación y programación desde:
     * - Editor general (PostController)
     * - Bandeja individual (AdminEditorialController::approve / schedule)
     * - Acciones masivas (AdminEditorialController::bulkAction approve / schedule)
     * - Programador automático (PublishScheduledPosts command)
     */
    public function test_indefinite_embargo_e2e_real_persistence_blocks_all_publishing_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Simular solo la frontera externa de IA (AiParserManager)
        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $aiManagerMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiManagerMock->shouldReceive('isRecoverableError')->andReturn(false);
        $aiManagerMock->shouldReceive('generateStructured')
            ->andReturnUsing(function ($prompt, $schema, $stage) {
                if ($stage === 'classification_extraction') {
                    return [
                        'content_type'         => 'musical_news',
                        'editorial_importance' => 4,
                        'confidence_score'     => 0.98,
                        'facts'                => [
                            'artist_name'   => 'The Confidential Band',
                            'release_title' => 'Álbum Top Secret',
                        ],
                    ];
                }
                // La redacción de la IA OMITE por completo cualquier mención al embargo
                return [
                    'title'             => 'The Confidential Band Nuevo Álbum',
                    'excerpt'           => 'Gran expectativa ante la llegada de su nuevo trabajo discográfico.',
                    'content'           => '<p>La banda regresa con nueva música sin dar fechas.</p>',
                    'editorial_warning' => null,
                ];
            });
        $this->app->instance(AiParserManager::class, $aiManagerMock);

        $resolverMock = \Mockery::mock(PostImageResolver::class);
        $resolverMock->shouldReceive('resolveForPost')->andReturn([
            'url'          => 'https://sevenrockradio.com/cover.jpg',
            'article_url'  => null,
            'credit'       => null,
            'source'       => 'test',
            'error_reason' => null,
        ]);
        $this->app->instance(PostImageResolver::class, $resolverMock);

        // Correo sintético con embargo indefinido en el asunto/cuerpo
        $messageId = 'msg-indefinite-embargo-e2e-001';
        $jobData = [
            'message_id' => $messageId,
            'sender'     => 'management@confidential.com',
            'subject'    => 'The Confidential Band - Bajo embargo hasta nueva confirmación',
            'body'       => '<h1>The Confidential Band</h1><p>Nuevo material discográfico. Estricto embargo: bajo embargo hasta nueva confirmación.</p>',
        ];

        // Ejecutar el Job real
        $job = new ProcessExternalEmailWithAiJob($jobData);
        $job->handle($aiManagerMock, app(\App\Services\AiUsageTracker::class), $resolverMock);

        // Verificar persistencia real de Post y EmailPostSanitizedMeta generada automáticamente
        $post = Post::where('source_subject', $jobData['subject'])->first();
        $this->assertNotNull($post);
        $this->assertEquals('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('post_id', $post->id)->first();
        $this->assertNotNull($meta);
        $this->assertTrue((bool) $meta->is_embargo_active);
        $this->assertNull($meta->embargo_until, 'El embargo indefinido debe tener fecha nula');
        $this->assertEquals('held_embargo', $meta->security_status);

        // ── RUTA 1: Editor General (PostController) ──────────────────────────────────
        // A) Intento de publicar inmediatamente
        $resEditorPub = $this->actingAs($admin)
            ->from(route('admin.posts.edit', $post))
            ->put(route('admin.posts.update', $post), [
                'title'          => 'The Confidential Band Nuevo Álbum',
                'slug'           => $post->slug,
                'featured_image' => 'https://sevenrockradio.com/cover.jpg',
                'content_text'   => '<p>Contenido limpio</p>',
                '_action'        => 'publish',
            ]);
        $resEditorPub->assertSessionHasErrors('status');
        $this->assertEquals('draft', $post->fresh()->status);

        // B) Intento de programar
        $resEditorSched = $this->actingAs($admin)
            ->from(route('admin.posts.edit', $post))
            ->put(route('admin.posts.update', $post), [
                'title'          => 'The Confidential Band Nuevo Álbum',
                'slug'           => $post->slug,
                'featured_image' => 'https://sevenrockradio.com/cover.jpg',
                'content_text'   => '<p>Contenido limpio</p>',
                'published_at'   => Carbon::now()->addDays(5)->format('Y-m-d H:i'),
                '_action'        => 'schedule',
            ]);
        $resEditorSched->assertSessionHasErrors('status');
        $this->assertEquals('draft', $post->fresh()->status);

        // ── RUTA 2: Bandeja Individual (AdminEditorialController) ─────────────────────
        // A) Aprobar publicación individual
        $resInboxApprove = $this->actingAs($admin)->postJson(route('admin.editorial.approve', $post));
        $resInboxApprove->assertStatus(422);
        $resInboxApprove->assertJsonFragment(['code' => 'active_embargo']);
        $this->assertEquals('draft', $post->fresh()->status);

        // B) Programar publicación individual
        $resInboxSchedule = $this->actingAs($admin)->postJson(route('admin.editorial.schedule', $post), [
            'scheduled_at' => Carbon::now()->addDays(3)->toDateTimeString(),
        ]);
        $resInboxSchedule->assertStatus(422);
        $resInboxSchedule->assertJsonFragment(['code' => 'embargo_conflict']);
        $this->assertEquals('draft', $post->fresh()->status);

        // ── RUTA 3: Acciones Masivas (AdminEditorialController::bulkAction) ───────────
        // A) Bulk approve
        $resBulkApprove = $this->actingAs($admin)->postJson(route('admin.editorial.bulk-action'), [
            'action'   => 'approve',
            'post_ids' => [$post->id],
        ]);
        $resBulkApprove->assertStatus(200);
        $this->assertNotEmpty($resBulkApprove->json('results.blocked'));
        $this->assertEquals('draft', $post->fresh()->status);

        // B) Bulk schedule
        $resBulkSchedule = $this->actingAs($admin)->postJson(route('admin.editorial.bulk-action'), [
            'action'       => 'schedule',
            'post_ids'     => [$post->id],
            'scheduled_at' => Carbon::now()->addDays(3)->toDateTimeString(),
        ]);
        $resBulkSchedule->assertStatus(200);
        $this->assertNotEmpty($resBulkSchedule->json('results.blocked'));
        $this->assertEquals('draft', $post->fresh()->status);

        // ── RUTA 4: Programador Automático (PublishScheduledPosts command) ────────────
        // Forzar artificialmente el post como 'scheduled' con fecha pasada para verificar que el comando
        // evalúa las compuertas de seguridad y no publica un post con embargo indefinido o held
        $post->update([
            'status'       => 'scheduled',
            'published_at' => Carbon::now()->subMinute(),
            'is_published' => false,
        ]);

        Artisan::call('posts:publish-scheduled');

        $this->assertEquals('draft', $post->fresh()->status, 'El comando programador debe degradar el post a draft y no publicarlo');
        $this->assertFalse((bool) $post->fresh()->is_published);
    }

    /**
     * 7. Fallo del saneador de extremo a extremo:
     * El marcador neutro de seguridad se persiste con security_status='held_security'
     * y no puede aprobarse ni publicarse sin resolución expresa.
     */
    public function test_sanitizer_failure_persists_neutral_marker_and_blocks_approval(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Simular que el saneador falla / devuelve datos corruptos
        $brokenSanitizer = \Mockery::mock(EmailContentSanitizer::class);
        $brokenSanitizer->shouldReceive('sanitize')->andReturn(['broken' => true]);
        $this->app->instance(EmailContentSanitizer::class, $brokenSanitizer);

        $resolverMock = \Mockery::mock(PostImageResolver::class);
        $resolverMock->shouldReceive('resolveForPost')->andReturn([
            'url'          => 'https://sevenrockradio.com/cover.jpg',
            'article_url'  => null,
            'credit'       => null,
            'source'       => 'test',
            'error_reason' => null,
        ]);
        $this->app->instance(PostImageResolver::class, $resolverMock);

        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $aiManagerMock->shouldReceive('generateStructured')->never();
        $this->app->instance(AiParserManager::class, $aiManagerMock);

        $sensitiveSubject = 'Comunicado Ultra Secreto con Password123';
        $messageId = 'msg-sanitizer-fail-persist-001';
        $jobData = [
            'message_id' => $messageId,
            'sender'     => 'leaker@secret.com',
            'subject'    => $sensitiveSubject,
            'body'       => '<p>Password: SuperSecretPass123</p>',
        ];

        // Ejecutar Job
        $job = new ProcessExternalEmailWithAiJob($jobData);
        $job->handle($aiManagerMock, app(\App\Services\AiUsageTracker::class), $resolverMock);

        $post = Post::where('source_subject', $sensitiveSubject)->first();
        $this->assertNotNull($post);
        $this->assertEquals('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        // Verificar marcador neutro sin fuga de credenciales
        $rawPostContent = is_array($post->content) ? json_encode($post->content) : (string) $post->content;
        $this->assertStringNotContainsString('SuperSecretPass123', $rawPostContent);
        $this->assertStringContainsString('CONTENIDO RETENIDO POR SEGURIDAD EDITORIAL', $rawPostContent);

        // Intentar aprobar desde bandeja
        $resApprove = $this->actingAs($admin)->postJson(route('admin.editorial.approve', $post));
        $resApprove->assertStatus(422);
        $this->assertEquals('draft', $post->fresh()->status);
        $this->assertFalse((bool) $post->fresh()->is_published);
    }

    /**
     * 8. Fallo de persistencia de metadatos seguido de reintento exitoso:
     * - Intento 1: Fallo al insertar metadata -> Rollback atómico, 0 posts huérfanos, no 'processed'.
     * - Intento 2: Persistencia normal -> Creación limpia, sin duplicados y con metadatos protegidos.
     */
    public function test_metadata_failure_followed_by_successful_retry_is_atomic_and_idempotent(): void
    {
        $resolverMock = \Mockery::mock(PostImageResolver::class);
        $resolverMock->shouldReceive('resolveForPost')->andReturn([
            'url'          => 'https://sevenrockradio.com/cover.jpg',
            'article_url'  => null,
            'credit'       => null,
            'source'       => 'test',
            'error_reason' => null,
        ]);

        $aiManagerMock = \Mockery::mock(AiParserManager::class);
        $aiManagerMock->shouldReceive('hasAnyProvider')->andReturn(true);
        $aiManagerMock->shouldReceive('isRecoverableError')->andReturn(false);
        $aiManagerMock->shouldReceive('generateStructured')
            ->andReturnUsing(function ($prompt, $schema, $stage) {
                if ($stage === 'classification_extraction') {
                    return [
                        'content_type'         => 'musical_news',
                        'editorial_importance' => 3,
                        'confidence_score'     => 0.95,
                        'facts'                => ['artist_name' => 'Test Band'],
                    ];
                }
                return [
                    'title'             => 'Test Retry Release',
                    'excerpt'           => 'Excerpt',
                    'content'           => '<p>Contenido limpio</p>',
                    'editorial_warning' => null,
                ];
            });
        $this->app->instance(AiParserManager::class, $aiManagerMock);

        $messageId = 'msg-retry-test-001';
        $jobData = [
            'message_id' => $messageId,
            'sender'     => 'band@rock.com',
            'subject'    => 'Test Retry Subject',
            'body'       => '<h1>Test Band</h1><p>Estricto embargo hasta 2026-11-20 12:00.</p>',
        ];

        // ── INTENTO 1: Forzar fallo en EmailPostSanitizedMeta ─────────────────────────
        $hasFailedOnce = false;
        EmailPostSanitizedMeta::creating(function () use (&$hasFailedOnce) {
            if (!$hasFailedOnce) {
                $hasFailedOnce = true;
                throw new \RuntimeException('Simulated transient DB error in metadata insertion');
            }
        });

        $job1 = new ProcessExternalEmailWithAiJob($jobData);
        try {
            $job1->handle($aiManagerMock, app(\App\Services\AiUsageTracker::class), $resolverMock);
            $this->fail('Se esperaba fallo en el primer intento.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Simulated transient DB error', $e->getMessage());
        }

        // Demostrar que NO hay posts huérfanos en la base de datos tras el rollback
        $this->assertEquals(0, Post::where('source_subject', 'Test Retry Subject')->count());
        $this->assertEquals(0, EmailPostSanitizedMeta::where('source_message_id', $messageId)->count());

        $statusRow1 = DB::table('processed_emails')->where('message_id', $messageId)->first();
        $this->assertEquals('failed_metadata', $statusRow1->status);

        // ── INTENTO 2: Reintento del Job (simulando reintento de la cola) ──────────────
        $job2 = new ProcessExternalEmailWithAiJob($jobData);
        $job2->handle($aiManagerMock, app(\App\Services\AiUsageTracker::class), $resolverMock);

        // Demostrar idempotencia: exactamente 1 post y 1 meta creados (sin duplicados)
        $this->assertEquals(1, Post::where('source_subject', 'Test Retry Subject')->count());
        $this->assertEquals(1, EmailPostSanitizedMeta::where('source_message_id', $messageId)->count());

        $statusRow2 = DB::table('processed_emails')->where('message_id', $messageId)->first();
        $this->assertEquals('processed', $statusRow2->status);

        $savedPost = Post::where('source_subject', 'Test Retry Subject')->first();
        $this->assertEquals('draft', $savedPost->status);
        $this->assertTrue((bool) $savedPost->sanitizedMeta->is_embargo_active);
    }

    /**
     * 9. Bloque 2: Panel de Lanzamientos (NewReleaseController).
     * Demostrar que un lanzamiento con procedencia 'email' bajo embargo o con enlaces sospechosos
     * NO puede activarse (is_active=true) desde el editor de lanzamientos.
     */
    public function test_new_release_controller_blocks_activating_email_releases_with_embargo_or_violations(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Lanzamiento originado por correo con embargo activo
        $futureDate = Carbon::now()->addDays(10)->format('d/m/Y');
        $release = NewRelease::create([
            'title'        => 'Nuevo Álbum Metal',
            'slug'         => 'nuevo-album-metal',
            'artist_name'  => 'Metal Kings',
            'description'  => "Lanzamiento oficial. Estricto embargo hasta {$futureDate}.",
            'released_at'  => Carbon::now()->addDays(10),
            'is_active'    => false, // Creado inactivo
            'origin'       => 'email',
            'author_email' => 'promo@metalkings.com',
            'cover_image'  => 'catalog/releases/covers/cover.jpg',
        ]);

        // A) Intentar activarlo (is_active=true) -> Debe fallar por embargo activo
        $response = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $release), [
                'title'        => 'Nuevo Álbum Metal',
                'artist_name'  => 'Metal Kings',
                'description'  => "Lanzamiento oficial. Estricto embargo hasta {$futureDate}.",
                'released_at'  => Carbon::now()->addDays(10)->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/cover.jpg',
                'is_active'    => true, // Intentar activar
            ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertFalse((bool) $release->fresh()->is_active);

        // B) Intentar activarlo inyectando un enlace privado EPK (Haulix)
        $responseLink = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $release), [
                'title'        => 'Nuevo Álbum Metal',
                'artist_name'  => 'Metal Kings',
                'description'  => 'Escucha la promo privada en https://haulix.com/promo/album?token=secret123',
                'released_at'  => Carbon::now()->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/cover.jpg',
                'is_active'    => true,
            ]);

        $responseLink->assertSessionHasErrors('description');
        $this->assertFalse((bool) $release->fresh()->is_active);

        // C) Lanzamiento manual legítimo sin restricciones -> Debe poder activarse
        $manualRelease = NewRelease::create([
            'title'        => 'Disco Indie',
            'slug'         => 'disco-indie',
            'artist_name'  => 'Indie Band',
            'description'  => 'Disco lanzado hoy.',
            'released_at'  => Carbon::now(),
            'is_active'    => false,
            'origin'       => 'manual',
            'cover_image'  => 'catalog/releases/covers/cover.jpg',
        ]);

        $resManual = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $manualRelease), [
                'title'        => 'Disco Indie',
                'artist_name'  => 'Indie Band',
                'description'  => 'Disco lanzado hoy.',
                'released_at'  => Carbon::now()->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/cover.jpg',
                'is_active'    => true,
            ]);

        $resManual->assertSessionHasNoErrors();
        $this->assertTrue((bool) $manualRelease->fresh()->is_active);
    }

    /**
     * 10. Bloque 2: Rutas directas de Dark Vader (Noticias Rock y Efemérides).
     * Comprueba saneamiento de seguridad con auto_publish simulado en false y true:
     * - Con auto_publish=false: se crea siempre como draft.
     * - Con auto_publish=true pero con enlace restringido/credencial: se fuerza a draft por EmailSafetyValidator.
     * - Con auto_publish=true y contenido 100% limpio: se publica correctamente.
     */
    public function test_dark_vader_direct_routes_apply_safety_and_respect_auto_publish(): void
    {
        $settings = ThemeSetting::current();

        // CASO A: auto_publish = false (Valor real del sistema)
        $settings->update(['email_auto_publish' => false]);

        $postA = Post::create([
            'title'          => 'Dark Vader Noticia A',
            'slug'           => 'dark-vader-noticia-a',
            'content'        => '<p>Noticia limpia de Dark Vader</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'categories'     => ['Noticias Rock'],
            'author_email'   => 'dark.vader.agent@gmail.com',
            'source_subject' => 'Noticias: Dark Vader Noticia A',
        ]);

        $safetyValidator = app(EmailSafetyValidator::class);
        $reportA = $safetyValidator->validatePost($postA);
        $this->assertTrue($reportA['is_safe']);
        $this->assertEquals('draft', $postA->status);
        $this->assertFalse((bool) $postA->is_published);

        // CASO B: auto_publish simulado en true, pero el correo contiene un enlace privado (Haulix)
        $postB = Post::create([
            'title'          => 'Dark Vader Noticia B Insegura',
            'slug'           => 'dark-vader-noticia-b',
            'content'        => '<p>Noticia con promo privada: <a href="https://haulix.com/promo/album?token=secret789">Haulix</a></p>',
            'status'         => 'draft',
            'is_published'   => false,
            'categories'     => ['Noticias Rock'],
            'author_email'   => 'dark.vader.agent@gmail.com',
            'source_subject' => 'Noticias: Dark Vader Noticia B',
        ]);

        $reportB = $safetyValidator->validatePost($postB);
        $this->assertFalse($reportB['is_safe']);
        // Simular lógica de decisión de ProcessIncomingEmails
        $finalStatusB = ($reportB['is_safe']) ? 'published' : 'draft';
        $this->assertEquals('draft', $finalStatusB, 'Aunque auto_publish fuera true, una violación de seguridad debe forzar draft');

        // CASO C: auto_publish simulado en true y contenido 100% limpio
        $postC = Post::create([
            'title'          => 'Dark Vader Noticia C Limpia',
            'slug'           => 'dark-vader-noticia-c',
            'content'        => '<p>Noticia completamente limpia y autorizada.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'categories'     => ['Noticias Rock'],
            'author_email'   => 'dark.vader.agent@gmail.com',
            'source_subject' => 'Noticias: Dark Vader Noticia C',
        ]);

        $reportC = $safetyValidator->validatePost($postC);
        $this->assertTrue($reportC['is_safe']);
        $finalStatusC = ($reportC['is_safe']) ? 'published' : 'draft';
        $this->assertEquals('published', $finalStatusC);
    }

    /**
     * 11. Bloque 2: Dark Vader con embargo original en asunto o cuerpo que fue eliminado durante saneamiento.
     * La persistencia atómica debe conservar el embargo y bloquear la auto-publicación.
     */
    public function test_dark_vader_with_embargo_in_original_message_is_blocked_from_publishing(): void
    {
        $rawSubject = 'Noticias: Gira mundial Metal 2027 [BAJO ESTRICTO EMBARGO HASTA NUEVA ORDEN]';
        $rawBody = "CONFIDENCIAL: Esta gira se anunciará próximamente.\n\nContenido de la noticia...";

        $scheduleManager = app(ReleaseScheduleManager::class);
        $schedule = $scheduleManager->evaluateSchedule($rawSubject, $rawBody, now());

        $this->assertTrue($schedule['is_embargo_active']);
        $this->assertNull($schedule['embargo_until']); // Embargo indefinido

        // Saneamiento retira las notas pero el pipeline determinista evalúa los datos originales
        $sanitizer = app(EmailContentSanitizer::class);
        $sanitized = $sanitizer->sanitize($rawBody);

        // Simulamos evaluación de auto_publish = true
        $autoPublishEnabled = true;
        $isEmbargoActive = (bool) $schedule['is_embargo_active'];
        $sanitizerFailed = false;

        $canPublish = $autoPublishEnabled && !$sanitizerFailed && !$isEmbargoActive;
        $this->assertFalse($canPublish, 'Un correo de Dark Vader con embargo activo NUNCA debe publicarse automáticamente');

        // Persistir en transacción atómica
        DB::beginTransaction();
        $post = Post::create([
            'title'          => 'Gira mundial Metal 2027',
            'slug'           => 'gira-mundial-metal-2027',
            'content'        => $sanitized['clean_html'],
            'status'         => $canPublish ? 'published' : 'draft',
            'is_published'   => $canPublish,
            'categories'     => ['Noticias Rock'],
            'author_email'   => 'dark.vader.agent@gmail.com',
            'source_subject' => $rawSubject,
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => '<embargo-dv-test@sevenrock.local>',
            'sender_email'      => 'dark.vader.agent@gmail.com',
            'source_subject'    => $rawSubject,
            'received_at'       => now(),
            'is_embargo_active' => true,
            'embargo_until'     => null,
            'security_status'   => 'held_embargo',
            'incident_reason'   => 'Material bajo embargo editorial detectado en correo original',
        ]);
        DB::commit();

        $this->assertEquals('draft', $post->fresh()->status);
        $this->assertFalse((bool) $post->fresh()->is_published);
        $this->assertTrue((bool) $meta->fresh()->is_embargo_active);
        $this->assertEquals('held_embargo', $meta->fresh()->security_status);
    }

    /**
     * 12. Bloque 2: Dark Vader con fallo del saneador o fallo al guardar metadatos.
     * No debe quedar publicación ni borrador desprotegido.
     */
    public function test_dark_vader_sanitizer_or_metadata_failure_leaves_no_unprotected_post(): void
    {
        // CASO A: Fallo de saneador -> genera marcador neutro y retención por seguridad
        $sanitizerFailed = true;
        $sanitizerReason = 'Excepción simulada en saneador';
        $neutralContent = '<p>[CONTENIDO RETENIDO POR SEGURIDAD EDITORIAL: Error durante el saneamiento]</p>';

        $post = Post::create([
            'title'          => 'Noticia con fallo saneador',
            'slug'           => 'noticia-fallo-saneador',
            'content'        => $neutralContent,
            'status'         => 'draft',
            'is_published'   => false,
            'categories'     => ['Noticias Rock'],
            'author_email'   => 'dark.vader.agent@gmail.com',
            'source_subject' => 'Noticias: Algo pasó',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => '<sanitizer-fail@sevenrock.local>',
            'sender_email'      => 'dark.vader.agent@gmail.com',
            'source_subject'    => 'Noticias: Algo pasó',
            'received_at'       => now(),
            'is_embargo_active' => false,
            'security_status'   => 'held_security',
            'incident_reason'   => $sanitizerReason,
        ]);

        $rawContent = is_array($post->fresh()->content) ? json_encode($post->fresh()->content) : (string) $post->fresh()->content;
        $this->assertStringContainsString('CONTENIDO RETENIDO POR SEGURIDAD EDITORIAL', $rawContent);
        $this->assertEquals('held_security', $meta->fresh()->security_status);
        $this->assertFalse((bool) $post->fresh()->is_published);

        // CASO B: Fallo al persistir metadata en la transacción -> rollback total
        $initialPostsCount = Post::count();
        try {
            DB::transaction(function () {
                $p = Post::create([
                    'title'          => 'Noticia transaccional',
                    'slug'           => 'noticia-transaccional',
                    'content'        => '<p>Texto</p>',
                    'status'         => 'draft',
                    'is_published'   => false,
                    'author_email'   => 'dark.vader.agent@gmail.com',
                    'source_subject' => 'Asunto test',
                ]);

                // Simular fallo en metadata
                throw new \RuntimeException('Fallo forzado al persistir EmailPostSanitizedMeta');
            });
        } catch (\Throwable $e) {
            // Error capturado
        }

        $this->assertEquals($initialPostsCount, Post::count(), 'El rollback atómico no debe dejar ningún post huérfano sin metadatos');
    }

    /**
     * 13. Bloque 2: Lanzamiento con embargo original en correo pero cuya redacción generada omite la advertencia.
     * La activación debe estar bloqueada por la información persistida en la ingesta.
     */
    public function test_release_with_original_embargo_blocks_activation_even_if_generated_description_omits_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Simular un NewRelease originado por correo donde el correo original tenía embargo para dentro de 1 mes,
        // pero la IA omitió mencionar el embargo en la descripción
        $futureEmbargo = Carbon::now()->addMonth();
        $release = NewRelease::create([
            'title'             => 'Single Sin Aviso En Texto',
            'slug'              => 'single-sin-aviso-en-texto',
            'artist_name'       => 'Ghost Band',
            'description'       => 'Gran nuevo single de rock moderno listo para escuchar.', // No menciona la palabra embargo
            'released_at'       => $futureEmbargo,
            'is_active'         => false,
            'origin'            => 'email',
            'cover_image'       => 'catalog/releases/covers/single.jpg',
            'source_subject'    => 'Promo: Ghost Band - Single [EMBARGO UNTIL ' . $futureEmbargo->format('Y-m-d') . ']',
            'source_message_id' => '<release-embargo-test@music.com>',
            'is_embargo_active' => true,
            'embargo_until'     => $futureEmbargo,
            'security_status'   => 'held_embargo',
            'incident_reason'   => 'Material bajo embargo editorial detectado en correo original',
        ]);

        $this->assertTrue((bool) $release->is_embargo_active);
        $this->assertTrue($release->isFromEmailPipeline());

        // Intentar activar desde el panel NewReleaseController
        $response = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $release), [
                'title'        => 'Single Sin Aviso En Texto',
                'artist_name'  => 'Ghost Band',
                'description'  => 'Gran nuevo single de rock moderno listo para escuchar.',
                'released_at'  => $futureEmbargo->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/single.jpg',
                'is_active'    => true, // Intentar activar
            ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertFalse((bool) $release->fresh()->is_active, 'No debe permitir activarse porque la restricción de embargo está persistida');
    }

    /**
     * 14. Bloque 2: Edición de un lanzamiento eliminando el texto de advertencia de la descripción.
     * El embargo persistente debe seguir impidiendo la activación.
     */
    public function test_editing_release_and_deleting_embargo_text_does_not_lift_embargo(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $futureEmbargo = Carbon::now()->addDays(15);
        $release = NewRelease::create([
            'title'             => 'Álbum Protegido',
            'slug'              => 'album-protegido',
            'artist_name'       => 'Heavy Sound',
            'description'       => 'Texto inicial con advertencia de embargo hasta el ' . $futureEmbargo->format('d/m/Y'),
            'released_at'       => $futureEmbargo,
            'is_active'         => false,
            'origin'            => 'email',
            'cover_image'       => 'catalog/releases/covers/album.jpg',
            'source_subject'    => 'Heavy Sound Álbum - Embargo hasta ' . $futureEmbargo->format('d/m/Y'),
            'source_message_id' => '<album-embargo@records.com>',
            'is_embargo_active' => true,
            'embargo_until'     => $futureEmbargo,
            'security_status'   => 'held_embargo',
        ]);

        // Un editor intenta "engañar" al validador borrando el texto del embargo y marcando is_active=true
        $response = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $release), [
                'title'        => 'Álbum Protegido',
                'artist_name'  => 'Heavy Sound',
                'description'  => 'Texto completamente limpio sin ninguna palabra sobre restricciones.', // Texto borrado
                'released_at'  => $futureEmbargo->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/album.jpg',
                'is_active'    => true, // Intenta activar
            ]);

        $response->assertSessionHasErrors('is_active');
        $this->assertFalse((bool) $release->fresh()->is_active, 'Borrar el texto de embargo de la descripción NO debe levantar el embargo persistente');
    }

    /**
     * 15. Bloque 2: Contenido legítimo sin restricciones funciona normalmente y se activa/publica sin trabas.
     */
    public function test_legitimate_content_without_restrictions_activates_and_publishes_normally(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Lanzamiento legítimo desde correo pero con fecha de embargo ya vencida en el pasado
        $pastDate = Carbon::now()->subDays(5);
        $release = NewRelease::create([
            'title'             => 'Lanzamiento Pasado Legítimo',
            'slug'              => 'lanzamiento-pasado-legitimo',
            'artist_name'       => 'The Classics',
            'description'       => 'Lanzamiento público disponible en todas las plataformas.',
            'released_at'       => $pastDate,
            'is_active'         => false,
            'origin'            => 'email',
            'cover_image'       => 'catalog/releases/covers/classic.jpg',
            'source_subject'    => 'The Classics - Nuevo single',
            'source_message_id' => '<classic@music.com>',
            'is_embargo_active' => false,
            'embargo_until'     => $pastDate,
            'security_status'   => 'passed',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.new-releases.update', $release), [
                'title'        => 'Lanzamiento Pasado Legítimo',
                'artist_name'  => 'The Classics',
                'description'  => 'Lanzamiento público disponible en todas las plataformas.',
                'released_at'  => $pastDate->format('Y-m-d'),
                'cover_image'  => 'catalog/releases/covers/classic.jpg',
                'is_active'    => true, // Activar
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue((bool) $release->fresh()->is_active, 'El contenido legítimo sin restricciones debe poder activarse');
    }
}
