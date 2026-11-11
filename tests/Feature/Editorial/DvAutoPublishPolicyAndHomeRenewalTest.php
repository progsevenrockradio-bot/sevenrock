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
use Carbon\Carbon;
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

        // Fijar fecha y hora para pruebas deterministas (2026-11-11 Europe/Madrid)
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:30:00', 'Europe/Madrid'));

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
        Carbon::setTestNow(null);
        $fullPath = storage_path('app/public/posts/covers/post-dv-test-cover.jpg');
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        parent::tearDown();
    }

    /**
     * Helper para generar un manifiesto con metadatos estructurados de imagen
     *
     * @param array<int, string> $elementIds
     * @return array<string, mixed>
     */
    protected function generateSampleManifest(array $elementIds): array
    {
        $manifest = [];
        foreach ($elementIds as $id) {
            $manifest[$id] = [
                'path' => $this->testCoverPath,
                'meta' => [
                    'url'         => 'https://upload.wikimedia.org/wikipedia/commons/4/46/Test_Photo.jpg',
                    'artist'      => 'Fotografo Acreditado',
                    'license'     => 'CC BY-SA 4.0',
                    'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                    'credit'      => 'Foto: Fotografo Acreditado / Wikimedia Commons (CC BY-SA 4.0)',
                    'alt'         => 'Fotografía acreditada para pruebas',
                    'is_archive'  => true,
                ],
            ];
        }
        return $manifest;
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
        string $editorialDate = '2026-11-11',
        string|array $fuentes = 'https://blabbermouth.net/news/valid-rock-news',
        string $revision = 'NO',
        string $motivo = 'NINGUNO',
        string $imageCredit = 'Foto: Fotografo Acreditado / Wikimedia Commons (CC BY-SA 4.0)',
        ?string $customText = null,
        ?string $fuentePrimaria = 'https://oficial.banda.com/comunicado-oficial',
        ?string $corroboracion = 'https://prensa-rock-independiente.com/confirmacion',
        ?string $afirmaciones = 'Lanzamiento confirmado por la banda; gira programada para 2027',
        string $discrepancias = 'NINGUNA'
    ): string {
        $fuentesText = '';
        if (is_array($fuentes)) {
            foreach ($fuentes as $f) {
                $fuentesText .= "- {$f}\n";
            }
        } else {
            $fuentesText = "- {$fuentes}\n";
        }

        $text = $customText ?: "Este es un texto completo y profesional para la nota editorial del artista {$artist}, detallando su nuevo acontecimiento y novedades con mas de sesenta caracteres para superar las validaciones de longitud requeridas.";

        $evidenceBlock = '';
        if ($fuentePrimaria !== null) {
            $evidenceBlock .= "FUENTE_PRIMARIA: {$fuentePrimaria}\n";
        }
        if ($corroboracion !== null) {
            $evidenceBlock .= "CORROBORACION: {$corroboracion}\n";
        }
        if ($afirmaciones !== null) {
            $evidenceBlock .= "AFIRMACIONES_VERIFICADAS: {$afirmaciones}\n";
        }
        if ($discrepancias !== '') {
            $evidenceBlock .= "DISCREPANCIAS: {$discrepancias}\n";
        }

        return <<<ELEM
=== INICIO ELEMENTO ===
ID: {$id}
TIPO: {$tipo}
FECHA_EDITORIAL: {$editorialDate}
FECHA_ACONTECIMIENTO: {$eventDate}
ARTISTA_BANDA: {$artist}
TITULO: {$title}
TEXTO:
{$text}
{$evidenceBlock}FUENTES:
{$fuentesText}IMAGEN_URL: {$this->testCoverPath}
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
        $elementIds = [];

        // 7 Noticias con evidencia estructurada
        for ($i = 1; $i <= 7; $i++) {
            $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $id = "DV-20261111-VAR-E{$num}";
            $elementIds[] = $id;
            $elementsText[] = $this->generateDv1Element(
                id: $id,
                tipo: 'NOTICIA',
                artist: "Banda Noticia {$i}",
                title: "Banda Noticia {$i} anuncia nuevo disco y gira mundial para 2027",
                eventDate: '2026-11-10',
                fuentes: [
                    "https://oficial.banda{$i}.com/comunicado",
                    "https://prensa-rock-independiente.com/banda-{$i}",
                ],
                fuentePrimaria: "https://oficial.banda{$i}.com/comunicado",
                corroboracion: "https://prensa-rock-independiente.com/banda-{$i}"
            );
        }

        // 7 Efemérides (con titular histórico y fecha pasada)
        for ($j = 8; $j <= 14; $j++) {
            $num = str_pad((string) $j, 2, '0', STR_PAD_LEFT);
            $id = "DV-20261111-VAR-E{$num}";
            $elementIds[] = $id;
            $year = 1970 + $j;
            $elementsText[] = $this->generateDv1Element(
                id: $id,
                tipo: 'EFEMERIDE',
                artist: "Leyenda Rock {$j}",
                title: "Efeméride ({$year}): Nace músico legendario {$j} en Londres",
                eventDate: "{$year}-11-11",
                fuentes: "https://discogs.com/artist/leyenda-{$j}",
                fuentePrimaria: "https://discogs.com/artist/leyenda-{$j}",
                corroboracion: "Archivo discográfico contrastado",
                afirmaciones: "Nacimiento del músico en Londres registrado en registros oficiales"
            );
        }

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-20261111-VAR\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 14\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $this->generateSampleManifest($elementIds),
            sourceMessageId: '<dv-msg-var-14@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
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
        $manifest = $this->generateSampleManifest([
            'DV-PART-E01', 'DV-PART-E02', 'DV-PART-E03',
            'DV-PART-E04', 'DV-PART-E05', 'DV-PART-E06',
        ]);

        // E04: Simular manifiesto sin acreditación estructurada (licencia desconocida)
        $manifest['DV-PART-E04']['meta'] = [
            'url' => 'https://instagram.com/photo.jpg',
            'artist' => 'Desconocido',
            'license' => 'Todos los derechos reservados',
            'is_archive' => false,
        ];

        $elementsText = [
            // E01: Noticia VÁLIDA -> Debe publicarse
            $this->generateDv1Element(
                'DV-PART-E01', 'NOTICIA', 'Kreator', 'Kreator confirman nuevo álbum para 2027', '2026-11-10',
                fuentes: ['https://kreator-terrorzone.de/news', 'https://prensa-metal.de/kreator'],
                fuentePrimaria: 'https://kreator-terrorzone.de/news',
                corroboracion: 'https://prensa-metal.de/kreator'
            ),

            // E02: Noticia con REVISIÓN_REQUERIDA = SI -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E02', 'NOTICIA', 'Megadeth', 'Megadeth anuncia festival europeo en 2027', '2026-11-10', revision: 'SI', motivo: 'Discrepancia en fechas de concierto'),

            // E03: Noticia con URL insegura con credenciales incrustadas -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E03', 'NOTICIA', 'Anthrax', 'Anthrax prepara gira de 40 aniversario', '2026-11-10', fuentes: 'https://admin:pass123@blabbermouth.net/news/anthrax'),

            // E04: Noticia con imagen SIN acreditación estructurada -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E04', 'NOTICIA', 'Overkill', 'Overkill estrena single de adelanto', '2026-11-10', imageCredit: 'Foto: Instagram oficial sin derechos conocidos'),

            // E05: Efeméride con titular que NO identifica hecho histórico (parece actualidad) -> Debe quedar en borrador
            $this->generateDv1Element('DV-PART-E05', 'EFEMERIDE', 'Artista X', 'Muere guitarrista legendario', '1982-11-11'),

            // E06: Efeméride VÁLIDA con titular histórico -> Debe publicarse
            $this->generateDv1Element('DV-PART-E06', 'EFEMERIDE', 'Artista Y', 'Efeméride (1985): Nace cantante de power metal en Hamburgo', '1985-11-11', fuentes: 'https://discogs.com/artist/artista-y', fuentePrimaria: 'https://discogs.com/artist/artista-y'),
        ];

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-PART-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 6\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<dv-msg-part-01@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
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
        $this->assertStringContainsString('Permiso de imagen no acreditado mediante evidencia estructurada', $postE04->sanitizedMeta->editorial_notes);

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
        $elementIds = [];
        for ($k = 1; $k <= 6; $k++) {
            $num = str_pad((string) $k, 2, '0', STR_PAD_LEFT);
            $id = "DV-HOME-E{$num}";
            $elementIds[] = $id;
            $elementsText[] = $this->generateDv1Element(
                id: $id,
                tipo: 'NOTICIA',
                artist: "Banda Nueva {$k}",
                title: "Banda Nueva {$k} estrena videoclip oficial",
                eventDate: '2026-11-11',
                fuentes: [
                    "https://oficial.bandanueva{$k}.com/video",
                    "https://prensa-rock-independiente.com/bandanueva-{$k}",
                ],
                fuentePrimaria: "https://oficial.bandanueva{$k}.com/video",
                corroboracion: "https://prensa-rock-independiente.com/bandanueva-{$k}"
            );
        }

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-HOME-BATCH\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 6\n\n" . implode("\n\n", $elementsText);

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $this->generateSampleManifest($elementIds),
            sourceMessageId: '<dv-msg-home-batch@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

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
        $elem = $this->generateDv1Element(
            'DV-IDEMP-E01', 'NOTICIA', 'Saxon', 'Saxon anuncia gira británica 2027', '2026-11-11',
            fuentes: ['https://saxon747.com/tour-dates', 'https://prensa-rock-independiente.com/saxon'],
            fuentePrimaria: 'https://saxon747.com/tour-dates',
            corroboracion: 'https://prensa-rock-independiente.com/saxon'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-IDEMP-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elem;

        $manifest = $this->generateSampleManifest(['DV-IDEMP-E01']);
        $processor = app(EditorialBatchProcessor::class);

        // Primer envío -> Se publica
        $res1 = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<dv-msg-idemp-01@gmail.com>',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );
        $this->assertSame(1, $res1['created']);
        $this->assertSame(1, $res1['auto_published_noticias']);

        $post1 = Post::where('slug', 'like', '%saxon%')->first();
        $originalPublishedAt = $post1->published_at;

        // Segundo envío idéntico (reintento) -> Se omite (skipped)
        $res2 = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<dv-msg-idemp-01@gmail.com>',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );
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

        $res3 = $processor->process(
            rawContent: $rawBatchModified,
            imagesManifest: $manifest,
            sourceMessageId: '<dv-msg-idemp-01@gmail.com>',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );
        $this->assertSame(1, $res3['conflicts']);

        // REGLA CRÍTICA: El post publicado permanece intacto, publicado y sin despublicar
        $post1->refresh();
        $this->assertSame('published', $post1->status);
        $this->assertTrue((bool) $post1->is_published);
        $this->assertStringContainsString('gira británica 2027', $post1->title);
        $this->assertStringNotContainsString('CANCELADA', $post1->title);
    }

    /**
     * TEST 5: Rechazo por autenticación ausente o no verificada.
     * Si no se acredita autenticación del remitente, el lote crea estrictamente borradores.
     */
    public function test_rejection_when_sender_authentication_is_missing_or_unverified(): void
    {
        $elem = $this->generateDv1Element(
            'DV-AUTH-E01', 'NOTICIA', 'Accept', 'Accept lanza nuevo single en directo', '2026-11-10',
            fuentes: ['https://oficial.accept.com/single', 'https://prensa-metal.com/accept'],
            fuentePrimaria: 'https://oficial.accept.com/single',
            corroboracion: 'https://prensa-metal.com/accept'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-AUTH-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elem;

        $manifest = $this->generateSampleManifest(['DV-AUTH-E01']);
        $processor = app(EditorialBatchProcessor::class);

        // Envío con autenticación deshabilitada (isSenderAuthenticated: false)
        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<unverified-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: false,
            allowLocalAutoPublish: false
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);
        $this->assertSame(1, $result['created']);

        $post = Post::where('slug', 'like', '%accept%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertNull($post->published_at);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-AUTH-E01')->first();
        $this->assertNotNull($meta);
        $this->assertNull($meta->approved_by);
        $this->assertSame('needs_review', $meta->security_status);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $meta->editorial_notes);
        $this->assertStringContainsString('Remitente no autenticado', $meta->editorial_notes);
    }

    /**
     * TEST 6: Rechazo cuando el texto del artículo contiene credenciales o contraseñas.
     * La validación final exhaustiva debe bloquear la publicación y conservar el borrador.
     */
    public function test_rejection_when_post_text_contains_credentials_or_tokens(): void
    {
        $elemWithPassword = $this->generateDv1Element(
            id: 'DV-CRED-E01',
            tipo: 'NOTICIA',
            artist: 'Blind Guardian',
            title: 'Blind Guardian presenta edición especial de aniversario',
            eventDate: '2026-11-10',
            fuentes: ['https://blind-guardian.com/news', 'https://prensa-metal.com/bg'],
            customText: "Contenido editorial sobre el lanzamiento especial. Para acceder a la descarga privada exclusiva utilice la Contraseña: SuperSecretToken2026! que no debe publicarse abiertamente.",
            fuentePrimaria: 'https://blind-guardian.com/news',
            corroboracion: 'https://prensa-metal.com/bg'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-CRED-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elemWithPassword;

        $manifest = $this->generateSampleManifest(['DV-CRED-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<cred-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%blind-guardian%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-CRED-E01')->first();
        $this->assertNotNull($meta);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $meta->editorial_notes);
        $this->assertStringContainsString('Validación de seguridad final fallida', $meta->editorial_notes);
        $this->assertStringContainsString('credencial', strtolower($meta->editorial_notes));
    }

    /**
     * TEST 7: Rechazo cuando el artículo o sus fuentes están bajo embargo.
     */
    public function test_rejection_when_post_or_source_is_under_embargo(): void
    {
        $elemEmbargo = $this->generateDv1Element(
            id: 'DV-EMB-E01',
            tipo: 'NOTICIA',
            artist: 'Opeth',
            title: 'Opeth anuncia nueva gira CONFIDENCIAL: BAJO EMBARGO',
            eventDate: '2026-11-10',
            fuentes: ['https://opeth.com/news', 'https://prensa-metal.com/opeth'],
            customText: "[EMBARGO HASTA 2026-12-01] Este material es estrictamente confidencial hasta la fecha acordada con la banda y la discográfica.",
            fuentePrimaria: 'https://opeth.com/news',
            corroboracion: 'https://prensa-metal.com/opeth'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-EMB-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elemEmbargo;

        $manifest = $this->generateSampleManifest(['DV-EMB-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<emb-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%opeth%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-EMB-E01')->first();
        $this->assertNotNull($meta);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $meta->editorial_notes);
        $this->assertStringContainsString('embargo', strtolower($meta->editorial_notes));
    }

    /**
     * TEST 8: Rechazo cuando la imagen tiene crédito declarado pero carece de acreditación estructurada.
     * No se concede permiso por prefijo de ruta ni por regex sobre el texto de crédito.
     */
    public function test_rejection_when_image_has_declared_credit_but_lacks_structured_accreditation(): void
    {
        $elem = $this->generateDv1Element(
            id: 'DV-NOSTR-E01',
            tipo: 'NOTICIA',
            artist: 'Testament',
            title: 'Testament anuncia conciertos en festivales de verano',
            eventDate: '2026-11-10',
            fuentes: ['https://testamentlegions.com/news', 'https://prensa-metal.com/testament'],
            imageCredit: 'Foto: Fotografo No Verificado / Wikimedia Commons (CC BY-SA 4.0)',
            fuentePrimaria: 'https://testamentlegions.com/news',
            corroboracion: 'https://prensa-metal.com/testament'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-NOSTR-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elem;

        // Manifest vacío: no hay evidencia estructurada de la imagen
        $manifest = [];

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<nostr-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%testament%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-NOSTR-E01')->first();
        $this->assertNotNull($meta);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $meta->editorial_notes);
        $this->assertStringContainsString('evidencia estructurada', $meta->editorial_notes);
    }

    /**
     * TEST 9: Rechazo cuando la fecha de acontecimiento de una efeméride no coincide en mes y día con la fecha editorial.
     */
    public function test_rejection_when_efemeride_date_does_not_match_editorial_day_and_month(): void
    {
        // Fecha editorial: 2026-11-11. Fecha acontecimiento: 1975-10-15 (octubre vs noviembre)
        $elem = $this->generateDv1Element(
            id: 'DV-DATE-E01',
            tipo: 'EFEMERIDE',
            artist: 'Black Sabbath',
            title: 'Efeméride (1975): Black Sabbath lanza su disco histórico',
            eventDate: '1975-10-15',
            editorialDate: '2026-11-11',
            fuentes: 'https://discogs.com/artist/black-sabbath',
            fuentePrimaria: 'https://discogs.com/artist/black-sabbath',
            corroboracion: 'Archivo discográfico contrastado'
        );
        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-DATE-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n" . $elem;

        $manifest = $this->generateSampleManifest(['DV-DATE-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<date-mismatch-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%black-sabbath%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-DATE-E01')->first();
        $this->assertNotNull($meta);
        $this->assertStringContainsString('[BORRADOR_POR_POLITICA]', $meta->editorial_notes);
        $this->assertStringContainsString('no coincide con el día y mes de la fecha editorial', $meta->editorial_notes);
    }

    /**
     * TEST 10: Rechazo por fecha de acontecimiento no histórica para efemérides.
     */
    public function test_rejection_when_event_date_is_invalid_or_not_historical_for_efemerides(): void
    {
        // Caso: Fecha de efeméride del año actual o futuro (no histórica)
        $elemNotHistorical = $this->generateDv1Element(
            id: 'DV-INVAL-E02',
            tipo: 'EFEMERIDE',
            artist: 'Judas Priest',
            title: 'Efeméride (2026): Judas Priest concierto especial',
            eventDate: '2026-11-11',
            editorialDate: '2026-11-11',
            fuentes: 'https://discogs.com/judas-priest',
            fuentePrimaria: 'https://discogs.com/judas-priest',
            corroboracion: 'Archivo discográfico contrastado'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-INVAL-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemNotHistorical}";

        $manifest = $this->generateSampleManifest(['DV-INVAL-E02']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<invalid-date-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $meta2 = EmailPostSanitizedMeta::where('element_id', 'DV-INVAL-E02')->first();
        $this->assertStringContainsString('Fecha de efeméride no es histórica', $meta2->editorial_notes);
    }

    /**
     * TEST 11: Corrección de expresión regular de año histórico (como 1972 en Berry Oakley).
     */
    public function test_historical_year_regex_identifies_1972_accurately(): void
    {
        $elemOakley = $this->generateDv1Element(
            id: 'DV-FIX-E01',
            tipo: 'EFEMERIDE',
            artist: 'Berry Oakley',
            title: 'Berry Oakley (1972): Se cumplen 54 años del fallecimiento del bajista',
            eventDate: '1972-11-11',
            editorialDate: '2026-11-11',
            fuentes: 'https://discogs.com/artist/berry-oakley',
            fuentePrimaria: 'https://discogs.com/artist/berry-oakley',
            corroboracion: 'Archivo histórico biográfico contrastado',
            afirmaciones: 'Fallecimiento de Berry Oakley el 11 de noviembre de 1972 en Florida'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-FIX-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemOakley}";

        $manifest = $this->generateSampleManifest(['DV-FIX-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<fix-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['auto_published_efemerides']);
        $this->assertSame(1, $result['auto_published_total']);

        $postOakley = Post::where('slug', 'like', '%berry-oakley%')->first();
        $this->assertNotNull($postOakley);
        $this->assertSame('published', $postOakley->status);
        $this->assertTrue((bool) $postOakley->is_published);
    }

    /**
     * TEST 12: Rechazo cuando las fuentes en distintos dominios son en realidad sindicaciones sin corroboración independiente.
     */
    public function test_rejection_when_sources_are_syndicated_across_different_domains_without_independent_corroboration(): void
    {
        $elemSyndicated = $this->generateDv1Element(
            id: 'DV-SYND-E01',
            tipo: 'NOTICIA',
            artist: 'Helloween',
            title: 'Helloween anuncia nuevo festival internacional',
            eventDate: '2026-11-10',
            editorialDate: '2026-11-11',
            fuentes: [
                'https://metal-wire-a.com/pr/helloween-pr-identical',
                'https://metal-wire-b.com/pr/helloween-pr-identical',
            ],
            fuentePrimaria: 'https://metal-wire-a.com/pr/helloween-pr-identical',
            corroboracion: 'NINGUNA (republicación sindicada de la misma nota de prensa sin corroboración independiente)',
            afirmaciones: 'Anuncio de festival sin declaraciones exclusivas'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-SYND-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemSyndicated}";

        $manifest = $this->generateSampleManifest(['DV-SYND-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<synd-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%helloween%')->first();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-SYND-E01')->first();
        $this->assertStringContainsString('fuentes sindicadas o agregadas en distintos dominios no equivalen a corroboración independiente', $meta->editorial_notes);
    }

    /**
     * TEST 13: Rechazo cuando falta la evidencia estructurada de investigación (fuente original o afirmaciones).
     */
    public function test_rejection_when_structured_research_evidence_is_missing(): void
    {
        $elemMissingEvidence = $this->generateDv1Element(
            id: 'DV-MISSEV-E01',
            tipo: 'NOTICIA',
            artist: 'Gamma Ray',
            title: 'Gamma Ray anuncia nuevo single de estudio',
            eventDate: '2026-11-10',
            editorialDate: '2026-11-11',
            fuentes: ['https://gamma-ray.com/news', 'https://prensa-metal.com/gr'],
            fuentePrimaria: null, // Evidencia ausente
            corroboracion: null,
            afirmaciones: null
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-MISSEV-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemMissingEvidence}";

        $manifest = $this->generateSampleManifest(['DV-MISSEV-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<missev-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%gamma-ray%')->first();
        $this->assertSame('draft', $post->status);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-MISSEV-E01')->first();
        $this->assertStringContainsString('Evidencia editorial incompleta', $meta->editorial_notes);
    }

    /**
     * TEST 14: Prevención de falsos positivos en español.
     * Frases corrientes como «todo el disco» NO deben disparar el detector de TODO.
     */
    public function test_false_positive_prevention_spanish_phrase_with_todo_is_not_rejected(): void
    {
        $spanishText = "La legendaria banda alemana interpretará todo el disco en directo durante su próxima gira europea, repasando todos los clásicos ante todo su público fiel con una puesta en escena espectacular.";

        $elemSpanish = $this->generateDv1Element(
            id: 'DV-TODO-E01',
            tipo: 'NOTICIA',
            artist: 'Scorpions',
            title: 'Scorpions interpretará todo su clásico álbum en directo',
            eventDate: '2026-11-10',
            editorialDate: '2026-11-11',
            fuentes: ['https://the-scorpions.com/tour-news', 'https://prensa-metal.com/scorpions'],
            customText: $spanishText,
            fuentePrimaria: 'https://the-scorpions.com/tour-news',
            corroboracion: 'https://prensa-metal.com/scorpions',
            afirmaciones: 'Interpretación íntegra de álbum clásico en gira europea'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-TODO-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemSpanish}";

        $manifest = $this->generateSampleManifest(['DV-TODO-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<todo-test-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['auto_published_noticias']);
        $this->assertSame(1, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%scorpions%')->first();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
    }

    /**
     * TEST 15: Rechazo estricto ante fecha de calendario inexistente / imposible (ej. 2026-02-31 o 2026-04-31).
     * No debe normalizarse automáticamente hacia marzo o mayo.
     */
    public function test_rejection_when_calendar_date_is_impossible_such_as_february_31(): void
    {
        $elemImpossibleDate = $this->generateDv1Element(
            id: 'DV-IMPOSS-E01',
            tipo: 'NOTICIA',
            artist: 'Dio',
            title: 'Dio reedición histórica anunciada',
            eventDate: '2026-02-31', // Fecha imposible: 31 de febrero no existe en el calendario
            editorialDate: '2026-11-11',
            fuentes: ['https://ronniejamesdio.com/news', 'https://prensa-metal.com/dio'],
            fuentePrimaria: 'https://ronniejamesdio.com/news',
            corroboracion: 'https://prensa-metal.com/dio'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-IMPOSS-01\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemImpossibleDate}";

        $manifest = $this->generateSampleManifest(['DV-IMPOSS-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<imposs-date-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%dio%')->first();
        $this->assertSame('draft', $post->status);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-IMPOSS-E01')->first();
        $this->assertStringContainsString('Fecha inexistente en el calendario gregoriano', $meta->editorial_notes);
    }

    /**
     * TEST 16: Rechazo ante lote con fecha editorial futura.
     */
    public function test_rejection_when_batch_editorial_date_is_in_the_future(): void
    {
        // Fecha editorial futura: 2026-11-15 (cuando hoy es 2026-11-11 Europe/Madrid)
        $elemFuture = $this->generateDv1Element(
            id: 'DV-FUT-E01',
            tipo: 'NOTICIA',
            artist: 'W.A.S.P.',
            title: 'W.A.S.P. anuncia nueva gira mundial',
            eventDate: '2026-11-11',
            editorialDate: '2026-11-15',
            fuentes: ['https://waspnation.com/news', 'https://prensa-metal.com/wasp'],
            fuentePrimaria: 'https://waspnation.com/news',
            corroboracion: 'https://prensa-metal.com/wasp'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-FUT-01\nFECHA_EDITORIAL: 2026-11-15\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemFuture}";

        $manifest = $this->generateSampleManifest(['DV-FUT-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<future-date-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%wasp%')->first();
        $this->assertSame('draft', $post->status);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-FUT-E01')->first();
        $this->assertStringContainsString('Fecha editorial futura no permitida', $meta->editorial_notes);
    }

    /**
     * TEST 17: Rechazo de efeméride recibida fuera de su día editorial.
     * Una efeméride de ayer no debe publicarse hoy automáticamente como la efeméride del día.
     */
    public function test_rejection_when_efemeride_is_received_outside_its_editorial_day(): void
    {
        // Efeméride histórica del 10 de noviembre con fecha editorial 2026-11-10 recibida cuando hoy es 2026-11-11
        $elemYesterday = $this->generateDv1Element(
            id: 'DV-YEST-E01',
            tipo: 'EFEMERIDE',
            artist: 'Ronnie Montrose',
            title: 'Efeméride (1973): Montrose lanza su histórico disco debut',
            eventDate: '1973-11-10',
            editorialDate: '2026-11-10', // Fecha de ayer respecto a hoy 2026-11-11
            fuentes: 'https://discogs.com/artist/montrose',
            fuentePrimaria: 'https://discogs.com/artist/montrose',
            corroboracion: 'Archivo histórico discográfico'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-YEST-01\nFECHA_EDITORIAL: 2026-11-10\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemYesterday}";

        $manifest = $this->generateSampleManifest(['DV-YEST-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<yesterday-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%montrose%')->first();
        $this->assertSame('draft', $post->status);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-YEST-E01')->first();
        $this->assertStringContainsString('Lote fuera de fecha editorial diaria', $meta->editorial_notes);
    }

    /**
     * TEST 18: Efeméride de año bisiesto (29 de febrero) recibida en año común (2026).
     * No se traslada automáticamente al 28 de febrero; se retiene como borrador para decisión editorial expresa.
     */
    public function test_efemeride_leap_year_february_29_in_common_year_is_held_for_editorial_decision(): void
    {
        // Simular que el día actual de la prueba es el 28 de febrero de 2026
        Carbon::setTestNow(Carbon::parse('2026-02-28 10:00:00', 'Europe/Madrid'));

        $elemLeap = $this->generateDv1Element(
            id: 'DV-LEAP-E01',
            tipo: 'EFEMERIDE',
            artist: 'Músico Bisiesto',
            title: 'Músico Bisiesto (1968): Nace baterista en fecha bisiesta',
            eventDate: '1968-02-29', // 29 de febrero
            editorialDate: '2026-02-28', // Año común 2026
            fuentes: 'https://discogs.com/artist/musico-bisiesto',
            fuentePrimaria: 'https://discogs.com/artist/musico-bisiesto',
            corroboracion: 'Archivo histórico biográfico',
            afirmaciones: 'Nacimiento verificado en fecha bisiesta 29 de febrero de 1968'
        );

        $rawBatch = "FORMATO: DV-1\nLOTE_ID: DV-LEAP-01\nFECHA_EDITORIAL: 2026-02-28\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemLeap}";

        $manifest = $this->generateSampleManifest(['DV-LEAP-E01']);
        $processor = app(EditorialBatchProcessor::class);

        $result = $processor->process(
            rawContent: $rawBatch,
            imagesManifest: $manifest,
            sourceMessageId: '<leap-decision-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['auto_published_total']);

        $post = Post::where('slug', 'like', '%musico-bisiesto%')->first();
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);

        $meta = EmailPostSanitizedMeta::where('element_id', 'DV-LEAP-E01')->first();
        $this->assertStringContainsString('Efeméride de año bisiesto (29 de febrero) recibida en año común', $meta->editorial_notes);
        $this->assertStringContainsString('requiere criterio y revisión editorial expresa', $meta->editorial_notes);
    }

    /**
     * TEST 19: Simulación (Dry-Run) sobre lote nuevo sin reutilizar artículos existentes.
     * Comprueba que las piezas válidas pueden publicarse y que las incompletas
     * quedan individualmente en borrador, sin persistir nada en la base de datos.
     */
    public function test_simulation_dry_run_on_new_lot_without_publishing_or_reusing_articles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:30:00', 'Europe/Madrid'));

        $batchPath = storage_path('editorial_batches/DV-SIM-20261111/batch.txt');
        $manifestPath = storage_path('editorial_batches/DV-SIM-20261111/images_installed.json');

        $this->assertFileExists($batchPath);
        $this->assertFileExists($manifestPath);

        $rawContent = File::get($batchPath);
        $imagesManifest = json_decode(File::get($manifestPath), true);

        $initialPostsCount = Post::count();
        $initialMetaCount = EmailPostSanitizedMeta::count();

        $processor = app(EditorialBatchProcessor::class);
        $result = $processor->process(
            rawContent: $rawContent,
            imagesManifest: $imagesManifest,
            sourceMessageId: '<simulation-dry-run-msg@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            isDryRun: true,
            allowUpdateApproval: false,
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($result['success']);
        $this->assertSame('DV-SIM-20261111', $result['batch_id']);
        $this->assertSame(4, $result['total_extracted']);

        // En simulación: 2 calificarían para auto-publicación (1 noticia, 1 efeméride)
        $this->assertSame(1, $result['auto_published_noticias']);
        $this->assertSame(1, $result['auto_published_efemerides']);
        $this->assertSame(2, $result['auto_published_total']);

        // Comprobación individual de piezas en la simulación:
        // E01 (Accept - Noticia válida con investigación completa): Califica para auto-publicación
        $this->assertSame('would_auto_publish', $result['elements_detail']['DV-SIM2611-E01']['action']);
        $this->assertTrue($result['elements_detail']['DV-SIM2611-E01']['can_auto_publish']);
        $this->assertSame('published', $result['elements_detail']['DV-SIM2611-E01']['status']);

        // E02 (Overkill - Noticia con fuentes sindicadas sin corroboración): Queda en borrador
        $this->assertSame('would_create_draft', $result['elements_detail']['DV-SIM2611-E02']['action']);
        $this->assertFalse($result['elements_detail']['DV-SIM2611-E02']['can_auto_publish']);
        $this->assertSame('draft', $result['elements_detail']['DV-SIM2611-E02']['status']);
        $this->assertStringContainsString('fuentes sindicadas', $result['elements_detail']['DV-SIM2611-E02']['reason']);

        // E03 (Bruce Dickinson 1981 - Efeméride histórica válida): Califica para auto-publicación
        $this->assertSame('would_auto_publish', $result['elements_detail']['DV-SIM2611-E03']['action']);
        $this->assertTrue($result['elements_detail']['DV-SIM2611-E03']['can_auto_publish']);
        $this->assertSame('published', $result['elements_detail']['DV-SIM2611-E03']['status']);

        // E04 (Deep Purple - Efeméride con fecha parcial no confirmada): Queda en borrador
        $this->assertSame('would_create_draft', $result['elements_detail']['DV-SIM2611-E04']['action']);
        $this->assertFalse($result['elements_detail']['DV-SIM2611-E04']['can_auto_publish']);
        $this->assertSame('draft', $result['elements_detail']['DV-SIM2611-E04']['status']);
        $this->assertStringContainsString('no confirmada o parcial', $result['elements_detail']['DV-SIM2611-E04']['reason']);

        // REGLA CRÍTICA DE SIMULACIÓN: Cero cambios persistidos en la base de datos
        $this->assertSame($initialPostsCount, Post::count(), 'En simulación (dry-run) no debe crearse ningún Post en base de datos.');
        $this->assertSame($initialMetaCount, EmailPostSanitizedMeta::count(), 'En simulación (dry-run) no debe crearse ningún registro de metadata en base de datos.');
    }

    /**
     * Prueba exhaustiva del formato prompt_diaria_reanudada_v3:
     * - CORROBORACION con URL + texto explicativo.
     * - AFIRMACIONES_VERIFICADAS con múltiples líneas.
     * - Fechas parciales o NO_CONFIRMADA que son forzadas estrictamente a borrador.
     */
    public function test_parser_extracts_multiline_verified_claims_and_corroboration_with_explanation_and_rejects_partial_or_unconfirmed_dates(): void
    {
        $multilineClaims = "- Gira mundial de 2027 confirmada con fechas en Norteamérica y Europa\n- Primer single anunciado para el 15 de marzo de 2027\n- Grabado con el productor Tom Allom en Birmingham";
        $corroboracionWithExplanation = "https://blabbermouth.net/news/judas-priest-tour-2027 Reporte periodístico independiente de Blabbermouth confirmando los recintos y fechas de la gira";

        $elemText = $this->generateDv1Element(
            id: 'DV-PARSE-01',
            tipo: 'NOTICIA',
            artist: 'Judas Priest',
            title: 'Judas Priest anuncia gira mundial para 2027',
            eventDate: '2026-11-10',
            editorialDate: '2026-11-11',
            fuentes: 'https://blabbermouth.net/news/judas-priest-tour-2027',
            fuentePrimaria: 'https://judaspriest.com/news/tour-2027',
            corroboracion: $corroboracionWithExplanation,
            afirmaciones: $multilineClaims,
            discrepancias: 'NINGUNA'
        );

        $parser = app(\App\Services\EditorialDarthVaderParser::class);
        $subject = '[DARTH-VADER][DV-1][2026-11-11][DV-TEST-BATCH]';
        $body = "FORMATO: DV-1\nLOTE_ID: DV-TEST-BATCH\nFECHA_EDITORIAL: 2026-11-11\n{$elemText}";
        $parsed = $parser->parseDv1($subject, $body);

        $this->assertCount(1, $parsed['elements']);
        $element = $parsed['elements'][0];

        // 1. Acreditar extracción completa de CORROBORACION (URL + texto)
        $this->assertStringContainsString('https://blabbermouth.net/news/judas-priest-tour-2027', $element['corroboracion']);
        $this->assertStringContainsString('Reporte periodístico independiente de Blabbermouth', $element['corroboracion']);

        // 2. Acreditar extracción completa de AFIRMACIONES_VERIFICADAS multilínea
        $this->assertStringContainsString('Gira mundial de 2027 confirmada', $element['afirmaciones_verificadas']);
        $this->assertStringContainsString('Primer single anunciado para el 15 de marzo de 2027', $element['afirmaciones_verificadas']);
        $this->assertStringContainsString('Grabado con el productor Tom Allom', $element['afirmaciones_verificadas']);

        // 3. Evaluar política automática: la pieza con fecha válida y fuentes seguras debe calificar
        $processor = app(EditorialBatchProcessor::class);
        $manifest = $this->generateSampleManifest(['DV-PARSE-01']);
        $rawBatchValid = "FORMATO: DV-1\nLOTE_ID: DV-TEST-BATCH\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemText}";
        $resultValid = $processor->process(
            rawContent: $rawBatchValid,
            imagesManifest: $manifest,
            sourceMessageId: '<test-valid-v3@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            isDryRun: true,
            allowUpdateApproval: false,
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertTrue($resultValid['elements_detail']['DV-PARSE-01']['can_auto_publish']);
        $this->assertSame('published', $resultValid['elements_detail']['DV-PARSE-01']['status']);

        // 4. Evaluar que FECHA_ACONTECIMIENTO = 'NO_CONFIRMADA' queda estrictamente en borrador
        $elemNoConfirmada = $this->generateDv1Element(
            id: 'DV-PARSE-02',
            tipo: 'EFEMERIDE',
            artist: 'Black Sabbath',
            title: 'Black Sabbath publica álbum histórico en 1970',
            eventDate: 'NO_CONFIRMADA',
            editorialDate: '2026-11-11',
            corroboracion: $corroboracionWithExplanation,
            afirmaciones: $multilineClaims
        );
        $rawBatchNoConf = "FORMATO: DV-1\nLOTE_ID: DV-TEST-BATCH-2\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemNoConfirmada}";

        $resultNoConf = $processor->process(
            rawContent: $rawBatchNoConf,
            imagesManifest: $this->generateSampleManifest(['DV-PARSE-02']),
            sourceMessageId: '<test-no-conf@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            isDryRun: true,
            allowUpdateApproval: false,
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertFalse($resultNoConf['elements_detail']['DV-PARSE-02']['can_auto_publish']);
        $this->assertSame('draft', $resultNoConf['elements_detail']['DV-PARSE-02']['status']);
        $this->assertStringContainsString('no confirmada o parcial', $resultNoConf['elements_detail']['DV-PARSE-02']['reason']);

        // 5. Evaluar que fecha parcial como '1972-11-XX' queda estrictamente en borrador
        $elemParcial = $this->generateDv1Element(
            id: 'DV-PARSE-03',
            tipo: 'EFEMERIDE',
            artist: 'Deep Purple',
            title: 'Deep Purple graba sesión histórica en 1972',
            eventDate: '1972-11-XX',
            editorialDate: '2026-11-11',
            corroboracion: $corroboracionWithExplanation,
            afirmaciones: $multilineClaims
        );
        $rawBatchParcial = "FORMATO: DV-1\nLOTE_ID: DV-TEST-BATCH-3\nFECHA_EDITORIAL: 2026-11-11\nZONA_HORARIA: Europe/Madrid\nTOTAL_ELEMENTOS: 1\n\n{$elemParcial}";

        $resultParcial = $processor->process(
            rawContent: $rawBatchParcial,
            imagesManifest: $this->generateSampleManifest(['DV-PARSE-03']),
            sourceMessageId: '<test-parcial@gmail.com>',
            senderEmail: 'dark.vader.agent@gmail.com',
            isDryRun: true,
            allowUpdateApproval: false,
            autoPublish: true,
            isSenderAuthenticated: true,
            allowLocalAutoPublish: true
        );

        $this->assertFalse($resultParcial['elements_detail']['DV-PARSE-03']['can_auto_publish']);
        $this->assertSame('draft', $resultParcial['elements_detail']['DV-PARSE-03']['status']);
        $this->assertStringContainsString('no confirmada o parcial', $resultParcial['elements_detail']['DV-PARSE-03']['reason']);
    }
}
