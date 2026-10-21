<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EditorialIncident;
use App\Models\EmailPostSanitizedMeta;
use App\Models\EmailSenderSource;
use App\Models\Post;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\EmailMediaInventoryExtractor;
use App\Services\EmailSafetyValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase6EditorialInboxTest extends TestCase
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
        ]);

        ThemeSetting::create([
            'email_auto_publish'     => false,
            'email_daily_news_limit' => 10,
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->regularUser = User::factory()->create([
            'is_admin' => false,
        ]);

        Mail::fake();
    }

    /**
     * 1. Acceso denegado a usuarios no autorizados (403 Forbidden).
     */
    public function test_unauthorized_users_receive_403_forbidden(): void
    {
        // Invitado no autenticado -> 302 a login o 401
        $this->get(route('admin.editorial.index'))->assertRedirect();

        // Usuario registrado no administrador -> 403
        $this->actingAs($this->regularUser)
            ->get(route('admin.editorial.index'))
            ->assertForbidden();

        // Admin -> 200 OK
        $this->actingAs($this->admin)
            ->get(route('admin.editorial.index'))
            ->assertOk();
    }

    /**
     * 2. La Bandeja Editorial solo muestra publicaciones originadas por correo.
     */
    public function test_inbox_lists_exclusively_posts_originating_from_email_pipeline(): void
    {
        // Post Manual (sin source_subject ni sanitizedMeta)
        $manualPost = Post::create([
            'title'        => 'Post Manual de Redactor',
            'slug'         => 'post-manual-de-redactor',
            'content'      => '<p>Texto redactado directamente en la web.</p>',
            'status'       => 'draft',
            'is_published' => false,
        ]);

        // Post de Correo (con source_subject y sanitizedMeta)
        $emailPost = Post::create([
            'title'          => 'Comunicado de Judas Priest',
            'slug'           => 'comunicado-de-judas-priest',
            'content'        => '<p>Gira europea confirmada.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Press Release: Judas Priest European Tour 2026',
            'author_email'   => 'press@centurymedia.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $emailPost->id,
            'source_message_id' => 'msg-jp-1234@centurymedia.com',
            'sender_email'      => 'press@centurymedia.com',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.editorial.index'));
        $response->assertOk();

        // El comunicado de correo aparece
        $response->assertSee('Comunicado de Judas Priest');
        // El post manual NO debe listarse en la bandeja editorial
        $response->assertDontSee('Post Manual de Redactor');
    }

    /**
     * 3. Imposibilidad absoluta de aprobar Posts manuales mediante la Bandeja Editorial.
     */
    public function test_manual_posts_cannot_be_approved_through_editorial_inbox(): void
    {
        $manualPost = Post::create([
            'title'        => 'Post Manual Ajeno',
            'slug'         => 'post-manual-ajeno',
            'content'      => '<p>Manual.</p>',
            'status'       => 'draft',
            'is_published' => false,
        ]);

        $this->assertFalse($manualPost->isFromEmailPipeline());

        // Intentar aprobar a través del endpoint editorial
        $response = $this->actingAs($this->admin)
            ->post(route('admin.editorial.approve', $manualPost));

        $response->assertForbidden();

        $manualPost->refresh();
        $this->assertSame('draft', $manualPost->status);
        $this->assertFalse((bool) $manualPost->is_published);
    }

    /**
     * 4. Vista Rápida Segura: El servidor no envía credenciales, contraseñas ni tokens privados.
     */
    public function test_quick_view_returns_structured_data_without_leaking_credentials_or_private_tokens(): void
    {
        $post = Post::create([
            'title'          => 'Single Exclusivo con Contraseña',
            'slug'           => 'single-exclusivo-con-contrasena',
            'content'        => '<p>Escucha el adelanto exclusivo.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Exclusiva Prensa: Password=SecretRock2026',
            'author_email'   => 'promo@label.com',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-leak-test@label.com',
            'sender_email'      => 'promo@label.com',
            'received_at'       => now(),
            'editorial_notes'   => 'Nota para editores: download password: SuperSecretPassword! No compartir.',
            'incident_reason'   => 'Token detectado token=abc123456xyz en URL',
            'redacted_links'    => [
                [
                    'platform' => 'haulix',
                    'category' => 'restricted_private',
                    'url'      => 'https://haulix.com/stream?token=SuperPrivateStreamToken999',
                ]
            ],
            'security_status'   => 'needs_review',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.editorial.quick-view', $post));

        $response->assertOk();
        $json = $response->json();

        $this->assertTrue($json['success']);
        $metaData = $json['data']['meta'];

        // Verificar que la contraseña fue enmascarada
        $this->assertStringNotContainsString('SuperSecretPassword!', $metaData['editorial_notes']);
        $this->assertStringContainsString('[PROTECTED_CREDENTIAL]', $metaData['editorial_notes']);

        // Verificar que el token en incident_reason fue enmascarado
        $this->assertStringNotContainsString('abc123456xyz', $metaData['incident_reason']);
        $this->assertStringContainsString('[PROTECTED_CREDENTIAL]', $metaData['incident_reason']);

        // Verificar que la URL de stream con token privado NO se envía en crudo
        $this->assertStringNotContainsString('SuperPrivateStreamToken999', json_encode($metaData['redacted_materials']));
    }

    /**
     * 5. Contadores multimedia reales y diferenciación de adjuntos vs enlazados.
     */
    public function test_quick_view_returns_differentiated_media_inventory_from_real_data(): void
    {
        $post = Post::create([
            'title'          => 'Lanzamiento con Multimedia Mixto',
            'slug'           => 'lanzamiento-con-multimedia-mixto',
            'content'        => '<p>Mira el vídeo en https://youtube.com/watch?v=dQw4w9WgXcQ y escucha en https://open.spotify.com/album/37i9dQZF1DXcBWIGoYBM5M <img src="https://example.com/banner.jpg" alt="Foto"></p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Nuevo Álbum + Videoclip',
            'author_email'   => 'info@band.com',
        ]);

        // Guardar inventario real con 1 MP3 adjunto conocido y 1 doc
        $mediaInventory = [
            'attached_photos_count' => 2,
            'linked_photos_count'   => 1,
            'attached_videos_count' => 0,
            'linked_videos_count'   => 1,
            'attached_mp3s_count'   => 1,
            'linked_music_count'    => 1,
            'attached_docs_count'   => 1,
            'linked_docs_count'     => 0,
            'public_audio_links'    => [['platform' => 'spotify', 'url' => 'https://open.spotify.com/album/37i9dQZF1DXcBWIGoYBM5M', 'label' => 'Música en Spotify']],
            'public_video_links'    => [['platform' => 'youtube', 'url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ', 'label' => 'Vídeo en Youtube']],
            'has_reserved_materials'=> true,
            'inventory_status'      => 'complete',
        ];

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-media-1@band.com',
            'sender_email'      => 'info@band.com',
            'received_at'       => now(),
            'media_inventory'   => $mediaInventory,
            'security_status'   => 'passed',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.editorial.quick-view', $post));

        $response->assertOk();
        $inv = $response->json('data.media_inventory');

        $this->assertSame(2, $inv['attached_photos_count']);
        $this->assertSame(1, $inv['linked_photos_count']);
        $this->assertSame(1, $inv['attached_mp3s_count']);
        $this->assertSame(1, $inv['linked_music_count']);
        $this->assertSame(1, $inv['attached_docs_count']);
        $this->assertTrue($inv['has_reserved_materials']);
    }

    /**
     * 6. Aprobación individual exitosa: Valida seguridad, publica y actualiza published_at.
     */
    public function test_individual_approval_publishes_safe_post_and_updates_status(): void
    {
        $post = Post::create([
            'title'          => 'Angelus Apatrida: Nuevo Single',
            'slug'           => 'angelus-apatrida-nuevo-single',
            'content'        => '<p>Adelanto del nuevo trabajo discográfico sin infracciones.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Angelus Apatrida News',
            'author_email'   => 'promo@centurymedia.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-aa-single@centurymedia.com',
            'sender_email'      => 'promo@centurymedia.com',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.editorial.approve', $post));

        $response->assertRedirect();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->published_at->isToday());
    }

    /**
     * 7. Bloqueo de aprobación individual si hay embargo activo.
     */
    public function test_individual_approval_is_blocked_if_post_has_active_embargo(): void
    {
        $post = Post::create([
            'title'          => 'Crisix: Portada Embargada',
            'slug'           => 'crisix-portada-embargada',
            'content'        => '<p>Información bajo estricto embargo.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'CONFIDENTIAL EMBARGO: Crisix',
            'author_email'   => 'press@crisix.com',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-embargo-1@crisix.com',
            'sender_email'      => 'press@crisix.com',
            'received_at'       => now(),
            'is_embargo_active' => true,
            'embargo_until'     => now()->addDays(2),
            'security_status'   => 'held_embargo',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $this->assertSame('active_embargo', $response->json('code'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 8. Bloqueo de aprobación individual si hay incidencias editoriales pendientes.
     */
    public function test_individual_approval_is_blocked_if_post_has_pending_incidents(): void
    {
        $post = Post::create([
            'title'          => 'Ghost: Gira Conciertos',
            'slug'           => 'ghost-gira-conciertos',
            'content'        => '<p>Fechas del tour europeo.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Ghost Tour Dates',
            'author_email'   => 'press@loma-vista.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-ghost-1@loma.com',
            'sender_email'      => 'press@loma-vista.com',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        // Registrar incidencia pendiente (ej. cambio de recinto)
        EditorialIncident::create([
            'post_id'              => $post->id,
            'source_message_id'    => 'msg-ghost-corr@loma.com',
            'original_message_id'  => 'msg-ghost-1@loma.com',
            'incident_type'        => 'rectification_data',
            'priority'             => 'high',
            'post_previous_status' => 'draft',
            'action_taken'         => 'linked_to_draft',
            'correlation_score'    => 85,
            'resolution_status'    => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $this->assertSame('pending_incidents', $response->json('code'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 9. Bloqueo de aprobación si EmailSafetyValidator detecta contraseñas residuales.
     */
    public function test_individual_approval_is_blocked_if_safety_validator_fails(): void
    {
        $post = Post::create([
            'title'          => 'Álbum con Contraseña Filtrada en Título',
            'slug'           => 'album-con-contrasena-filtrada',
            'content'        => '<p>Contenido limpio.</p>',
            'excerpt'        => 'Descarga passcode: SecretCode999',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Promo Leak',
            'author_email'   => 'leaker@promo.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-safety-fail@promo.com',
            'sender_email'      => 'leaker@promo.com',
            'received_at'       => now(),
            'security_status'   => 'needs_review',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $this->assertSame('safety_violation', $response->json('code'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 10. Concurrencia y replay: Segundo intento de aprobación sobre post ya publicado retorna 409 Conflict.
     */
    public function test_concurrent_approvals_or_replay_returns_conflict_409(): void
    {
        $post = Post::create([
            'title'          => 'Barón Rojo: Gira Despedida',
            'slug'           => 'baron-rojo-gira-despedida',
            'content'        => '<p>Fechas de despedida confirmadas.</p>',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => now(),
            'source_subject' => 'Baron Rojo News',
            'author_email'   => 'management@baronrojo.net',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-baron-1@net.com',
            'sender_email'      => 'management@baronrojo.net',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        // Simular que otro administrador o una petición simultánea intenta aprobarlo de nuevo
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(409);
        $this->assertSame('already_published', $response->json('code'));
    }

    /**
     * 11. Programación: Exige fecha posterior al embargo.
     */
    public function test_individual_schedule_requires_datetime_after_embargo(): void
    {
        $embargoDate = now()->addDays(5);

        $post = Post::create([
            'title'          => 'Nuevo Álbum Bajo Embargo 5 Días',
            'slug'           => 'nuevo-album-bajo-embargo-5-dias',
            'content'        => '<p>Lanzamiento mundial.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Embargoed Release',
            'author_email'   => 'label@nuclearblast.de',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-nb-embargo@nb.de',
            'sender_email'      => 'label@nuclearblast.de',
            'received_at'       => now(),
            'is_embargo_active' => true,
            'embargo_until'     => $embargoDate,
            'security_status'   => 'held_embargo',
        ]);

        // Intento 1: Programar ANTES del fin del embargo (por ejemplo, a 2 días)
        $invalidDate = now()->addDays(2)->toDateTimeString();
        $response1 = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.schedule', $post), [
                'scheduled_at' => $invalidDate,
            ]);

        $response1->assertStatus(422);
        $this->assertSame('embargo_conflict', $response1->json('code'));

        // Intento 2: Programar POSTERIOR al fin del embargo (a 6 días)
        $validDate = now()->addDays(6)->toDateTimeString();
        $response2 = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.schedule', $post), [
                'scheduled_at' => $validDate,
            ]);

        $response2->assertOk();
        $this->assertTrue($response2->json('success'));

        $post->refresh();
        $this->assertSame('scheduled', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertSame($validDate, $post->published_at->toDateTimeString());
    }

    /**
     * 12. Rechazo individual: Preserva el registro con status = 'rejected' para auditoría.
     */
    public function test_individual_reject_preserves_post_record_with_traceability(): void
    {
        $post = Post::create([
            'title'          => 'Comunicado de Spam o Irrelevante',
            'slug'           => 'comunicado-de-spam',
            'content'        => '<p>Publicidad no relacionada con el rock.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Spam promo',
            'author_email'   => 'spam@unknown.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-spam-99@unknown.com',
            'sender_email'      => 'spam@unknown.com',
            'received_at'       => now(),
            'security_status'   => 'needs_review',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.editorial.reject', $post), [
                'reason' => 'Contenido publicitario ajeno a la línea editorial',
            ]);

        $response->assertRedirect();

        // El post sigue existiendo en BD (no se borró)
        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => 'rejected',
        ]);

        $post->refresh();
        $this->assertSame('rejected', $post->sanitizedMeta->security_status);
        $this->assertSame('Contenido publicitario ajeno a la línea editorial', $post->sanitizedMeta->incident_reason);
    }

    /**
     * 13. Acción Masiva Heterogénea: Aprueba las limpias y retiene las conflictivas con reporte.
     */
    public function test_bulk_action_isolates_failures_publishing_safe_items_and_retaining_embargoed_or_incident_items(): void
    {
        // Post 1: Limpio y apto
        $p1 = Post::create([
            'title'          => 'Noticia Limpia 1',
            'slug'           => 'noticia-limpia-1',
            'content'        => '<p>Texto seguro sin alertas.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Asunto 1',
            'author_email'   => 'pr1@media.com',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p1->id, 'security_status' => 'passed', 'received_at' => now()]);

        // Post 2: Con Embargo Activo
        $p2 = Post::create([
            'title'          => 'Noticia Embargada 2',
            'slug'           => 'noticia-embargada-2',
            'content'        => '<p>Texto bajo embargo.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Asunto 2',
            'author_email'   => 'pr2@media.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $p2->id,
            'security_status'   => 'held_embargo',
            'is_embargo_active' => true,
            'embargo_until'     => now()->addDays(3),
            'received_at'       => now(),
        ]);

        // Post 3: Con Incidencia Pendiente
        $p3 = Post::create([
            'title'          => 'Noticia con Incidencia 3',
            'slug'           => 'noticia-con-incidencia-3',
            'content'        => '<p>Texto con rectificación pendiente.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Asunto 3',
            'author_email'   => 'pr3@media.com',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p3->id, 'security_status' => 'needs_review', 'received_at' => now()]);
        EditorialIncident::create([
            'post_id'              => $p3->id,
            'source_message_id'    => 'msg-corr-3',
            'incident_type'        => 'rectification_data',
            'priority'             => 'high',
            'post_previous_status' => 'draft',
            'action_taken'         => 'linked_to_draft',
            'resolution_status'    => 'pending_review',
        ]);

        // Post 4: Limpio y apto
        $p4 = Post::create([
            'title'          => 'Noticia Limpia 4',
            'slug'           => 'noticia-limpia-4',
            'content'        => '<p>Texto seguro sin alertas 4.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Asunto 4',
            'author_email'   => 'pr4@media.com',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p4->id, 'security_status' => 'passed', 'received_at' => now()]);

        // Ejecutar acción masiva de aprobación sobre los 4 posts
        $response = $this->actingAs($this->admin)->postJson(route('admin.editorial.bulk-action'), [
            'action'   => 'approve',
            'post_ids' => [$p1->id, $p2->id, $p3->id, $p4->id],
        ]);

        $response->assertOk();
        $data = $response->json('results');

        // Los posts limpios (p1 y p4) deben haberse aprobado
        $approvedIds = array_column($data['approved'], 'id');
        $this->assertContains($p1->id, $approvedIds);
        $this->assertContains($p4->id, $approvedIds);

        // Los posts conflictivos (p2 y p3) deben estar en 'blocked'
        $blockedIds = array_column($data['blocked'], 'id');
        $this->assertContains($p2->id, $blockedIds);
        $this->assertContains($p3->id, $blockedIds);

        // Verificar estados en base de datos
        $p1->refresh();
        $this->assertSame('published', $p1->status);
        $this->assertTrue((bool) $p1->is_published);

        $p2->refresh();
        $this->assertSame('draft', $p2->status);
        $this->assertFalse((bool) $p2->is_published);

        $p3->refresh();
        $this->assertSame('draft', $p3->status);
        $this->assertFalse((bool) $p3->is_published);

        $p4->refresh();
        $this->assertSame('published', $p4->status);
        $this->assertTrue((bool) $p4->is_published);
    }

    /**
     * 14. Gestión de Fuentes (EmailSenderSource): Registro, actualización de estado y auditoría.
     */
    public function test_sources_management_allows_registering_and_updating_status_with_audit(): void
    {
        // 1. Crear nueva fuente vía controlador
        $createRes = $this->actingAs($this->admin)->post(route('admin.editorial.sources.store'), [
            'name'              => 'Century Media Records',
            'organization_type' => 'label',
            'verified_domains'  => 'centurymedia.com, sonymusic.com',
            'authorized_emails' => 'press@centurymedia.com',
            'status'            => 'pending',
            'notes'             => 'Sello discográfico oficial',
        ]);
        $createRes->assertRedirect(route('admin.editorial.sources.index'));

        $source = EmailSenderSource::where('name', 'Century Media Records')->first();
        $this->assertNotNull($source);
        $this->assertSame('pending', $source->status);
        $this->assertContains('centurymedia.com', $source->verified_domains);

        // 2. Autorizar fuente
        $updateRes = $this->actingAs($this->admin)->patch(route('admin.editorial.sources.update-status', $source), [
            'status' => 'authorized',
            'reason' => 'Verificación técnica completada con éxito',
        ]);
        $updateRes->assertRedirect();

        $source->refresh();
        $this->assertSame('authorized', $source->status);
        $this->assertNotNull($source->authorized_at);
        $this->assertSame($this->admin->id, $source->authorized_by);
        $this->assertFalse((bool) $source->auto_publish_allowed, 'Modo B debe permanecer estrictamente en false');
    }

    /**
     * 15. Gestión de Incidencias: Diff comparativo y resolución sin despublicar noticias activas.
     */
    public function test_incident_resolution_applies_changes_without_unpublishing_published_posts(): void
    {
        $publishedPost = Post::create([
            'title'          => 'Concierto en Sala Mon Live',
            'slug'           => 'concierto-en-sala-mon-live',
            'content'        => '<p>Entradas ya a la venta para Sala Mon.</p>',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => now()->subDays(1),
            'source_subject' => 'Concierto Anuncio',
            'author_email'   => 'promoter@live.com',
        ]);

        $incident = EditorialIncident::create([
            'post_id'              => $publishedPost->id,
            'source_message_id'    => 'msg-errata-1@live.com',
            'original_message_id'  => 'msg-orig-1@live.com',
            'incident_type'        => 'rectification_data',
            'priority'             => 'urgent',
            'post_previous_status' => 'published',
            'action_taken'         => 'flagged_published_post',
            'correlation_score'    => 95,
            'detected_diff'        => [
                'venue_previous' => 'Sala Mon Live',
                'venue_new'      => 'Sala La Riviera',
                'reason'         => 'Cambio de recinto por ampliación de aforo',
            ],
            'resolution_status'    => 'pending_review',
        ]);

        // Consultar Diff JSON
        $diffRes = $this->actingAs($this->admin)->getJson(route('admin.editorial.incidents.diff', $incident));
        $diffRes->assertOk();
        $this->assertSame(95, $diffRes->json('data.correlation_score'));
        $this->assertSame('Sala La Riviera', $diffRes->json('data.detected_diff.venue_new'));

        // Resolver la incidencia aplicando fe de erratas
        $resolveRes = $this->actingAs($this->admin)->post(route('admin.editorial.incidents.resolve', $incident), [
            'resolution_action' => 'apply_changes',
            'resolution_notes'  => 'El concierto se traslada a La Riviera por venta total en 24h.',
        ]);
        $resolveRes->assertRedirect();

        $incident->refresh();
        $this->assertSame('resolved_applied', $incident->resolution_status);
        $this->assertSame($this->admin->id, $incident->resolved_by_user_id);
        $this->assertNotNull($incident->resolved_at);

        // COMPROBACIÓN CRÍTICA: La noticia publicada NUNCA debe haber sido despublicada
        $publishedPost->refresh();
        $this->assertSame('published', $publishedPost->status);
        $this->assertTrue((bool) $publishedPost->is_published);
        // Debe contener la fe de erratas al pie
        $contentJson = json_encode($publishedPost->content);
        $this->assertStringContainsString('Fe de Erratas', $contentJson);
        $this->assertStringContainsString('La Riviera', $contentJson);
    }

    /**
     * 16. Aprobación desde la Bandeja NO envía notificaciones indebidas por correo.
     */
    public function test_approval_from_inbox_never_sends_accidental_published_notifications_to_authors(): void
    {
        $post = Post::create([
            'title'          => 'Nuevo Álbum para Aprobación',
            'slug'           => 'nuevo-album-para-aprobacion',
            'content'        => '<p>Texto editorial.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Press Notification Test',
            'author_email'   => 'external_agency@promorock.es',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-notif-test@promorock.es',
            'sender_email'      => 'external_agency@promorock.es',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        $this->actingAs($this->admin)->post(route('admin.editorial.approve', $post));

        $post->refresh();
        $this->assertTrue((bool) $post->is_published);

        // Verificar que Mail::fake() no ha enviado ningún correo hacia external_agency@promorock.es
        Mail::assertNothingSent();
    }

    /**
     * 17. Dos administradores intentando aprobar el mismo Post (manejo estricto de concurrencia).
     */
    public function test_two_admins_approving_same_post_concurrently_rejects_second_with_conflict(): void
    {
        $secondAdmin = User::factory()->create(['is_admin' => true]);

        $post = Post::create([
            'title'          => 'Carrera de Aprobación Concurrente',
            'slug'           => 'carrera-de-aprobacion-concurrente',
            'content'        => '<p>Texto limpio.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Concurrent test',
            'author_email'   => 'band@race.com',
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'           => $post->id,
            'source_message_id' => 'msg-race-1@race.com',
            'sender_email'      => 'band@race.com',
            'received_at'       => now(),
            'security_status'   => 'passed',
        ]);

        // Primer administrador aprueba: Exitoso
        $res1 = $this->actingAs($this->admin)
            ->postJson(route('admin.editorial.approve', $post));
        $res1->assertOk();
        $this->assertTrue($res1->json('success'));

        // Segundo administrador intenta aprobar casi simultáneamente sobre el post ya publicado
        $res2 = $this->actingAs($secondAdmin)
            ->postJson(route('admin.editorial.approve', $post));

        // Debe retornar 409 Conflict impidiendo doble publicación o sobreescritura
        $res2->assertStatus(409);
        $this->assertSame('already_published', $res2->json('code'));
    }

    /**
     * 18. Filtros de bandeja: Búsqueda, estado editorial y retenciones de seguridad.
     */
    public function test_inbox_search_and_filters_operate_correctly(): void
    {
        // Post A: Judas Priest (draft)
        $postA = Post::create([
            'title'          => 'Judas Priest: Concierto en Madrid',
            'slug'           => 'judas-priest-concierto-en-madrid',
            'content'        => '<p>Madrid.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'JP Tour 2026',
            'author_email'   => 'press@judaspriest.com',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $postA->id, 'received_at' => now(), 'security_status' => 'passed']);

        // Post B: Iron Maiden (published)
        $postB = Post::create([
            'title'          => 'Iron Maiden: The Future Past Tour',
            'slug'           => 'iron-maiden-the-future-past-tour',
            'content'        => '<p>Maiden.</p>',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => now(),
            'source_subject' => 'Maiden 2026',
            'author_email'   => 'press@ironmaiden.com',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $postB->id, 'received_at' => now(), 'security_status' => 'passed']);

        // Búsqueda por "Judas"
        $resSearch = $this->actingAs($this->admin)
            ->get(route('admin.editorial.index', ['search' => 'Judas']));
        $resSearch->assertOk();
        $resSearch->assertSee('Judas Priest: Concierto en Madrid');
        $resSearch->assertDontSee('Iron Maiden: The Future Past Tour');

        // Filtro por status = 'published'
        $resPublished = $this->actingAs($this->admin)
            ->get(route('admin.editorial.index', ['status' => 'published']));
        $resPublished->assertOk();
        $resPublished->assertSee('Iron Maiden: The Future Past Tour');
        $resPublished->assertDontSee('Judas Priest: Concierto en Madrid');
    }

    /**
     * 19. Comprobación visual de las 3 pantallas principales con diseño Lucille y responsive.
     */
    public function test_views_render_successfully_with_responsive_lucille_components(): void
    {
        // 1. Pantalla Principal: Bandeja Editorial
        $resInbox = $this->actingAs($this->admin)->get(route('admin.editorial.index'));
        $resInbox->assertOk();
        $resInbox->assertSee('Bandeja Editorial');
        $resInbox->assertSee('Modo A Controlado');
        $resInbox->assertSee('quickViewOpen');
        $resInbox->assertSee('overflow-x-auto');

        // 2. Pantalla: Fuentes y Agencias
        $resSources = $this->actingAs($this->admin)->get(route('admin.editorial.sources.index'));
        $resSources->assertOk();
        $resSources->assertSee('Fuentes y Agencias de Prensa');
        $resSources->assertSee('Aviso de Seguridad Operativa');
        $resSources->assertSee('createModalOpen');

        // 3. Pantalla: Incidencias y Rectificaciones
        $resIncidents = $this->actingAs($this->admin)->get(route('admin.editorial.incidents.index'));
        $resIncidents->assertOk();
        $resIncidents->assertSee('Incidencias y Rectificaciones Editoriales');
        $resIncidents->assertSee('diffModalOpen');
    }
}
