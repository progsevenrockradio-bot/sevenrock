<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Services\EditorialImageService;
use App\Services\EditorialRecoveryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditorialRecoveryAndReevaluationTest extends TestCase
{
    use RefreshDatabase;

    private string $validPngBytes;
    private string $wikimediaPageUrl = 'https://commons.wikimedia.org/wiki/File:Rock_Recovery_Test_1976.png';
    private string $wikimediaDirectUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Rock_Recovery_Test_1976.png';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Generar PNG real >= 10KB (ruido pseudoaleatorio no comprimible) para superar MIN_SIZE_BYTES (4096) y optimización GD
        $img = imagecreatetruecolor(240, 180);
        mt_srand(42);
        for ($y = 0; $y < 180; $y++) {
            for ($x = 0; $x < 240; $x++) {
                $col = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                imagesetpixel($img, $x, $y, $col);
            }
        }
        ob_start();
        imagepng($img);
        $this->validPngBytes = (string) ob_get_clean();
        imagedestroy($img);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function commonsApiPayload(): array
    {
        return [
            'query' => [
                'pages' => [
                    '12345' => [
                        'title'     => 'File:Rock_Recovery_Test_1976.png',
                        'imageinfo' => [[
                            'url'         => $this->wikimediaDirectUrl,
                            'mime'        => 'image/png',
                            'extmetadata' => [
                                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                                'LicenseUrl'       => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                                'Artist'           => ['value' => 'Fotógrafo Verificado'],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }

    private function eligibleSnapshot(string $editorialDate, string $eventDate, string $tipo = 'EFEMERIDE', string $discrepancias = 'NINGUNA'): array
    {
        $title = $tipo === 'EFEMERIDE'
            ? 'En 1976 Sex Pistols publican Anarchy in the UK'
            : 'Nueva gira europea anunciada oficialmente hoy';

        return [
            'elem' => [
                'id'                       => 'DV-TEST-E01',
                'tipo'                     => $tipo,
                'titulo'                   => $title,
                'texto'                    => str_repeat('Texto editorial verificado con información contrastada y completa para la audiencia rock. ', 2),
                'fecha_editorial'          => $editorialDate,
                'fecha_acontecimiento'     => $eventDate,
                'revision_requerida'       => false,
                'motivo_revision'          => 'NINGUNO',
                'fuente_primaria'          => 'https://officialband.example.com/press-release',
                'corroboracion'            => 'https://independentrockmag.example.com/news/confirmed',
                'afirmaciones_verificadas' => 'Fecha y anuncio confirmados por comunicado oficial y medio independiente.',
                'discrepancias'            => $discrepancias,
                'fuentes'                  => [
                    'https://officialband.example.com/press-release',
                    'https://independentrockmag.example.com/news/confirmed',
                ],
            ],
            'context' => [
                'sender_email'             => 'dark.vader.agent@gmail.com',
                'is_sender_authenticated'  => true,
                'is_local_import'          => false,
                'allow_local_auto_publish' => false,
                'source_message_id'        => 'msg-test-dv1@gmail.com',
                'timezone'                 => 'Europe/Madrid',
                'fecha_editorial'          => $editorialDate,
            ],
        ];
    }

    /**
     * 1. HTTP 429 con Retry-After: programa espera, respeta la ventana y recupera después sin descargas duplicadas.
     */
    public function test_http_429_respects_retry_after_and_recovers_without_duplicate_downloads(): void
    {
        $t0 = Carbon::create(2026, 11, 26, 12, 0, 0, 'Europe/Madrid');
        Carbon::setTestNow($t0);

        $post1 = Post::create([
            'title'          => 'En 1976 Sex Pistols publican Anarchy in the UK',
            'slug'           => 'en-1976-sex-pistols-recovery-1',
            'content'        => '<p>Contenido verificado.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Test 429',
            'categories'     => ['Hoy en el Rock'],
        ]);

        $meta1 = EmailPostSanitizedMeta::create([
            'post_id'                  => $post1->id,
            'batch_id'                 => 'DV-20261126-01',
            'element_id'               => 'DV-20261126-01-E01',
            'source_message_id'        => 'msg-429-1@gmail.com',
            'event_date'               => '1976-11-26',
            'editorial_date'           => '2026-11-26',
            'security_status'          => 'needs_review',
            'incident_reason'          => '[IMAGEN_PENDIENTE] Fallo de descarga: HTTP 429 al descargar imagen',
            'image_candidate_url'      => $this->wikimediaPageUrl,
            'image_alt'                => 'Portada verificada de Sex Pistols 1976',
            'image_recovery_status'    => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'image_recovery_attempts'  => 0,
            'forced_as_draft'          => true, // probar solo recuperación de imagen aquí
        ]);

        // Primer intento: API OK pero descarga devuelve 429 con Retry-After: 120
        Http::fake([
            'commons.wikimedia.org/w/api.php*' => Http::response($this->commonsApiPayload(), 200),
            $this->wikimediaDirectUrl          => Http::sequence()
                ->push('', 429, ['Retry-After' => '120'])
                ->push($this->validPngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $recovery = app(EditorialRecoveryService::class);
        $res1 = $recovery->reevaluatePost($post1, isManualAction: false, attemptAutoPublish: false, referenceNow: $t0);

        $this->assertSame('image_retry_scheduled', $res1['outcome']);
        $this->assertFalse($res1['image_recovered']);
        $meta1->refresh();
        $this->assertSame(1, $meta1->image_recovery_attempts);
        $this->assertSame(EditorialRecoveryService::STATUS_PENDING_RETRY, $meta1->image_recovery_status);
        $this->assertNotNull($meta1->image_recovery_next_retry_at);
        $this->assertEquals(120, $t0->diffInSeconds($meta1->image_recovery_next_retry_at->timezone('Europe/Madrid')));

        // 30 segundos después (aún dentro de Retry-After): NO debe golpear la red
        $tEarly = $t0->copy()->addSeconds(30);
        Carbon::setTestNow($tEarly);
        $resEarly = $recovery->reevaluatePost($post1, isManualAction: false, attemptAutoPublish: false, referenceNow: $tEarly);
        $this->assertSame('waiting_retry_window', $resEarly['outcome']);

        // 125 segundos después: ventana cumplida -> descarga exitosa
        $tAfter = $t0->copy()->addSeconds(125);
        Carbon::setTestNow($tAfter);
        $res2 = $recovery->reevaluatePost($post1, isManualAction: false, attemptAutoPublish: false, referenceNow: $tAfter);

        $this->assertTrue($res2['image_recovered']);
        $this->assertFalse($res2['reused_from_cache']);
        $post1->refresh();
        $this->assertNotEmpty($post1->featured_image);

        // Segunda pieza con la MISMA URL de Wikimedia: reutiliza caché verificada sin descarga duplicada
        $post2 = Post::create([
            'title'          => 'Segunda pieza misma imagen',
            'slug'           => 'segunda-pieza-misma-imagen',
            'content'        => '<p>Texto.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Test Dedup',
            'categories'     => ['Hoy en el Rock'],
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $post2->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E02',
            'source_message_id'       => 'msg-429-2@gmail.com',
            'event_date'              => '1976-11-26',
            'editorial_date'          => '2026-11-26',
            'security_status'         => 'needs_review',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'image_recovery_attempts' => 0,
            'forced_as_draft'         => true,
        ]);

        $resDedup = $recovery->reevaluatePost($post2, isManualAction: false, attemptAutoPublish: false, referenceNow: $tAfter);
        $this->assertTrue($resDedup['image_recovered']);
        $this->assertTrue($resDedup['reused_from_cache']);
        $this->assertSame($post1->featured_image, $post2->fresh()->featured_image);
    }

    /**
     * 2. Imagen recuperada elimina el bloqueo técnico de imagen pero conserva cualquier requisito editorial pendiente.
     */
    public function test_recovered_image_clears_image_blocker_but_preserves_editorial_blocker(): void
    {
        $t0 = Carbon::create(2026, 11, 26, 12, 0, 0, 'Europe/Madrid');
        Carbon::setTestNow($t0);

        $post = Post::create([
            'title'          => 'En 1976 Sex Pistols publican Anarchy in the UK',
            'slug'           => 'imagen-ok-pero-bloqueo-editorial',
            'content'        => '<p>Texto.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Multi Blocker',
            'categories'     => ['Hoy en el Rock'],
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'                 => $post->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E03',
            'source_message_id'       => 'msg-multi@gmail.com',
            'event_date'              => '1976-11-26',
            'editorial_date'          => '2026-11-26',
            'security_status'         => 'needs_review',
            'incident_reason'         => '[IMAGEN_PENDIENTE] Fallo de descarga: HTTP 429 al descargar imagen | Efemeride sin documento original oficial',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_alt'               => 'Portada verificada de Sex Pistols 1976',
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'image_recovery_attempts' => 1,
            'forced_as_draft'         => false,
            'dv_policy_snapshot'      => $this->eligibleSnapshot('2026-11-26', '1976-11-26', 'EFEMERIDE', 'Discrepancia real sin resolver entre fuentes'),
        ]);

        Http::fake([
            'commons.wikimedia.org/w/api.php*' => Http::response($this->commonsApiPayload(), 200),
            $this->wikimediaDirectUrl          => Http::response($this->validPngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        $res = app(EditorialRecoveryService::class)->reevaluatePost($post, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);

        $this->assertTrue($res['image_recovered']);
        $this->assertFalse($res['published']);
        $post->refresh();
        $meta->refresh();

        $this->assertSame('draft', $post->status);
        $this->assertNotEmpty($post->featured_image);
        $this->assertSame(EditorialRecoveryService::STATUS_RECOVERED, $meta->image_recovery_status);
        $this->assertStringNotContainsString('[IMAGEN_PENDIENTE]', (string) $meta->incident_reason);
        $this->assertStringContainsString('Discrepancia real sin resolver', (string) $meta->incident_reason);
    }

    /**
     * 3. Reevaluación repetida no duplica artículos ni altera publicaciones ni ediciones manuales.
     */
    public function test_repeated_reevaluation_never_duplicates_or_overwrites_published_or_manual_edits(): void
    {
        $t0 = Carbon::create(2026, 11, 26, 12, 0, 0, 'Europe/Madrid');
        Carbon::setTestNow($t0);

        $publishedPost = Post::create([
            'title'          => 'Ya Publicada Intocable',
            'slug'           => 'ya-publicada-intocable',
            'content'        => '<p>Publicado.</p>',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => $t0->copy()->subHour(),
            'source_subject' => '[DV-1] Published',
        ]);

        $manualPost = Post::create([
            'title'          => 'Editada Manualmente',
            'slug'           => 'editada-manualmente',
            'content'        => '<p>Edición del editor jefe.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Manual',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $manualPost->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E04',
            'source_message_id'       => 'msg-manual@gmail.com',
            'security_status'         => 'needs_review',
            'manually_modified_at'    => $t0->copy()->subMinutes(10),
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
        ]);

        $recovery = app(EditorialRecoveryService::class);
        $r1 = $recovery->reevaluatePost($publishedPost, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);
        $r2 = $recovery->reevaluatePost($publishedPost, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);
        $rManual = $recovery->reevaluatePost($manualPost, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);

        $this->assertSame('already_published', $r1['outcome']);
        $this->assertSame('already_published', $r2['outcome']);
        $this->assertSame('manual_edit_preserved', $rManual['outcome']);
        $this->assertSame(2, Post::count());
        $this->assertSame('draft', $manualPost->fresh()->status);
    }

    /**
     * 4. Pieza vigente y elegible se publica por política formal; atrasada, rechazada y --as-draft se respetan.
     */
    public function test_eligible_today_publishes_while_outdated_rejected_and_forced_draft_stay_held(): void
    {
        $t0 = Carbon::create(2026, 11, 26, 12, 0, 0, 'Europe/Madrid');
        Carbon::setTestNow($t0);

        Http::fake([
            'commons.wikimedia.org/w/api.php*' => Http::response($this->commonsApiPayload(), 200),
            $this->wikimediaDirectUrl          => Http::response($this->validPngBytes, 200, ['Content-Type' => 'image/png']),
        ]);

        // A. Pieza vigente de hoy (2026-11-26) y totalmente elegible -> DEBE publicarse
        $eligible = Post::create([
            'title'          => 'En 1976 Sex Pistols publican Anarchy in the UK',
            'slug'           => 'vigente-elegible-hoy',
            'content'        => '<p>Contenido limpio verificado.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Elegible Hoy',
            'categories'     => ['Hoy en el Rock'],
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $eligible->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E10',
            'source_message_id'       => 'msg-elig@gmail.com',
            'event_date'              => '1976-11-26',
            'editorial_date'          => '2026-11-26',
            'security_status'         => 'needs_review',
            'incident_reason'         => '[IMAGEN_PENDIENTE] Fallo de descarga: HTTP 429 al descargar imagen',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_alt'               => 'Portada verificada de Sex Pistols 1976',
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'forced_as_draft'         => false,
            'dv_policy_snapshot'      => $this->eligibleSnapshot('2026-11-26', '1976-11-26', 'EFEMERIDE'),
        ]);

        // B. Efeméride atrasada de ayer (2026-11-25) -> conserva fecha histórica, NO se publica
        $outdated = Post::create([
            'title'          => 'En 1976 Sex Pistols publican Anarchy in the UK',
            'slug'           => 'efemeride-atrasada-ayer',
            'content'        => '<p>Contenido limpio verificado.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Atrasada',
            'categories'     => ['Hoy en el Rock'],
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $outdated->id,
            'batch_id'                => 'DV-20261125-01',
            'element_id'              => 'DV-20261125-01-E11',
            'source_message_id'       => 'msg-outdated@gmail.com',
            'event_date'              => '1976-11-25',
            'editorial_date'          => '2026-11-25',
            'security_status'         => 'needs_review',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_alt'               => 'Portada verificada de ayer',
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'forced_as_draft'         => false,
            'dv_policy_snapshot'      => $this->eligibleSnapshot('2026-11-25', '1976-11-25', 'EFEMERIDE'),
        ]);

        // C. Pieza forzada con --as-draft -> recupera imagen pero NO se publica
        $forcedDraft = Post::create([
            'title'          => 'En 1976 Sex Pistols publican Anarchy in the UK',
            'slug'           => 'forzada-as-draft',
            'content'        => '<p>Contenido limpio verificado.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] As Draft',
            'categories'     => ['Hoy en el Rock'],
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $forcedDraft->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E12',
            'source_message_id'       => 'msg-asdraft@gmail.com',
            'event_date'              => '1976-11-26',
            'editorial_date'          => '2026-11-26',
            'security_status'         => 'needs_review',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_alt'               => 'Portada verificada as-draft',
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
            'forced_as_draft'         => true,
            'dv_policy_snapshot'      => $this->eligibleSnapshot('2026-11-26', '1976-11-26', 'EFEMERIDE'),
        ]);

        // D. Pieza rechazada -> permanece rechazada
        $rejected = Post::create([
            'title'          => 'Rechazada Previamente',
            'slug'           => 'rechazada-previamente',
            'content'        => '<p>Texto.</p>',
            'status'         => 'rejected',
            'is_published'   => false,
            'source_subject' => '[DV-1] Rejected',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'                 => $rejected->id,
            'batch_id'                => 'DV-20261126-01',
            'element_id'              => 'DV-20261126-01-E13',
            'source_message_id'       => 'msg-rej@gmail.com',
            'security_status'         => 'rejected',
            'image_candidate_url'     => $this->wikimediaPageUrl,
            'image_recovery_status'   => EditorialRecoveryService::STATUS_PENDING_RETRY,
        ]);

        $recovery = app(EditorialRecoveryService::class);

        $rEligible = $recovery->reevaluatePost($eligible, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);
        $rOutdated = $recovery->reevaluatePost($outdated, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);
        $rForced = $recovery->reevaluatePost($forcedDraft, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);
        $rRejected = $recovery->reevaluatePost($rejected, isManualAction: false, attemptAutoPublish: true, referenceNow: $t0);

        $this->assertTrue($rEligible['published']);
        $this->assertSame('published', $eligible->fresh()->status);

        $this->assertFalse($rOutdated['published']);
        $this->assertSame('draft', $outdated->fresh()->status);
        $this->assertSame('1976-11-25', $outdated->sanitizedMeta->fresh()->event_date);

        $this->assertTrue($rForced['image_recovered']);
        $this->assertFalse($rForced['published']);
        $this->assertSame('draft', $forcedDraft->fresh()->status);

        $this->assertSame('rejected_respected', $rRejected['outcome']);
        $this->assertSame('rejected', $rejected->fresh()->status);
    }
}
