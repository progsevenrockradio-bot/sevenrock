<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\AuthorizedImageOrigin;
use App\Models\Post;
use App\Models\User;
use App\Services\EmailSafetyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualPostCloudinaryAndOriginsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email'    => 'admin-cloudinary@sevenrockradio.com',
            'is_admin' => true,
        ]);

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
            $this->admin->assignRole($role);
        }
    }

    /**
     * 1. Imagen válida del espacio propio de Cloudinary (dutx97jzy) pasa en edición manual,
     *    mientras la ingesta automática mantiene intactos sus criterios estrictos.
     */
    public function test_authorized_cloudinary_account_image_passes_in_manual_editing(): void
    {
        $validUrl = 'https://res.cloudinary.com/dutx97jzy/image/upload/v1763900000/efemeride-rock.jpg';
        $jpegSample = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        Http::fake([
            $validUrl => Http::response($jpegSample, 200, [
                'Content-Type'   => 'image/jpeg',
                'Content-Length' => '24500',
            ]),
        ]);

        $post = Post::create([
            'title'        => 'Efeméride Rock 1975',
            'slug'         => 'efemeride-rock-1975',
            'content'      => '<p>Texto editorial.</p>',
            'status'       => 'draft',
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.posts.update', $post), [
            'title'          => 'Efeméride Rock 1975',
            'slug'           => 'efemeride-rock-1975',
            'content_text'   => '<p>Texto editorial.</p>',
            'featured_image' => $validUrl,
            'image_source'   => 'Archivo propio Cloudinary',
            '_action'        => 'publish',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
        $this->assertSame($validUrl, $post->featured_image);

        // Verificar que la ingesta automática (contexto default) sigue sin abrir Cloudinary
        $autoCheck = app(EmailSafetyValidator::class)->validatePublishableCoverImage($validUrl);
        $this->assertFalse($autoCheck['is_safe']);
        $this->assertSame('unauthorized_origin', $autoCheck['reason']['code'] ?? null);
    }

    /**
     * 2. Imagen de otra cuenta de Cloudinary no autorizada es rechazada con motivo concreto
     *    y conserva los datos del formulario sin publicar el artículo.
     */
    public function test_unauthorized_cloudinary_account_is_rejected_with_structured_modal_reason(): void
    {
        $otherAccountUrl = 'https://res.cloudinary.com/otra-cuenta-ajena/image/upload/v1763900000/foto.jpg?ref=secreto123';

        $post = Post::create([
            'title'        => 'Noticia con Cloudinary ajeno',
            'slug'         => 'noticia-cloudinary-ajeno',
            'content'      => '<p>Contenido original.</p>',
            'status'       => 'draft',
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.posts.update', $post), [
            'title'          => 'Noticia con Cloudinary ajeno',
            'slug'           => 'noticia-cloudinary-ajeno',
            'content_text'   => '<p>Contenido editado.</p>',
            'featured_image' => $otherAccountUrl,
            '_action'        => 'publish',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('featured_image');
        $response->assertSessionHas('image_rejection');

        $rejection = session('image_rejection');
        $this->assertSame('unauthorized_origin', $rejection['code']);
        $this->assertSame('technical', $rejection['category']);
        $this->assertSame('Origen no autorizado', $rejection['title']);
        $this->assertStringNotContainsString('secreto123', (string) ($rejection['redacted_url'] ?? ''));

        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
    }

    /**
     * 3. URL insegura (HTTP sin cifrar o redirección a dirección interna) y contenido
     *    no válido (HTML en vez de imagen) son rechazados con su causa exacta.
     */
    public function test_insecure_url_redirect_and_invalid_content_are_rejected_with_exact_reasons(): void
    {
        $validator = app(EmailSafetyValidator::class);
        $ctx = ['context' => AuthorizedImageOrigin::SCOPE_MANUAL_EDITORIAL];

        // 3a. HTTP sin cifrar sobre cuenta autorizada
        $httpUrl = 'http://res.cloudinary.com/dutx97jzy/image/upload/v1763900000/efemeride.jpg';
        $httpResult = $validator->validatePublishableCoverImage($httpUrl, $ctx);
        $this->assertFalse($httpResult['is_safe']);
        $this->assertSame('unsafe_address_or_redirect', $httpResult['reason']['code'] ?? null);

        // 3b. Redirección insegura hacia IP interna (SSRF)
        $redirectUrl = 'https://res.cloudinary.com/dutx97jzy/image/upload/v1763900000/redir.jpg';
        $htmlUrl = 'https://res.cloudinary.com/dutx97jzy/image/upload/v1763900000/no-es-imagen.jpg';

        Http::fake([
            $redirectUrl => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
            $htmlUrl     => Http::response('<html><body>No soy imagen</body></html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $redirectResult = $validator->validatePublishableCoverImage($redirectUrl, $ctx);
        $this->assertFalse($redirectResult['is_safe']);
        $this->assertSame('unsafe_address_or_redirect', $redirectResult['reason']['code'] ?? null);

        // 3c. Contenido que no es una imagen válida
        $invalidContentResult = $validator->validatePublishableCoverImage($htmlUrl, $ctx);
        $this->assertFalse($invalidContentResult['is_safe']);
        $this->assertSame('invalid_image_content', $invalidContentResult['reason']['code'] ?? null);
    }
}
