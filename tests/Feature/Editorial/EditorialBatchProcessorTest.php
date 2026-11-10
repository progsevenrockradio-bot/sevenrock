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
}
