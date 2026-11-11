<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Http\Controllers\SiteController;
use App\Models\AuditLog;
use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use App\Services\ArchiveOrgService;
use App\Services\DvAutoPublishPolicy;
use App\Services\EditorialBatchProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DvAutoPublishPolicyAndHomeRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected string $testCoverPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear taxonomías requeridas
        PostTaxonomy::create(['name' => 'Noticias Rock', 'slug' => 'noticias-rock', 'type' => 'category']);
        PostTaxonomy::create(['name' => 'Hoy en el Rock', 'slug' => 'hoy-en-el-rock', 'type' => 'category']);

        // Crear imagen física simulada para validar existencia en disco
        $coversDir = storage_path('app/public/posts/covers');
        if (! File::exists($coversDir)) {
            File::makeDirectory($coversDir, 0755, true);
        }

        $this->testCoverPath = 'storage/posts/covers/post-dv-test-cover.jpg';
        $fullPath = storage_path('app/public/posts/covers/post-dv-test-cover.jpg');
        if (! file_exists($fullPath)) {
            file_put_contents($fullPath, 'fake-image-bytes-for-test');
        }
    }

    protected function tearDown(): void
    {
        $fullPath = storage_path('app/public/posts/covers/post-dv-test-cover.jpg');
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        parent::tearDown();
    }

    /**
     * Helper para generar un elemento DV-1 en formato texto
     */
    protected function generateDv1Element(
        string $id,
        string $tipo,
        string $artist,
        string $title,
        string $eventDate,
        string $fuente = 'https://blabbermouth.net/news/valid-rock-news',
        string $revision = 'NO',
        string $motivo = 'NINGUNO',
        string $imageCredit = 'Foto: Autor / Wikimedia Commons (CC BY-SA 4.0)'
    ): string {
        return <<<ELEM
=== INICIO ELEMENTO ===
ID: {$id}
TIPO: {$tipo}
FECHA_EDITORIAL: 2026-11-11
FECHA_ACONTECIMIENTO: {$eventDate}
ARTISTA_BANDA: {$artist}
TITULO: {$title}
TEXTO:
Este es un texto completo y profesional para la nota editorial del artista {$artist}, detallando su nuevo acontecimiento y novedades con mas de treinta caracteres para superar las validaciones de longitud.
FUENTES:
- {$fuente}
IMAGEN_URL: {$this->testCoverPath}
IMAGEN_CREDITO: {$imageCredit}
REVISION_REQUERIDA: {$revision}
MOTIVO_REVISION: {$motivo}
CORRIGE_ID: NINGUNO
=== FIN ELEMENTO ===
ELEM;
    }

    /**
     * TEST 1: Lote con 7 noticias y 7 efemérides (14 piezas válidas).
     * Se deben publicar las 14 piezas completas sin tope arbitrario de 4 o 7.
     * Ninguna debe tener aprobación humana ficticia (approved_by = null).
     */
    public function test_variable_batch_publishes_all_14_qualifying_pieces_without_artificial_cap(): void
    {
        $elementsText = [];

        // 7 Noticias
        for ($i = 1; $i <= 7; $i++) {
            $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $elementsText[] = $this->generateDv1Element(
                id: "DV-20261111-VAR-E{$num}",
                tipo: 'NOTICIA',
                artist: "Banda Noticia {$i}",
                title: "Banda Noticia {$i} anuncia nuevo disco y gira mundial para 2027",
                eventDate: '2026-11-10'
            );
        }

        // 7 Efemérides (con titular histórico y fecha pasada)
        for ($j = 8; $j <= 14; $j++) {
            $num = str_pad((string) $j, 2, '0', STR_PAD_LEFT);
            $year = 1970 + $j;
            $elementsText[] = $this->generateDv1Element(
                id: "DV-20261111-VAR-E{$num}",
                tipo: 'EFEMERIDE',
                artist: "Leyenda Rock {$j}",
                title: "Efeméride ({$year}): Nace músico legendario {$j} en Londres",
                eventDate: "{$year}-11-11"
            );
        }

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-20261111-VAR\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 14\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(14, $result['total_extracted']);
        $this->assertSame(14, $result['created']);
        $this->assertSame(7, $result['auto_published_noticias']);
        $this->assertSame(7, $result['auto_published_efemerides']);
        $this->assertSame(14, $result['auto_published_total']);
        $this->assertEmpty($result['warnings']);

        // Verificar que los 14 posts están formalmente publicados
        $publishedPosts = Post::where('status', 'published')->where('is_published', 1)->get();
        $this->assertSame(14, $publishedPosts->count());

        // Verificar que NINGUNO lleva firma humana ficticia (approved_by = null)
        $metas = EmailPostSanitizedMeta::where('batch_id', 'DV-20261111-VAR')->get();
        $this->assertSame(14, $metas->count());
        foreach ($metas as $m) {
            $this->assertNull($m->approved_by, 'La publicación automática no debe registrar aprobación humana ficticia.');
            $this->assertNotNull($m->approved_at);
            $this->assertSame('passed', $m->security_status);
            $this->assertStringContainsString('[AUTO_PUBLICADO]', $m->editorial_notes);
            $this->assertNotEmpty($m->approved_urls);
        }

        // Verificar eventos de auditoría registrados
        $autoLogs = AuditLog::where('event', 'editorial_auto_policy_published')->count();
        $this->assertSame(14, $autoLogs);
    }

    /**
     * TEST 2: Rechazos parciales.
     * Elementos con defectos quedan como borrador con motivo concreto,
     * sin bloquear la publicación de los elementos válidos.
     */
    public function test_partial_rejections_stay_as_drafts_without_blocking_valid_pieces(): void
    {
        $elementsText = [
            // E01: Noticia VÁLIDA -> Debe publicarse
            $this->generateDv1Element('DV-PART-E01', 'NOTICIA', 'Kreator', 'Kreator confirman nuevo álbum para 2027', '2026-11-10'),

            // E02: Noticia con REVISIÓN_REQUERIDA = SI -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E02', 'NOTICIA', 'Megadeth', 'Megadeth anuncia festival europeo en 2027', '2026-11-10', revision: 'SI', motivo: 'Discrepancia en fechas de concierto'),

            // E03: Noticia con URL insegura con credenciales incrustadas -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E03', 'NOTICIA', 'Anthrax', 'Anthrax prepara gira de 40 aniversario', '2026-11-10', fuente: 'https://admin:pass123@blabbermouth.net/news/anthrax'),

            // E04: Noticia con licencia de imagen NO acreditada (sin Commons ni CC) -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E04', 'NOTICIA', 'Overkill', 'Overkill estrena single de adelanto', '2026-11-10', imageCredit: 'Foto: Instagram oficial sin derechos conocidos'),

            // E05: Efeméride con titular que NO identifica hecho histórico (parece actualidad) -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E05', 'EFEMERIDE', 'Artista X', 'Muere guitarrista legendario', '1982-11-11'),

            // E06: Efeméride VÁLIDA con titular histórico -> Debe publicarse
            $this->generateDv1Element('DV-PART-E06', 'EFEMERIDE', 'Artista Y', 'Efeméride (1985): Nace cantante de power metal en Hamburgo', '1985-11-11'),
        ];

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-PART-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 6\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(6, $result['created']);
        $this->assertSame(1, $result['auto_published_noticias']); // Solo E01
        $this->assertSame(1, $result['auto_published_efemerides']); // Solo E06
        $this->assertSame(2, $result['auto_published_total']);

        // Advertencia emitida porque se publicaron menos de 4 noticias y menos de 4 efemérides
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('menos del umbral de 4', $result['warnings'][0]);

        // E01 (Válido): Publicado
        $postE01 = Post::where('slug', 'like', '%kreator%')->first();
        $this->assertSame('published', $postE01->status);
        $this->assertTrue((bool) $postE01->is_published);

        // E02 (Revisión requerida): Borrador con motivo
        $postE02 = Post::where('slug', 'like', '%megadeth%')->first();
        $this->assertSame('draft', $postE02->status);
        $this->assertFalse((bool) $postE02->is_published);
        $this->assertStringContainsString('Discrepancia en fechas', $postE02->sanitizedMeta->editorial_notes);

        // E03 (Credenciales en URL): Borrador por política
        $postE03 = Post::where('slug', 'like', '%anthrax%')->first();
        $this->assertSame('draft', $postE03->status);
        $this->assertFalse((bool) $postE03->is_published);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $postE03->sanitizedMeta->editorial_notes);
        $this->assertStringContainsString('credenciales', $postE03->sanitizedMeta->editorial_notes);

        // E04 (Imagen no acreditada): Borrador por política
        $postE04 = Post::where('slug', 'like', '%overkill%')->first();
        $this->assertSame('draft', $postE04->status);
        $this->assertFalse((bool) $postE04->is_published);
        $this->assertStringContainsString('Permiso de imagen no acreditado', $postE04->sanitizedMeta->editorial_notes);

        // E05 (Efeméride sin contexto histórico en titular): Borrador por política
        $postE05 = Post::where('slug', 'like', '%muere-guitarrista-legendario%')->first();
        $this->assertSame('draft', $postE05->status);
        $this->assertFalse((bool) $postE05->is_published);
        $this->assertStringContainsString('no identifica expresamente el año o contexto histórico', $postE05->sanitizedMeta->editorial_notes);

        // E06 (Efeméride válida): Publicada
        $postE06 = Post::where('slug', 'like', '%efemeride-1985%')->first();
        $this->assertSame('published', $postE06->status);
        $this->assertTrue((bool) $postE06->is_published);
    }

    /**
     * TEST 3: Portada muestra exactamente las 4 publicaciones más recientes,
     * con orden estable por fecha e ID, y las anteriores permanecen intactas en la BD.
     */
    public function test_home_page_shows_exactly_four_latest_news_with_stable_order_and_retains_older(): void
    {
        // 1. Crear 3 noticias antiguas previas
        for ($old = 1; $old <= 3; $old++) {
            $p = Post::create([
                'title' => "Noticia Antigua {$old}",
                'slug' => "noticia-antigua-{$old}",
                'content' => "<p>Contenido noticia antigua {$old}</p>",
                'status' => 'published',
                'is_published' => 1,
                'published_at' => now()->subDays(5 - $old),
                'categories' => ['Noticias Rock'],
            ]);
            $p->taxonomies()->sync(PostTaxonomy::where('slug', 'noticias-rock')->first()->id);
        }

        $this->assertSame(3, Post::published()->count());

        // 2. Procesar nuevo lote con 6 noticias válidas
        $elementsText = [];
        for ($k = 1; $k <= 6; $k++) {
            $num = str_pad((string) $k, 2, '0', STR_PAD_LEFT);
            $elementsText[] = $this->generateDv1Element(
                id: "DV-HOME-E{$num}",
                tipo: 'NOTICIA',
                artist: "Banda Nueva {$k}",
                title: "Banda Nueva {$k} estrena videoclip oficial",
                eventDate: '2026-11-11'
            );
        }

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-HOME-BATCH\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 6\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process($rawBatch, autoPublish: true);

        $this->assertTrue($result['success']);
        $this->assertSame(6, $result['auto_published_noticias']);

        // Total en base de datos: 3 antiguas + 6 nuevas = 9 publicaciones publicadas (ninguna borrada)
        $this->assertSame(9, Post::published()->count());

        // 3. Consultar la portada a través de SiteController::homePosts
        $siteController = app(SiteController::class);
        $reflection = new \ReflectionClass($siteController);
        $homePostsMethod = $reflection->getMethod('homePosts');
        $homePostsMethod->setAccessible(true);

        [$homeNoticias, $homeEfemerides] = $homePostsMethod->invoke($siteController);

        // La portada debe mostrar EXACTAMENTE 4 noticias
        $this->assertSame(4, $homeNoticias->count(), 'La portada debe mostrar exactamente 4 noticias.');

        // Las 4 noticias en portada deben ser las 4 más recientes del lote nuevo (Banda Nueva 6, 5, 4, 3)
        $homeTitles = $homeNoticias->pluck('title')->toArray();
        $this->assertStringContainsString('Banda Nueva 6', $homeTitles[0]);
        $this->assertStringContainsString('Banda Nueva 5', $homeTitles[1]);
        $this->assertStringContainsString('Banda Nueva 4', $homeTitles[2]);
        $this->assertStringContainsString('Banda Nueva 3', $homeTitles[3]);

        // Y las noticias antiguas siguen existiendo en la BD
        $this->assertDatabaseHas('posts', ['title' => 'Noticia Antigua 1']);
        $this->assertDatabaseHas('posts', ['title' => 'Noticia Antigua 2']);
        $this->assertDatabaseHas('posts', ['title' => 'Noticia Antigua 3']);
    }

    /**
     * TEST 4: Idempotencia y protección de conflictos en publicaciones automáticas.
     * Reintentos idénticos no duplican ni alteran, y un conflicto con un artículo
     * publicado no lo despublica ni lo convierte en borrador.
     */
    public function test_reintent_is_skipped_and_conflict_with_published_post_is_protected(): void
    {
        $elem = $this->generateDv1Element('DV-IDEMP-E01', 'NOTICIA', 'Saxon', 'Saxon anuncia gira británica 2027', '2026-11-11');
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-IDEMP-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elem;

        $processor = app(EditorialBatchProcessor::class);

        // Primer envío -> Se publica
        $res1 = $processor->process($rawBatch, autoPublish: true);
        $this->assertSame(1, $res1['created']);
        $this->assertSame(1, $res1['auto_published_noticias']);

        $post1 = Post::where('slug', 'like', '%saxon%')->first();
        $originalPublishedAt = $post1->published_at;

        // Segundo envío idéntico (reintento) -> Se omite (skipped)
        $res2 = $processor->process($rawBatch, autoPublish: true);
        $this->assertSame(1, $res2['skipped']);
        $this->assertSame(0, $res2['created']);
        $this->assertSame(0, $res2['auto_published_noticias']);

        // Comprobar que sigue publicado intacto
        $post1->refresh();
        $this->assertSame('published', $post1->status);
        $this->assertTrue((bool) $post1->is_published);
        $this->assertSame($originalPublishedAt->toIso8601String(), $post1->published_at->toIso8601String());

        // Tercer envío: mismo ID de lote y elemento pero texto modificado (conflicto)
        $modifiedElem = str_replace('gira británica 2027', 'gira británica 2027 CANCELADA', $elem);
        $rawBatchModified = "FORMATO: DV-1\nLOTE_ID: DV-IDEMP-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $modifiedElem;

        $res3 = $processor->process($rawBatchModified, autoPublish: true);
        $this->assertSame(1, $res3['conflicts']);

        // REGLA CRÍTICA: El post publicado permanece intacto, publicado y sin despublicar
        $post1->refresh();
        $this->assertSame('published', $post1->status);
        $this->assertTrue((bool) $post1->is_published);
        $this->assertStringContainsString('gira británica 2027', $post1->title);
        $this->assertStringNotContainsString('CANCELADA', $post1->title);
    }
}
