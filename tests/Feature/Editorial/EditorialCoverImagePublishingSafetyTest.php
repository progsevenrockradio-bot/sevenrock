<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Console\Commands\PublishScheduledPosts;
use App\Models\EditorialIncident;
use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\User;
use App\Services\EmailSafetyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditorialCoverImagePublishingSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected EmailSafetyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'email'    => 'admin@sevenrockradio.com',
            'is_admin' => true,
        ]);

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
            $this->adminUser->assignRole($role);
        }

        $this->validator = app(EmailSafetyValidator::class);
    }

    /**
     * 1. Aprobación individual rechazada si falta la imagen de portada.
     */
    public function test_individual_approval_fails_when_cover_image_is_missing(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia sin imagen de portada',
            'slug'           => 'noticia-sin-imagen-de-portada',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Noticia Huérfana',
            'featured_image' => null,
            'content'        => '<p>Texto limpio sin enlaces externos.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);
        $this->assertStringContainsString('no dispone de una imagen de portada', $response->json('message'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertNull($post->published_at);
    }

    /**
     * 2. Aprobación individual rechazada si la ruta local no existe físicamente en disco.
     */
    public function test_individual_approval_fails_when_local_cover_image_does_not_exist_on_disk(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia con archivo inexistente',
            'slug'           => 'noticia-con-archivo-inexistente',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Portada Fantasma',
            'featured_image' => 'storage/posts/covers/ghost_photo_inexistente.jpg',
            'content'        => '<p>Texto de prueba.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);
        $this->assertStringContainsString('no existe físicamente en el almacenamiento local', $response->json('message'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 3. Aprobación individual rechazada cuando la URL remota no puede ser confirmada o falla.
     */
    public function test_individual_approval_fails_when_remote_cover_image_cannot_be_verified(): void
    {
        $this->actingAs($this->adminUser);

        Http::fake([
            'https://upload.wikimedia.org/*' => Http::response('Not Found', 404),
        ]);

        $post = Post::create([
            'title'          => 'Noticia con portada externa rota',
            'slug'           => 'noticia-con-portada-externa-rota',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Enlace Roto',
            'featured_image' => 'https://upload.wikimedia.org/broken_cover.jpg',
            'content'        => '<p>Texto seguro.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);
        $this->assertStringContainsString('no responde correctamente', $response->json('message'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 4. Aprobación individual exitosa cuando la portada local existe físicamente.
     */
    public function test_individual_approval_succeeds_when_valid_local_cover_exists(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia con portada válida en disco',
            'slug'           => 'noticia-con-portada-valida-en-disco',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Portada Válida',
            'featured_image' => 'assets/lucille/logo.png',
            'content'        => '<p>Texto seguro sin alertas.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
        $this->assertNotNull($post->published_at);
    }

    /**
     * 5. Aprobación individual exitosa cuando la URL remota es confirmada como válida.
     */
    public function test_individual_approval_succeeds_when_remote_cover_image_is_verified(): void
    {
        $this->actingAs($this->adminUser);

        Http::fake([
            'https://cdn.example.org/*' => Http::response("\xFF\xD8\xFF\xE0\x00\x10JFIFfake-image-binary-data", 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $post = Post::create([
            'title'          => 'Noticia con portada CDN confirmada',
            'slug'           => 'noticia-con-portada-cdn-confirmada',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Portada Remota Verificada',
            'featured_image' => 'https://cdn.example.org/band-cover.jpg',
            'content'        => '<p>Texto seguro.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $response = $this->postJson(route('admin.editorial.approve', $post));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
    }

    /**
     * 6. Programación individual rechazada si falta la imagen de portada.
     */
    public function test_individual_schedule_fails_when_cover_image_is_missing(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia para programar sin portada',
            'slug'           => 'noticia-para-programar-sin-portada',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Programar Huérfana',
            'featured_image' => null,
            'content'        => '<p>Texto seguro.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $futureDate = now()->addDays(2)->format('Y-m-d H:i:s');
        $response = $this->postJson(route('admin.editorial.schedule', $post), [
            'scheduled_at' => $futureDate,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code'    => 'safety_violation',
        ]);
        $this->assertStringContainsString('no dispone de una imagen de portada', $response->json('message'));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 7. Programación individual exitosa con portada local válida.
     */
    public function test_individual_schedule_succeeds_with_valid_cover(): void
    {
        $this->actingAs($this->adminUser);

        $post = Post::create([
            'title'          => 'Noticia programable con portada',
            'slug'           => 'noticia-programable-con-portada',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Programación Válida',
            'featured_image' => 'assets/lucille/logo.png',
            'content'        => '<p>Texto seguro.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $futureDate = now()->addDays(2);
        $response = $this->postJson(route('admin.editorial.schedule', $post), [
            'scheduled_at' => $futureDate->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $post->refresh();
        $this->assertSame('scheduled', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertNotNull($post->published_at);
    }

    /**
     * 8. Aprobación masiva aísla y bloquea posts sin portada válida, permitiendo publicar los válidos.
     */
    public function test_bulk_approval_isolates_and_blocks_posts_without_valid_cover(): void
    {
        $this->actingAs($this->adminUser);

        // Post 1: Portada válida en disco
        $p1 = Post::create([
            'title'          => 'Bulk Post Válido 1',
            'slug'           => 'bulk-post-valido-1',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Bulk Valid',
            'featured_image' => 'assets/lucille/logo.png',
            'content'        => '<p>Texto limpio.</p>',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p1->id, 'security_status' => 'passed', 'received_at' => now()]);

        // Post 2: Sin portada (null)
        $p2 = Post::create([
            'title'          => 'Bulk Post Sin Portada 2',
            'slug'           => 'bulk-post-sin-portada-2',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Bulk No Cover',
            'featured_image' => null,
            'content'        => '<p>Texto sin portada.</p>',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p2->id, 'security_status' => 'passed', 'received_at' => now()]);

        // Post 3: Portada local inexistente
        $p3 = Post::create([
            'title'          => 'Bulk Post Archivo Faltante 3',
            'slug'           => 'bulk-post-archivo-faltante-3',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Bulk Missing File',
            'featured_image' => 'storage/posts/covers/no_existe_bulk.jpg',
            'content'        => '<p>Texto con portada fantasma.</p>',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p3->id, 'security_status' => 'passed', 'received_at' => now()]);

        $response = $this->postJson(route('admin.editorial.bulk-action'), [
            'action'   => 'approve',
            'post_ids' => [$p1->id, $p2->id, $p3->id],
        ]);

        $response->assertOk();
        $results = $response->json('results');

        // Post 1 debe estar aprobado
        $approvedIds = array_column($results['approved'], 'id');
        $this->assertContains($p1->id, $approvedIds);

        // Posts 2 y 3 deben estar bloqueados con motivos claros
        $blockedIds = array_column($results['blocked'], 'id');
        $this->assertContains($p2->id, $blockedIds);
        $this->assertContains($p3->id, $blockedIds);

        // Comprobar persistencia coherente
        $this->assertSame('published', $p1->fresh()->status);
        $this->assertTrue((bool) $p1->fresh()->is_published);

        $this->assertSame('draft', $p2->fresh()->status);
        $this->assertFalse((bool) $p2->fresh()->is_published);

        $this->assertSame('draft', $p3->fresh()->status);
        $this->assertFalse((bool) $p3->fresh()->is_published);
    }

    /**
     * 9. Programación masiva aísla y bloquea posts sin portada válida.
     */
    public function test_bulk_schedule_isolates_and_blocks_posts_without_valid_cover(): void
    {
        $this->actingAs($this->adminUser);

        $p1 = Post::create([
            'title'          => 'Bulk Schedule Válido 1',
            'slug'           => 'bulk-schedule-valido-1',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Bulk Schedule 1',
            'featured_image' => 'assets/lucille/logo.png',
            'content'        => '<p>Texto limpio.</p>',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p1->id, 'security_status' => 'passed', 'received_at' => now()]);

        $p2 = Post::create([
            'title'          => 'Bulk Schedule Sin Portada 2',
            'slug'           => 'bulk-schedule-sin-portada-2',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => 'Bulk Schedule 2',
            'featured_image' => null,
            'content'        => '<p>Texto sin imagen.</p>',
        ]);
        EmailPostSanitizedMeta::create(['post_id' => $p2->id, 'security_status' => 'passed', 'received_at' => now()]);

        $futureDate = now()->addDays(3)->format('Y-m-d H:i:s');
        $response = $this->postJson(route('admin.editorial.bulk-action'), [
            'action'       => 'schedule',
            'post_ids'     => [$p1->id, $p2->id],
            'scheduled_at' => $futureDate,
        ]);

        $response->assertOk();
        $results = $response->json('results');

        $scheduledIds = array_column($results['scheduled'], 'id');
        $this->assertContains($p1->id, $scheduledIds);

        $blockedIds = array_column($results['blocked'], 'id');
        $this->assertContains($p2->id, $blockedIds);

        $this->assertSame('scheduled', $p1->fresh()->status);
        $this->assertSame('draft', $p2->fresh()->status);
    }

    /**
     * 10. El comando de posts programados bloquea la publicación si la portada desaparece y registra incidencia.
     */
    public function test_publish_scheduled_posts_command_blocks_and_halts_post_if_cover_image_disappears(): void
    {
        $scheduledPost = Post::create([
            'title'          => 'Post programado cuya portada desapareció',
            'slug'           => 'post-programado-portada-desaparecida',
            'content'        => '<p>Contenido programado originalmente apto.</p>',
            'status'         => 'scheduled',
            'is_published'   => false,
            'published_at'   => now()->subMinute(),
            'source_subject' => '[DV-1] Portada Borrada',
            'featured_image' => 'storage/posts/covers/foto_que_fue_borrada.jpg',
        ]);

        $meta = EmailPostSanitizedMeta::create([
            'post_id'           => $scheduledPost->id,
            'source_message_id' => 'msg-sched-cover-deleted@test.com',
            'security_status'   => 'passed',
            'received_at'       => now(),
        ]);

        $exitCode = Artisan::call('posts:publish-scheduled');
        $this->assertSame(0, $exitCode);

        $scheduledPost->refresh();
        $this->assertFalse((bool) $scheduledPost->is_published, 'El post con portada desaparecida NO debe publicarse.');
        $this->assertSame('draft', $scheduledPost->status, 'El estado debe haber sido degradado a draft de seguridad.');

        $meta->refresh();
        $this->assertSame('needs_review', $meta->security_status);
        $this->assertStringContainsString('no existe físicamente en el almacenamiento local', (string) $meta->incident_reason);

        // Se registra el incidente para el panel administrativo
        $incident = EditorialIncident::where('post_id', $scheduledPost->id)->first();
        $this->assertNotNull($incident, 'Debe crearse un EditorialIncident de aviso administrativo.');
        $this->assertSame('missing_cover_image', $incident->incident_type);
        $this->assertSame('halted_scheduled_post', $incident->action_taken);
        $this->assertSame('pending_review', $incident->resolution_status);

        // Si el comando se vuelve a ejecutar, no debe republicarse ni entrar en bucles
        Artisan::call('posts:publish-scheduled');
        $scheduledPost->refresh();
        $this->assertSame('draft', $scheduledPost->status);
        $this->assertFalse((bool) $scheduledPost->is_published);
    }

    /**
     * 11. El comando de posts programados publica normalmente cuando la portada es utilizable.
     */
    public function test_publish_scheduled_posts_command_publishes_normally_when_cover_is_valid(): void
    {
        $scheduledPost = Post::create([
            'title'          => 'Post programado con portada válida listo para salir',
            'slug'           => 'post-programado-portada-valida-listo',
            'content'        => '<p>Contenido programado listo.</p>',
            'status'         => 'scheduled',
            'is_published'   => false,
            'published_at'   => now()->subMinute(),
            'source_subject' => '[DV-1] Programado Listo',
            'featured_image' => 'assets/lucille/logo.png',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'           => $scheduledPost->id,
            'source_message_id' => 'msg-sched-ok@test.com',
            'security_status'   => 'passed',
            'received_at'       => now(),
        ]);

        $exitCode = Artisan::call('posts:publish-scheduled');
        $this->assertSame(0, $exitCode);

        $scheduledPost->refresh();
        $this->assertTrue((bool) $scheduledPost->is_published, 'El post con portada válida DEBE publicarse al vencer su hora.');
        $this->assertSame('published', $scheduledPost->status);
    }

    /**
     * 12. Rechazo de portada remota si el dominio no pertenece a la allowlist autorizada.
     */
    public function test_remote_cover_rejected_if_domain_not_in_allowlist(): void
    {
        $this->actingAs($this->adminUser);

        Http::fake([
            'https://unauthorized-domain.com/*' => Http::response('fake', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $post = Post::create([
            'title'          => 'Noticia con dominio no autorizado',
            'slug'           => 'noticia-dominio-no-autorizado',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Dominio Inseguro',
            'featured_image' => 'https://unauthorized-domain.com/photo.jpg',
            'content'        => '<p>Texto seguro.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $res = $this->validator->validatePublishableCoverImage($post);
        $this->assertFalse($res['is_safe']);
        $this->assertStringContainsString('no pertenece a los orígenes autorizados', (string) $res['violation']);

        $response = $this->postJson(route('admin.editorial.approve', $post));
        $response->assertStatus(422);
        $this->assertStringContainsString('no pertenece a los orígenes autorizados', $response->json('message'));
    }

    /**
     * 13. Rechazo de portada remota si redirige hacia un dominio no autorizado o destino privado.
     */
    public function test_remote_cover_rejected_if_redirects_to_unauthorized_domain_or_private(): void
    {
        $this->actingAs($this->adminUser);

        // Caso 1: Redirección hacia dominio externo no autorizado
        Http::fake([
            'https://upload.wikimedia.org/redirect-unauthorized.jpg' => Http::response('', 302, [
                'Location' => 'https://evil-unauthorized-server.com/malicious.jpg',
            ]),
            'https://evil-unauthorized-server.com/malicious.jpg' => Http::response('fake', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $post1 = Post::create([
            'title'          => 'Redirección no autorizada',
            'slug'           => 'redireccion-no-autorizada',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Redirección Maliciosa',
            'featured_image' => 'https://upload.wikimedia.org/redirect-unauthorized.jpg',
            'content'        => '<p>Contenido.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post1->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $res1 = $this->validator->validatePublishableCoverImage($post1);
        $this->assertFalse($res1['is_safe']);
        $this->assertStringContainsString('no pertenece a los orígenes autorizados', (string) $res1['violation']);

        // Caso 2: Redirección hacia un host privado/interno
        Http::fake([
            'https://upload.wikimedia.org/redirect-internal.jpg' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/private-metadata.jpg',
            ]),
        ]);

        $post2 = Post::create([
            'title'          => 'Redirección a host privado',
            'slug'           => 'redireccion-host-privado',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Redirección Interna',
            'featured_image' => 'https://upload.wikimedia.org/redirect-internal.jpg',
            'content'        => '<p>Contenido.</p>',
        ]);

        EmailPostSanitizedMeta::create([
            'post_id'         => $post2->id,
            'security_status' => 'sanitized',
            'received_at'     => now(),
        ]);

        $res2 = $this->validator->validatePublishableCoverImage($post2);
        $this->assertFalse($res2['is_safe']);
        $this->assertStringContainsString('apunta a una dirección interna o privada no permitida', (string) $res2['violation']);
    }

    /**
     * 14. Rechazo de portada remota para destinos privados, loopback y puertos inesperados.
     */
    public function test_remote_cover_rejected_for_private_or_loopback_destinations_and_unexpected_ports(): void
    {
        // IP loopback
        $resLoopback = $this->validator->validatePublishableCoverImage('http://127.0.0.1/internal.jpg');
        $this->assertFalse($resLoopback['is_safe']);
        $this->assertStringContainsString('dirección interna', (string) $resLoopback['violation']);

        $resSecLoopback = $this->validator->validateRemoteImageUrlSecurity('http://127.0.0.1/internal.jpg');
        $this->assertFalse($resSecLoopback['is_safe']);
        $this->assertStringContainsString('apunta a una dirección interna o privada no permitida', (string) $resSecLoopback['violation']);

        // Cloud metadata (169.254.169.254)
        $resMetadata = $this->validator->validatePublishableCoverImage('http://169.254.169.254/latest/meta-data/photo.jpg');
        $this->assertFalse($resMetadata['is_safe']);
        $this->assertStringContainsString('dirección interna', (string) $resMetadata['violation']);

        $resSecMetadata = $this->validator->validateRemoteImageUrlSecurity('http://169.254.169.254/latest/meta-data/photo.jpg');
        $this->assertFalse($resSecMetadata['is_safe']);
        $this->assertStringContainsString('apunta a una dirección interna o privada no permitida', (string) $resSecMetadata['violation']);

        // Puerto no estándar (8080 en lugar de 80 o 443)
        $resPort = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org:8080/photo.jpg');
        $this->assertFalse($resPort['is_safe']);
        $this->assertStringContainsString('Puerto de red no permitido', (string) $resPort['violation']);
    }

    /**
     * 15. Rechazo de portada remota si el Content-Type es falso o application/octet-stream sin magic bytes.
     */
    public function test_remote_cover_rejected_for_fake_content_type_or_unverifiable_octet_stream(): void
    {
        // Caso A: Content-Type no de imagen (text/html)
        Http::fake([
            'https://upload.wikimedia.org/fake-mime.jpg' => Http::response('<html><body>Not an image</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $resMime = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/fake-mime.jpg');
        $this->assertFalse($resMime['is_safe']);
        $this->assertStringContainsString('no devuelve un contenido de imagen válido', (string) $resMime['violation']);

        // Caso B: application/octet-stream pero sin magic bytes reconocidos (texto plano)
        Http::fake([
            'https://upload.wikimedia.org/fake-blob.jpg' => Http::response('PLAIN TEXT CORRUPTED DATA WITHOUT IMAGE HEADER', 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $resOctet = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/fake-blob.jpg');
        $this->assertFalse($resOctet['is_safe']);
        $this->assertStringContainsString('no presenta una estructura de imagen válida', (string) $resOctet['violation']);
    }

    /**
     * 16. Aceptación de portada remota con dominio autorizado e imagen válida o con octet-stream verificado por magic bytes.
     */
    public function test_remote_cover_accepted_for_authorized_domain_with_valid_image_or_verified_octet_stream(): void
    {
        // Caso A: Dominio autorizado con Content-Type image/jpeg
        Http::fake([
            'https://upload.wikimedia.org/valid-rock-photo.jpg' => Http::response("\xFF\xD8\xFF\xE0\x00\x10JFIF", 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $resValid = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/valid-rock-photo.jpg');
        $this->assertTrue($resValid['is_safe']);
        $this->assertNull($resValid['violation']);
        $this->assertSame('https://upload.wikimedia.org/valid-rock-photo.jpg', $resValid['normalized_path']);

        // Caso B: Dominio autorizado con application/octet-stream pero magic bytes reales de JPEG
        $jpegBinary = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat('A', 500);
        Http::fake([
            'https://upload.wikimedia.org/blob-image-with-magic-bytes.jpg' => Http::response($jpegBinary, 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $resBlob = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/blob-image-with-magic-bytes.jpg');
        $this->assertTrue($resBlob['is_safe']);
        $this->assertNull($resBlob['violation']);
        $this->assertSame('https://upload.wikimedia.org/blob-image-with-magic-bytes.jpg', $resBlob['normalized_path']);
    }

    /**
     * 17. La lectura se limita estrictamente a 1.024 bytes cuando el servidor ignora la cabecera Range.
     */
    public function test_remote_cover_response_strictly_limited_to_1024_bytes_when_server_ignores_range_header(): void
    {
        // Caso A: Servidor que ignora Range y devuelve 200 OK con payload de 100.000 bytes con cabecera JPEG válida
        $largePayload = "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat('Z', 99990); // Total exacto: 100.000 bytes
        $this->assertSame(100000, strlen($largePayload));

        Http::fake([
            'https://upload.wikimedia.org/huge-image.jpg' => function (\Illuminate\Http\Client\Request $request) use ($largePayload) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, [
                        'Content-Type'   => 'application/octet-stream',
                        'Content-Length' => '100000',
                    ]);
                }

                // En GET, el servidor ignora Range y devuelve el payload masivo completo con HTTP 200 OK
                return Http::response($largePayload, 200, [
                    'Content-Type'   => 'application/octet-stream',
                    'Content-Length' => '100000',
                ]);
            },
        ]);

        // 1. Comprobación directa sobre la función acotada de lectura
        $dummyHead = Http::head('https://upload.wikimedia.org/huge-image.jpg');
        $sampleBytes = $this->validator->readLimitedSampleBytes($dummyHead, 'https://upload.wikimedia.org/huge-image.jpg', 1024);

        $this->assertSame(1024, strlen($sampleBytes), 'La muestra leída debe estar estrictamente acotada a 1.024 bytes.');
        $this->assertTrue(str_starts_with($sampleBytes, "\xFF\xD8\xFF\xE0\x00\x10JFIF"));

        // 2. Comprobación a través del flujo completo de validación de portada
        $result = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/huge-image.jpg');
        $this->assertTrue($result['is_safe']);
        $this->assertNull($result['violation']);
        $this->assertSame('https://upload.wikimedia.org/huge-image.jpg', $result['normalized_path']);

        // Caso B: Servidor que ignora Range y devuelve 100.000 bytes de texto plano corrupto
        Http::fake([
            'https://upload.wikimedia.org/huge-corrupted.jpg' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, [
                        'Content-Type'   => 'application/octet-stream',
                        'Content-Length' => '100000',
                    ]);
                }
                return Http::response(str_repeat('TEXT_NOT_IMAGE_', 6250), 200, [
                    'Content-Type'   => 'application/octet-stream',
                    'Content-Length' => '100000',
                ]);
            },
        ]);

        $resultCorrupted = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/huge-corrupted.jpg');
        $this->assertFalse($resultCorrupted['is_safe']);
        $this->assertStringContainsString('no presenta una estructura de imagen válida', (string) $resultCorrupted['violation']);
    }

    /**
     * 18. Rechazo de portada remota cuando Content-Type es image/jpeg pero los bytes no son de imagen.
     */
    public function test_remote_cover_rejected_when_content_type_is_image_but_magic_bytes_are_invalid(): void
    {
        // Un servidor que responde con Content-Type: image/jpeg tanto en HEAD como en GET, pero el cuerpo es texto HTML
        Http::fake([
            'https://upload.wikimedia.org/fake-header-only.jpg' => function (\Illuminate\Http\Client\Request $request) {
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200, [
                        'Content-Type' => 'image/jpeg',
                    ]);
                }
                return Http::response('<!DOCTYPE html><html><body>Fake image masquerading as JPEG</body></html>', 200, [
                    'Content-Type' => 'image/jpeg',
                ]);
            },
        ]);

        $res = $this->validator->validatePublishableCoverImage('https://upload.wikimedia.org/fake-header-only.jpg');
        $this->assertFalse($res['is_safe']);
        $this->assertStringContainsString('no presenta una estructura de imagen válida', (string) $res['violation']);
    }

    /**
     * 19. Verificación con servidor HTTP real local: confirma que la lectura se corta a 1.024 bytes aunque ignore Range.
     */
    public function test_real_local_http_server_ignoring_range_is_strictly_limited_to_1024_bytes(): void
    {
        // 1. Obtener un puerto efímero libre
        $serverSock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($serverSock, "No se pudo abrir socket local: {$errstr}");
        $sockName = stream_socket_get_name($serverSock, false);
        $port = (int) parse_url("tcp://{$sockName}", PHP_URL_PORT);
        fclose($serverSock);

        // 2. Crear script router para el servidor built-in
        $routerFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sr_test_router_' . uniqid() . '.php';
        file_put_contents($routerFile, <<<'PHP'
<?php
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('HTTP/1.1 200 OK');
    header('Content-Type: application/octet-stream');
    header('Content-Length: 100000');
    exit;
}
header('HTTP/1.1 200 OK');
header('Content-Type: application/octet-stream');
header('Content-Length: 100000');
echo "\xFF\xD8\xFF\xE0\x00\x10JFIF" . str_repeat('Z', 99990);
PHP
        );

        $phpBinary = PHP_BINARY;
        $proc = proc_open(
            "\"{$phpBinary}\" -S 127.0.0.1:{$port} \"{$routerFile}\"",
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($proc);

        // Esperar a que el servidor built-in esté listo para recibir peticiones
        usleep(300000); // 300ms

        try {
            // Asegurar que las llamadas de red reales no sean interceptadas por mocks previos
            Http::swap(app(\Illuminate\Http\Client\Factory::class));

            $url = "http://127.0.0.1:{$port}/test-large.jpg";

            // Simular respuesta previa HEAD
            $headResponse = Http::timeout(3)->withoutRedirecting()->head($url);
            $this->assertSame(200, $headResponse->status());

            // Ejecutar lectura acotada de muestra
            $sample = $this->validator->readLimitedSampleBytes($headResponse, $url, 1024);

            $this->assertSame(1024, strlen($sample), 'La lectura de red debe cortarse estrictamente a 1.024 bytes.');
            $this->assertTrue(str_starts_with($sample, "\xFF\xD8\xFF\xE0\x00\x10JFIF"));
        } finally {
            proc_terminate($proc);
            proc_close($proc);
            @unlink($routerFile);
        }
    }
}

