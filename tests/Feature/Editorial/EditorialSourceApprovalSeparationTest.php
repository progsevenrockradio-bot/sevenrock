<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\User;
use App\Services\EmailSafetyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guardar la aprobación de URLs concretas (approve-urls) como acción separada de publicar.
 * Datos de prueba en SQLite en memoria.
 */
class EditorialSourceApprovalSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const WIKI = 'https://en.wikipedia.org/wiki/GRRR!';
    private const GUARDIAN = 'https://www.theguardian.com/music/2012/sep/05/rolling-stones-new-music-grrr';
    private const COVER = 'sep-2019-cover.jpg';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'admin@sevenrockradio.com', 'is_admin' => true]);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));
        $this->actingAs($this->admin);

        $dir = storage_path('app/public/posts/covers');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . DIRECTORY_SEPARATOR . self::COVER, 'fake-cover-bytes');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/public/posts/covers/' . self::COVER));
        parent::tearDown();
    }

    private function makePost(string $title, string $extra = ''): Post
    {
        $post = Post::create([
            'title'          => $title,
            'slug'           => str($title)->slug() . '-' . uniqid(),
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] ' . $title,
            'featured_image' => 'storage/posts/covers/' . self::COVER,
            'content'        => '<p>GRRR!</p><a href="' . self::WIKI . '">Wikipedia</a><a href="' . self::GUARDIAN . '">Guardian</a>' . $extra,
        ]);
        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'sanitized',
            'sources'         => json_encode([self::WIKI, self::GUARDIAN]),
            'approved_urls'   => null,
        ]);

        return $post;
    }

    private function meta(Post $post): EmailPostSanitizedMeta
    {
        return EmailPostSanitizedMeta::where('post_id', $post->id)->firstOrFail();
    }

    /** 1 y 2. Guardar URLs concretas persiste approved_urls/at/by sin publicar; siguen tras recargar. */
    public function test_saving_approval_persists_without_publishing(): void
    {
        $post = $this->makePost('Rolling Stones S1');

        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => [self::WIKI, self::GUARDIAN]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = Post::find($post->id);
        $this->assertSame('draft', $fresh->status);
        $this->assertFalse((bool) $fresh->is_published);

        $meta = $this->meta($post);
        $this->assertEqualsCanonicalizing([self::WIKI, self::GUARDIAN], $meta->approved_urls);
        $this->assertNotNull($meta->approved_at);
        $this->assertSame($this->admin->id, $meta->approved_by);

        // Recarga: sigue aprobado y ya no hay fuentes pendientes
        $this->assertEqualsCanonicalizing([self::WIKI, self::GUARDIAN], $meta->fresh()->approved_urls);
        $this->assertSame([], app(EmailSafetyValidator::class)->getPendingUnapprovedSources(Post::find($post->id)));
    }

    /** 3. Una URL distinta no aprobada sigue bloqueando la publicación. */
    public function test_unselected_url_still_blocks_publication(): void
    {
        $post = $this->makePost('Rolling Stones S3');

        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => [self::WIKI]])->assertSessionHas('success');

        $this->assertSame([self::GUARDIAN], app(EmailSafetyValidator::class)->getPendingUnapprovedSources(Post::find($post->id)));
        $r = $this->postJson(route('admin.editorial.approve', Post::find($post->id)));
        $r->assertStatus(422);
        $this->assertStringContainsString('theguardian.com', $r->json('message'));
        $this->assertSame('draft', Post::find($post->id)->status);
    }

    /** 4. El mismo enlace en otro post no hereda la aprobación. */
    public function test_other_post_does_not_inherit_approval(): void
    {
        $a = $this->makePost('Rolling Stones S4A');
        $b = $this->makePost('Rolling Stones S4B');

        $this->post(route('admin.editorial.approve-urls', $a), ['urls' => [self::WIKI, self::GUARDIAN]]);

        $this->assertNull($this->meta($b)->approved_urls);
        $this->assertCount(2, app(EmailSafetyValidator::class)->getPendingUnapprovedSources(Post::find($b->id)));
        $this->postJson(route('admin.editorial.approve', Post::find($b->id)))->assertStatus(422);
    }

    /** 5. Un fallo posterior (otra validación o portada) no borra la aprobación ya guardada. */
    public function test_later_validation_failure_keeps_saved_approval(): void
    {
        $post = $this->makePost('Rolling Stones S5', '<p>contraseña: abc123</p>');

        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => [self::WIKI, self::GUARDIAN]]);
        $this->assertCount(2, $this->meta($post)->approved_urls);

        // Otra validación falla (credencial expuesta en el texto)
        $this->postJson(route('admin.editorial.approve', Post::find($post->id)))->assertStatus(422);
        $this->assertCount(2, $this->meta($post)->approved_urls);

        // Portada inexistente: también falla, la aprobación sigue
        $post = Post::find($post->id);
        $post->featured_image = 'storage/posts/covers/no-existe.jpg';
        $post->save();
        $this->postJson(route('admin.editorial.approve', $post))->assertStatus(422);
        $this->assertCount(2, $this->meta($post)->approved_urls);
        $this->assertSame('draft', Post::find($post->id)->status);
    }

    /** 6. La acción masiva sigue sin aprobar fuentes ni publicar noticias bloqueadas. */
    public function test_bulk_action_does_not_save_approvals_or_publish(): void
    {
        $post = $this->makePost('Rolling Stones S6');

        $this->post(route('admin.editorial.bulk-action'), ['action' => 'approve', 'post_ids' => [$post->id]]);

        $this->assertNull($this->meta($post)->approved_urls);
        $this->assertSame('draft', Post::find($post->id)->status);
        $this->assertFalse((bool) Post::find($post->id)->is_published);
    }

    /** Validación: sin URLs → error claro; URLs internas → no se guardan; sin admin → no guarda. */
    public function test_empty_private_and_unauthorized_requests_do_not_store_approval(): void
    {
        $post = $this->makePost('Rolling Stones S7');

        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => []])
            ->assertSessionHas('error', 'Debe indicar al menos una URL concreta para aprobar.');
        $this->assertNull($this->meta($post)->approved_urls);

        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => ['http://127.0.0.1/admin', 'http://169.254.169.254/latest']])
            ->assertSessionHas('error');
        $this->assertNull($this->meta($post)->approved_urls);

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->post(route('admin.editorial.approve-urls', $post), ['urls' => [self::WIKI]]);
        $this->assertNull($this->meta($post)->approved_urls);
    }

    /** El cajón expone el botón independiente hacia approve-urls con CSRF. */
    public function test_panel_renders_separate_save_approval_button(): void
    {
        $this->makePost('Rolling Stones S8');

        $html = $this->get(route('admin.editorial.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Guardar aprobación de estas fuentes', $html);
        $this->assertStringContainsString("'/approve-urls'", $html);
        $this->assertStringContainsString('Aprobar estas fuentes y publicar', $html);
    }
}
