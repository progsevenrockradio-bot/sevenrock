<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\User;
use App\Services\EditorialBatchProcessor;
use App\Services\EditorialDarthVaderParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditorialBatchProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'email' => 'admin@sevenrockradio.com',
        ]);

        PostTaxonomy::create([
            'name' => 'Noticias Rock',
            'slug' => 'noticias-rock',
            'type' => 'category',
        ]);

        PostTaxonomy::create([
            'name' => 'Hoy en el Rock',
            'slug' => 'hoy-en-el-rock',
            'type' => 'category',
        ]);
    }

    public function test_parser_accurately_detects_and_extracts_dv1_non_consecutive_ids(): void
    {
        $batchFile = base_path('storage/editorial_batches/DV-20261110-01/DV-20261110-01_REVISADO.md');
        $this->assertFileExists($batchFile);

        $content = file_get_contents($batchFile);
        $parser = new EditorialDarthVaderParser();

        $this->assertTrue($parser->isDv1Format('Entrega DV-1', $content));

        $parsed = $parser->parseDv1('Entrega DV-1', $content);

        $this->assertSame('DV-20261110-01', $parsed['batch_id']);
        $this->assertSame('2026-11-10', $parsed['editorial_date']);
        $this->assertSame(5, $parsed['total_declared']);
        $this->assertSame(5, $parsed['total_extracted']);
        $this->assertFalse($parsed['has_count_discrepancy']);
        $this->assertCount(5, $parsed['elements']);

        $expectedIds = [
            'DV-20261110-01-E01',
            'DV-20261110-01-E02',
            'DV-20261110-01-E03',
            'DV-20261110-01-E04',
            'DV-20261110-01-E11',
        ];

        $extractedIds = array_column($parsed['elements'], 'id');
        $this->assertSame($expectedIds, $extractedIds);

        // Verificar fuentes múltiples en E01
        $this->assertGreaterThanOrEqual(2, count($parsed['elements'][0]['fuentes']));
        $this->assertStringContainsString('mariskalrock.com', $parsed['elements'][0]['fuentes'][0]);
        $this->assertStringContainsString('powermetal.de', $parsed['elements'][0]['fuentes'][1]);
    }

    public function test_batch_processor_creates_drafts_with_idempotency_and_metadata(): void
    {
        $batchFile = base_path('storage/editorial_batches/DV-20261110-01/DV-20261110-01_REVISADO.md');

        // Primera ejecución: debe crear 5 borradores
        $this->artisan('editorial:process-batch', ['file' => $batchFile])
            ->assertSuccessful();

        $this->assertSame(5, Post::count());
        $posts = Post::all();

        foreach ($posts as $post) {
            $this->assertSame('draft', $post->status);
            $this->assertSame(0, (int) $post->is_published);
            $this->assertNull($post->published_at);
            $this->assertTrue(! empty($post->featured_image_path) || ! empty($post->featured_image));
            $this->assertNotEmpty($post->image_source);
            $this->assertNotEmpty($post->image_alt);
        }

        // Verificar Ian Gillan desde el fichero revisado
        $gillanMeta = EmailPostSanitizedMeta::where('element_id', 'DV-20261110-01-E02')->first();
        $this->assertNotNull($gillanMeta);
        $this->assertStringContainsString('REVISION_REQUERIDA', $gillanMeta->editorial_notes);
        $this->assertStringContainsString('no existe artículo previo', $gillanMeta->editorial_notes);

        $gillanPost = $gillanMeta->post;
        $this->assertNotNull($gillanPost);
        $gillanText = implode(' ', (array) $gillanPost->content);
        $this->assertStringContainsString('aplazado', $gillanText);
        $this->assertStringNotContainsString('Me sometí ayer a una intervención', $gillanText);

        // Segunda ejecución (Idempotencia): no debe crear nuevos posts y debe omitir idénticos
        $this->artisan('editorial:process-batch', ['file' => $batchFile])
            ->assertSuccessful();

        $this->assertSame(5, Post::count());
        $this->assertSame(5, EmailPostSanitizedMeta::count());
    }

    public function test_reimporting_published_post_does_not_revert_to_draft_or_overwrite(): void
    {
        // 1. Crear un post existente publicado previamente
        $postData = [
            'title'        => 'Título Manual Ya Editado por Staff',
            'slug'         => 'titulo-manual-ya-editado',
            'author'       => 'Staff Editorial',
            'status'       => 'published',
            'is_published' => 1,
            'published_at' => now()->subDay(),
            'content'      => '<p>Contenido manual redactado por periodistas humanos.</p>',
            'excerpt'      => 'Extracto manual...',
            'image_source' => 'Staff',
            'image_alt'    => 'Alt manual',
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('posts', 'featured_image_path')) {
            $postData['featured_image_path'] = 'storage/posts/covers/custom.jpg';
        } else {
            $postData['featured_image'] = 'storage/posts/covers/custom.jpg';
        }
        $post = Post::create($postData);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'orig-msg-1',
            'batch_id'          => 'BATCH-TEST-SAFE',
            'element_id'        => 'BATCH-TEST-SAFE-E01',
            'payload_hash'      => 'hash-antiguo-1234',
            'sender_email'      => 'dark.vader.agent@gmail.com',
            'received_at'       => now()->subDay(),
            'editorial_notes'   => 'Publicado originalmente',
            'security_status'   => 'sanitized',
        ]);

        // 2. Intentar reimportar un lote con el mismo (batch_id, element_id) pero contenido diferente
        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-TEST-SAFE
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-TEST-SAFE-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Intento de Sobrescribir Titulo
TEXTO:
Este texto intenta sobrescribir un articulo publicado previamente en la web.
FUENTES:
- https://example.com/source
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MD;

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($payload);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['conflicts']);

        // 3. Verificar que el post publicado NO cambió de estado, ni de título, ni de fecha
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame(1, (int) $post->is_published);
        $this->assertSame('Título Manual Ya Editado por Staff', $post->title);
        $this->assertNotNull($post->published_at);

        // 4. Verificar que se registró el conflicto en los metadatos
        $meta->refresh();
        $this->assertSame('conflict', $meta->security_status);
        $this->assertStringContainsString('CONFLICTO_DETECTADO', $meta->editorial_notes);
    }

    public function test_processing_generic_batch_with_different_artist_in_e02(): void
    {
        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-GENERIC-99
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-GENERIC-99-E02
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
ARTISTA_BANDA: Iron Maiden
TITULO: Iron Maiden anuncia fechas de su gira 50 Aniversario en estadios
TEXTO:
La legendaria banda britanica Iron Maiden ha confirmado un nuevo tramo de conciertos en grandes recintos europeos.
FUENTES:
- https://ironmaiden.com/tour
REVISION_REQUERIDA: NO
MOTIVO_REVISION: NINGUNO
=== FIN ELEMENTO ===
MD;

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($payload);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['created']);

        $post = Post::where('slug', 'iron-maiden-anuncia-fechas-de-su-gira-50-aniversario-en-estadios')->first();
        $this->assertNotNull($post);
        $postText = implode(' ', (array) $post->content);
        $this->assertStringContainsString('Iron Maiden', $postText);
        $this->assertStringNotContainsString('Ian Gillan', $postText);
    }

    public function test_respects_revision_requerida_and_motivo_from_payload(): void
    {
        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-REV-CHECK
TOTAL_ELEMENTOS: 2

=== INICIO ELEMENTO ===
ID: BATCH-REV-CHECK-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Noticia que requiere revision especial de la redaccion
TEXTO:
Este texto tiene datos pendientes de confirmacion por parte de la sala de conciertos.
FUENTES:
- https://test.com
REVISION_REQUERIDA: SI
MOTIVO_REVISION: Fechas no confirmadas por la sala
=== FIN ELEMENTO ===

=== INICIO ELEMENTO ===
ID: BATCH-REV-CHECK-E02
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Noticia aprobada directamente sin observaciones
TEXTO:
Este texto esta completamente confirmado y verificado con las agencias oficiales.
FUENTES:
- https://test.com
REVISION_REQUERIDA: NO
MOTIVO_REVISION: NINGUNO
=== FIN ELEMENTO ===
MD;

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($payload);

        $this->assertTrue($result['success']);

        $meta1 = EmailPostSanitizedMeta::where('element_id', 'BATCH-REV-CHECK-E01')->first();
        $this->assertNotNull($meta1);
        $this->assertSame('needs_review', $meta1->security_status);
        $this->assertStringContainsString('Fechas no confirmadas por la sala', $meta1->editorial_notes);

        $meta2 = EmailPostSanitizedMeta::where('element_id', 'BATCH-REV-CHECK-E02')->first();
        $this->assertNotNull($meta2);
        $this->assertSame('sanitized', $meta2->security_status);
        $this->assertStringContainsString('Sin observaciones pendientes', $meta2->editorial_notes);
    }

    public function test_coordination_between_local_batch_and_email_processing_avoids_duplicates(): void
    {
        $batchFile = base_path('storage/editorial_batches/DV-20261110-01/DV-20261110-01_REVISADO.md');
        $rawContent = file_get_contents($batchFile);

        // 1. Proceso A: Importación local
        $this->artisan('editorial:process-batch', ['file' => $batchFile])
            ->assertSuccessful();

        $this->assertSame(5, Post::count());
        $this->assertSame(5, EmailPostSanitizedMeta::count());

        // 2. Proceso B: Simulación de llegada del mismo lote por correo IMAP coordinado
        $processor = app(EditorialBatchProcessor::class);
        $emailResult = $processor->process($rawContent, [], sourceMessageId: 'imap-msg-12345');

        $this->assertTrue($emailResult['success']);
        $this->assertSame(0, $emailResult['created']);
        $this->assertSame(5, $emailResult['skipped']);

        // Assert final: cero duplicados
        $this->assertSame(5, Post::count());
        $this->assertSame(5, EmailPostSanitizedMeta::count());
    }

    public function test_rejects_invalid_batch_atomically_without_partial_writes(): void
    {
        // Caso A: Discrepancia en conteo declarado vs extraído
        $payloadDiscrepancy = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-DISCREPANCY
TOTAL_ELEMENTOS: 5

=== INICIO ELEMENTO ===
ID: BATCH-DISCREPANCY-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Elemento solitario
TEXTO:
Este lote declara cinco elementos pero solo incluye uno en el cuerpo.
FUENTES:
- https://test.com
=== FIN ELEMENTO ===
MD;

        $processor = app(EditorialBatchProcessor::class);
        $result1 = $processor->process($payloadDiscrepancy);
        $this->assertFalse($result1['success']);
        $this->assertStringContainsString('Discrepancia de conteo', $result1['error']);
        $this->assertSame(0, Post::count());

        // Caso B: Identificador duplicado en el mismo lote
        $payloadDup = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-DUPLICATES
TOTAL_ELEMENTOS: 2

=== INICIO ELEMENTO ===
ID: BATCH-DUP-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Elemento Uno
TEXTO:
Texto del primer elemento del lote duplicado.
FUENTES:
- https://test.com
=== FIN ELEMENTO ===

=== INICIO ELEMENTO ===
ID: BATCH-DUP-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Elemento Dos con el mismo ID
TEXTO:
Texto del segundo elemento con el mismo identificador.
FUENTES:
- https://test.com
=== FIN ELEMENTO ===
MD;

        $result2 = $processor->process($payloadDup);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('Identificador duplicado', $result2['error']);
        $this->assertSame(0, Post::count());

        // Caso C: Campo obligatorio faltante (título vacío)
        $payloadMissing = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-MISSING
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-MISSING-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: 
TEXTO:
Texto con titulo vacio que debe ser rechazado inmediatamente.
FUENTES:
- https://test.com
=== FIN ELEMENTO ===
MD;

        $result3 = $processor->process($payloadMissing);
        $this->assertFalse($result3['success']);
        $this->assertStringContainsString('carece de campo TITULO', $result3['error']);
        $this->assertSame(0, Post::count());
    }

    public function test_draft_preview_is_protected_against_visitors_and_unauthorized_users(): void
    {
        // 1. Crear un borrador en la BD
        $draft = Post::create([
            'title'        => 'Borrador Confidencial En Revision',
            'slug'         => 'borrador-confidencial-en-revision',
            'author'       => 'Seven Rock Radio',
            'status'       => 'draft',
            'is_published' => 0,
            'published_at' => null,
            'content'      => '<p>Texto exclusivo de prueba antes de publicacion.</p>',
            'excerpt'      => 'Extracto borrador...',
        ]);

        $url = '/2026/11/10/' . $draft->slug;

        // Caso A: Visitante no autenticado (Guest) -> 404 Not Found
        $responseGuest = $this->get($url);
        $responseGuest->assertStatus(404);

        // Caso B: Usuario autenticado SIN permisos administrativos ni editoriales -> 403 Forbidden
        $regularUser = User::factory()->create([
            'email'    => 'listener@sevenrockradio.com',
            'is_admin' => false,
        ]);
        $responseUnauthorized = $this->actingAs($regularUser)->get($url);
        $responseUnauthorized->assertStatus(403);

        // Caso C: Usuario Administrador autenticado -> 200 OK y cabeceras privadas anti-caché
        $adminUser = User::where('email', 'admin@sevenrockradio.com')->first();
        $adminUser->is_admin = true;
        $adminUser->save();

        $responseAdmin = $this->actingAs($adminUser)->get($url);
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Borrador Confidencial En Revision');

        // Verificar cabeceras de no almacenamiento en caché compartida
        $cacheControl = (string) $responseAdmin->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function test_modified_draft_payload_is_not_overwritten_without_explicit_approval(): void
    {
        // 1. Crear borrador inicial
        $processor = app(EditorialBatchProcessor::class);
        $initialPayload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-MOD-PRESERVE
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-MOD-PRESERVE-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Titulo Borrador Original
TEXTO:
Texto original del borrador con modificaciones manuales de los editores.
FUENTES:
- https://example.com/source1
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MD;

        $res1 = $processor->process($initialPayload);
        $this->assertTrue($res1['success']);
        $this->assertSame(1, $res1['created']);

        $post = Post::where('slug', 'titulo-borrador-original')->first();
        $this->assertNotNull($post);

        // Simular edición manual por redactores: cambian título y texto
        $post->title = 'Titulo Modificado Manualmente por Redaccion';
        $post->content = '<p>Edicion manual periodistica que no debe perderse jamas.</p>';
        if (\Illuminate\Support\Facades\Schema::hasColumn('posts', 'featured_image_path')) {
            $post->featured_image_path = 'storage/posts/covers/manual-assigned.jpg';
        } else {
            $post->featured_image = 'storage/posts/covers/manual-assigned.jpg';
        }
        $post->save();

        // 2. Llega un nuevo payload con contenido modificado para el mismo (batch_id, element_id)
        $modifiedPayload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-MOD-PRESERVE
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-MOD-PRESERVE-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Titulo Modificado Que No Debe Sobrescribir
TEXTO:
Texto nuevo que llega en una reentrega automatica y debe ser detenido como conflicto.
FUENTES:
- https://example.com/source2
REVISION_REQUERIDA: SI
MOTIVO_REVISION: Nueva version entrante
=== FIN ELEMENTO ===
MD;

        // Ejecutar SIN aprobación explícita (comportamiento por defecto)
        $res2 = $processor->process($modifiedPayload, allowUpdateApproval: false);

        $this->assertTrue($res2['success']);
        $this->assertSame(1, $res2['conflicts']);
        $this->assertSame('conflict_preserved_existing', $res2['elements_detail']['BATCH-MOD-PRESERVE-E01']['action']);

        // 3. Comprobar que el borrador existente NO fue sobrescrito
        $post->refresh();
        $this->assertSame('Titulo Modificado Manualmente por Redaccion', $post->title);
        $this->assertStringContainsString('Edicion manual periodistica', implode(' ', (array) $post->content));
        $this->assertSame('storage/posts/covers/manual-assigned.jpg', $post->featured_image_path ?? $post->featured_image);

        // 4. Si se invoca CON aprobación explícita (allowUpdateApproval: true), entonces sí se actualiza conservando la imagen
        $res3 = $processor->process($modifiedPayload, allowUpdateApproval: true);
        $this->assertTrue($res3['success']);
        $this->assertSame(1, $res3['updated']);

        $post->refresh();
        $this->assertSame('Titulo Modificado Que No Debe Sobrescribir', $post->title);
        // La imagen previa NO se borra
        $this->assertSame('storage/posts/covers/manual-assigned.jpg', $post->featured_image_path ?? $post->featured_image);
    }

    public function test_stable_payload_hash_detects_all_relevant_field_changes(): void
    {
        $baseElem = [
            'tipo'                 => 'NOTICIA',
            'titulo'               => 'Gira Mundial Anunciada',
            'artista_banda'        => 'Judas Priest',
            'fecha_acontecimiento' => '2026-11-10',
            'texto'                => 'Judas Priest confirma una nueva gira de estadios.',
            'fuentes'              => ['https://source1.com', 'https://source2.com'],
            'revision_requerida'   => false,
            'motivo_revision'      => null,
            'imagen_url'           => 'https://commons.wikimedia.org/judas.jpg',
            'imagen_credito'       => 'Foto: Autor / CC BY 2.0',
            'imagen_alt'           => 'Judas Priest en directo',
        ];

        $baseHash = EditorialBatchProcessor::computeStablePayloadHash($baseElem);
        $this->assertNotEmpty($baseHash);

        // 1. Cambio en revision_requerida
        $elemRev = $baseElem;
        $elemRev['revision_requerida'] = true;
        $this->assertNotSame($baseHash, EditorialBatchProcessor::computeStablePayloadHash($elemRev));

        // 2. Cambio en motivo_revision
        $elemMotivo = $baseElem;
        $elemMotivo['motivo_revision'] = 'Comprobar fecha con promotora';
        $this->assertNotSame($baseHash, EditorialBatchProcessor::computeStablePayloadHash($elemMotivo));

        // 3. Cambio en fecha
        $elemDate = $baseElem;
        $elemDate['fecha_acontecimiento'] = '2026-12-01';
        $this->assertNotSame($baseHash, EditorialBatchProcessor::computeStablePayloadHash($elemDate));

        // 4. Cambio en metadatos de imagen (crédito)
        $elemImg = $baseElem;
        $elemImg['imagen_credito'] = 'Foto: Otro Autor / CC BY-SA 4.0';
        $this->assertNotSame($baseHash, EditorialBatchProcessor::computeStablePayloadHash($elemImg));

        // 5. Estabilidad: mismo contenido con distinto orden en fuentes o saltos de línea Windows/Unix produce el MISMO hash
        $elemStable = $baseElem;
        $elemStable['fuentes'] = ['https://source2.com', 'https://source1.com']; // Invertido
        $elemStable['texto'] = "Judas Priest confirma una nueva gira de estadios.\r\n"; // \r\n vs \n
        $this->assertSame($baseHash, EditorialBatchProcessor::computeStablePayloadHash($elemStable));
    }

    public function test_process_incoming_emails_end_to_end_avoids_duplication_after_local_import(): void
    {
        // 1. Configurar ThemeSetting para habilitar procesamiento de correos
        $settings = \App\Models\ThemeSetting::current();
        $settings->email_processing_enabled = true;
        $settings->gemini_api_key = 'fake-api-key-for-test';
        $settings->save();

        config([
            'services.imap.password' => 'fake-password',
            'services.imap.username' => 'test@sevenrockradio.com',
        ]);

        $batchFile = base_path('storage/editorial_batches/DV-20261110-01/DV-20261110-01_REVISADO.md');
        $batchContent = file_get_contents($batchFile);

        // 2. Paso 1: Importar lote primero localmente mediante comando
        $this->artisan('editorial:process-batch', ['file' => $batchFile])
            ->assertSuccessful();

        $this->assertSame(5, Post::count());
        $this->assertSame(5, EmailPostSanitizedMeta::count());

        // 3. Paso 2: Simular llegada del mismo lote por correo IMAP a través del comando emails:process real
        $mockClientManager = \Mockery::mock(\Webklex\PHPIMAP\ClientManager::class);
        $mockClient = \Mockery::mock(\Webklex\PHPIMAP\Client::class);
        $mockFolder = \Mockery::mock(\Webklex\PHPIMAP\Folder::class);
        $mockQuery = \Mockery::mock(\Webklex\PHPIMAP\Query\WhereQuery::class);
        $mockMessage = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
        $mockAddress = \Mockery::mock(\Webklex\PHPIMAP\Address::class);

        $mockAddress->mail = 'dark.vader.agent@gmail.com';

        $mockMessage->shouldReceive('getMessageId')->andReturn('<dv-test-20261110-msg@gmail.com>');
        $mockMessage->shouldReceive('getSubject')->andReturn('[DARTH-VADER][DV-1][2026-11-10][DV-20261110-01]');
        $mockMessage->shouldReceive('getFrom')->andReturn(new \Webklex\PHPIMAP\Support\PaginatedCollection([$mockAddress]));
        $mockMessage->shouldReceive('getAttachments')->andReturn(new \Webklex\PHPIMAP\Support\AttachmentCollection([]));
        $mockMessage->shouldReceive('getHTMLBody')->andReturn('');
        $mockMessage->shouldReceive('getTextBody')->andReturn($batchContent);
        $mockMessage->shouldReceive('setFlag')->with('SEEN')->andReturn(true);

        $mockQuery->shouldReceive('unseen')->andReturn($mockQuery);
        $mockQuery->shouldReceive('get')->andReturn(new \Webklex\PHPIMAP\Support\MessageCollection([$mockMessage]));
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->andReturn($mockFolder);
        $mockClient->shouldReceive('connect')->andReturn($mockClient);
        $mockClientManager->shouldReceive('make')->andReturn($mockClient);

        $this->app->instance(\Webklex\PHPIMAP\ClientManager::class, $mockClientManager);

        $mockVerifier = \Mockery::mock(\App\Services\EmailSecurityVerifier::class);
        $mockVerifier->shouldReceive('isDarkVaderLegitimate')->andReturn(true);
        $this->app->instance(\App\Services\EmailSecurityVerifier::class, $mockVerifier);

        // 4. Ejecutar el comando emails:process
        $exitCode = \Illuminate\Support\Facades\Artisan::call('emails:process');
        $this->assertSame(0, $exitCode);

        // 5. Verificar que NO hubo duplicados en absoluto
        $this->assertSame(5, Post::count());
        $this->assertSame(5, EmailPostSanitizedMeta::count());

        $this->assertDatabaseHas('processed_emails', [
            'message_id' => '<dv-test-20261110-msg@gmail.com>',
            'status'     => 'processed',
        ]);
    }

    public function test_concurrent_execution_collision_does_not_leave_orphan_posts_and_reports_failure(): void
    {
        // 1. Simular elemento preexistente en metadatos para provocar colisión de clave única
        $post = Post::create([
            'title'        => 'Articulo Ya Existente',
            'slug'         => 'articulo-ya-existente',
            'author'       => 'Seven Rock Radio',
            'status'       => 'draft',
            'is_published' => 0,
            'content'      => '<p>Texto existente.</p>',
            'excerpt'      => 'Extracto...',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-concurrency-prior',
            'batch_id'          => 'BATCH-CONCURRENCY-TEST',
            'element_id'        => 'BATCH-CONCURRENCY-TEST-E01',
            'payload_hash'      => 'hash-previo-1234',
            'sender_email'      => 'dark.vader.agent@gmail.com',
            'received_at'       => now(),
            'editorial_notes'   => 'Elemento inicial',
            'security_status'   => 'sanitized',
        ]);

        $initialPostCount = Post::count();

        // 2. Simular un proceso concurrente B que intenta forzar una inserción duplicada en meta
        // interceptando y lanzando una QueryException de clave única
        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-CONCURRENCY-TEST
TOTAL_ELEMENTOS: 1

=== INICIO ELEMENTO ===
ID: BATCH-CONCURRENCY-TEST-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Noticia Concurrente Provocando Colision
TEXTO:
Texto de un elemento que intenta insertarse mientras otro proceso acababa de consolidar el registro.
FUENTES:
- https://test.com
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MD;

        // Simulamos la colisión haciendo que el guardado falle con excepción de restricción única
        \Illuminate\Support\Facades\Event::listen('eloquent.saving: App\Models\EmailPostSanitizedMeta', function ($meta) {
            if ($meta->element_id === 'BATCH-CONCURRENCY-TEST-E01') {
                throw new \Illuminate\Database\QueryException(
                    'sqlite',
                    'insert into email_post_sanitized_meta...',
                    [],
                    new \Exception('UNIQUE constraint failed: email_post_sanitized_meta.batch_id, email_post_sanitized_meta.element_id')
                );
            }
        });

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($payload, allowUpdateApproval: true);

        // 3. Comprobar que el resultado comunica el fallo correctamente
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('BATCH-CONCURRENCY-TEST-E01', $result['failed_elements']);
        $this->assertStringContainsString('COLISIÓN_CONCURRENCIA', $result['failed_elements']['BATCH-CONCURRENCY-TEST-E01']);

        // 4. Comprobar que la transacción se revirtió y NO quedó ningún Post huérfano en la tabla posts
        $finalPostCount = Post::count();
        $this->assertSame($initialPostCount, $finalPostCount);
        $this->assertNull(Post::where('title', 'Noticia Concurrente Provocando Colision')->first());
    }

    public function test_persistence_failure_on_one_element_preserves_prior_elements_and_fails_batch_with_precise_summary(): void
    {
        $payload = <<<MD
FORMATO: DV-1
LOTE_ID: BATCH-FAIL-ELEMENT-2
TOTAL_ELEMENTOS: 3

=== INICIO ELEMENTO ===
ID: BATCH-FAIL-E01
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Primer Elemento Valido Que Debe Persistir
TEXTO:
Este primer elemento se procesa y persiste correctamente en la base de datos.
FUENTES:
- https://test.com/1
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===

=== INICIO ELEMENTO ===
ID: BATCH-FAIL-E02
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Segundo Elemento Que Sufrira Error De Persistencia
TEXTO:
Este segundo elemento sufrira un fallo simulado en su transaccion de guardado.
FUENTES:
- https://test.com/2
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===

=== INICIO ELEMENTO ===
ID: BATCH-FAIL-E03
TIPO: NOTICIA
FECHA_EDITORIAL: 2026-11-10
TITULO: Tercer Elemento Valido Posterior
TEXTO:
Este tercer elemento tambien se procesa tras el fallo del segundo elemento.
FUENTES:
- https://test.com/3
REVISION_REQUERIDA: NO
=== FIN ELEMENTO ===
MD;

        // Provocar fallo específico de persistencia solo en E02
        \Illuminate\Support\Facades\Event::listen('eloquent.saving: App\Models\Post', function ($post) {
            if ($post->title === 'Segundo Elemento Que Sufrira Error De Persistencia') {
                throw new \RuntimeException('Error simulado de disco o base de datos en E02');
            }
        });

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($payload);

        // 1. El lote general falla (success => false)
        $this->assertFalse($result['success']);
        $this->assertSame('Uno o más elementos no pudieron ser persistidos.', $result['error']);

        // 2. Resumen preciso: distingue elementos procesados vs fallidos
        $this->assertContains('BATCH-FAIL-E01', $result['processed_elements']);
        $this->assertContains('BATCH-FAIL-E03', $result['processed_elements']);
        $this->assertArrayHasKey('BATCH-FAIL-E02', $result['failed_elements']);
        $this->assertStringContainsString('Error simulado de disco', $result['failed_elements']['BATCH-FAIL-E02']);

        // 3. Acredita qué ocurre con los elementos anteriores (y posteriores válidos):
        // E01 y E03 existen íntegros en la BD, mientras que E02 fue revertido limpiamente por su transacción
        $this->assertNotNull(Post::where('title', 'Primer Elemento Valido Que Debe Persistir')->first());
        $this->assertNotNull(Post::where('title', 'Tercer Elemento Valido Posterior')->first());
        $this->assertNull(Post::where('title', 'Segundo Elemento Que Sufrira Error De Persistencia')->first());
    }
}
