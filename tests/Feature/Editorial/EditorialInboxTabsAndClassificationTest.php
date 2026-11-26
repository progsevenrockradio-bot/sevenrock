<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EmailPostSanitizedMeta;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\User;
use App\Support\EditorialClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditorialInboxTabsAndClassificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'     => 'Editor Jefe',
            'email'    => 'editor-tabs@sevenrockradio.com',
            'is_admin' => true,
        ]);

        if (class_exists(Role::class)) {
            $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
            $this->admin->assignRole($role);
        }
    }

    private function attachCategory(Post $post, string $name, string $slug): void
    {
        $tax = PostTaxonomy::firstOrCreate(
            ['type' => PostTaxonomy::TYPE_CATEGORY, 'slug' => $slug],
            ['name' => $name]
        );
        $post->taxonomies()->syncWithoutDetaching([$tax->id]);
    }

    /**
     * 1. Separación de pestañas (Por revisar / Publicadas / Rechazadas), contadores, filtros y paginación.
     */
    public function test_tabs_counters_type_filters_and_pagination_stay_strictly_separated(): void
    {
        // 2 en Por revisar (1 borrador Noticia, 1 programada Efeméride con año 1976)
        $draftNoticia = Post::create([
            'title'          => 'Borrador Noticia A',
            'slug'           => 'borrador-noticia-a',
            'content'        => '<p>Texto A</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Borrador Noticia A',
            'categories'     => ['Noticias Rock'],
        ]);
        $this->attachCategory($draftNoticia, 'Noticias Rock', 'noticias-rock');

        $scheduledEfemeride = Post::create([
            'title'          => 'Programada Efemeride 1976',
            'slug'           => 'programada-efemeride-1976',
            'content'        => '<p>Texto B</p>',
            'status'         => 'scheduled',
            'is_published'   => false,
            'published_at'   => now()->addDays(2),
            'source_subject' => '[DV-1] Efemeride 1976',
            'categories'     => ['Hoy en el Rock'],
        ]);
        $this->attachCategory($scheduledEfemeride, 'Hoy en el Rock', 'hoy-en-el-rock');
        EmailPostSanitizedMeta::create([
            'post_id'         => $scheduledEfemeride->id,
            'event_date'      => '1976-11-26',
            'security_status' => 'needs_review',
        ]);

        // 1 Publicada (Efeméride)
        $publishedEfemeride = Post::create([
            'title'          => 'Publicada Efemeride 1989',
            'slug'           => 'publicada-efemeride-1989',
            'content'        => '<p>Texto C</p>',
            'status'         => 'published',
            'is_published'   => true,
            'published_at'   => now()->subHour(),
            'source_subject' => '[DV-1] Publicada 1989',
            'categories'     => ['Hoy en el Rock'],
        ]);
        $this->attachCategory($publishedEfemeride, 'Hoy en el Rock', 'hoy-en-el-rock');

        // 1 Rechazada (Noticia)
        $rejectedNoticia = Post::create([
            'title'          => 'Rechazada Noticia D',
            'slug'           => 'rechazada-noticia-d',
            'content'        => '<p>Texto D</p>',
            'status'         => 'rejected',
            'is_published'   => false,
            'source_subject' => '[DV-1] Rechazada D',
            'categories'     => ['Noticias Rock'],
        ]);
        $this->attachCategory($rejectedNoticia, 'Noticias Rock', 'noticias-rock');
        EmailPostSanitizedMeta::create([
            'post_id'         => $rejectedNoticia->id,
            'security_status' => 'rejected',
            'incident_reason' => 'Comunicado duplicado',
        ]);

        // Pestaña inicial por defecto: Por revisar
        $resReview = $this->actingAs($this->admin)->get(route('admin.editorial.index'));
        $resReview->assertOk();
        $resReview->assertViewHas('currentTab', 'review');
        $resReview->assertViewHas('counts', fn ($c) => $c['review'] === 2 && $c['published'] === 1 && $c['rejected'] === 1);
        $resReview->assertSee('Borrador Noticia A');
        $resReview->assertSee('Programada Efemeride 1976');
        $resReview->assertSee('Año 1976');
        $resReview->assertDontSee('Publicada Efemeride 1989');
        $resReview->assertDontSee('Rechazada Noticia D');

        // Filtro por tipo dentro de Por revisar: solo Efemérides
        $resReviewEf = $this->actingAs($this->admin)->get(route('admin.editorial.index', ['tab' => 'review', 'type' => 'efemeride']));
        $resReviewEf->assertOk();
        $resReviewEf->assertSee('Programada Efemeride 1976');
        $resReviewEf->assertDontSee('Borrador Noticia A');

        // Pestaña Publicadas
        $resPub = $this->actingAs($this->admin)->get(route('admin.editorial.index', ['tab' => 'published']));
        $resPub->assertOk();
        $resPub->assertViewHas('currentTab', 'published');
        $resPub->assertSee('Publicada Efemeride 1989');
        $resPub->assertDontSee('Borrador Noticia A');
        $resPub->assertDontSee('Rechazada Noticia D');

        // Pestaña Rechazadas
        $resRej = $this->actingAs($this->admin)->get(route('admin.editorial.index', ['tab' => 'rejected']));
        $resRej->assertOk();
        $resRej->assertViewHas('currentTab', 'rejected');
        $resRej->assertSee('Rechazada Noticia D');
        $resRej->assertSee('Comunicado duplicado');
        $resRej->assertDontSee('Borrador Noticia A');
        $resRej->assertDontSee('Publicada Efemeride 1989');
    }

    /**
     * 2. Publicar o rechazar mueve la pieza al grupo correspondiente; restaurar una rechazada la devuelve a revisión.
     */
    public function test_publish_reject_and_restore_move_items_with_audit_trail(): void
    {
        $post = Post::create([
            'title'          => 'Pieza Flujo Editorial',
            'slug'           => 'pieza-flujo-editorial',
            'content'        => '<p>Contenido limpio sin enlaces externos.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'featured_image' => 'assets/lucille/logo.png',
            'source_subject' => '[DV-1] Flujo Editorial',
            'categories'     => ['Noticias Rock'],
        ]);
        $this->attachCategory($post, 'Noticias Rock', 'noticias-rock');
        EmailPostSanitizedMeta::create([
            'post_id'         => $post->id,
            'security_status' => 'needs_review',
        ]);

        // Rechazar -> pasa a Rechazadas sin eliminarse
        $rej = $this->actingAs($this->admin)->post(route('admin.editorial.reject', $post), [
            'reason' => 'Fuera de agenda semanal',
        ]);
        $rej->assertStatus(302);
        $post->refresh();
        $this->assertSame('rejected', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertSame('Fuera de agenda semanal', $post->sanitizedMeta->incident_reason);

        // Restaurar -> vuelve a Por revisar con registro de quién y cuándo, sin publicar
        $restore = $this->actingAs($this->admin)->post(route('admin.editorial.restore', $post), [
            'reason' => 'Reconsiderado para edición de tarde',
        ]);
        $restore->assertStatus(302);
        $post->refresh();
        $this->assertSame('draft', $post->status);
        $this->assertFalse((bool) $post->is_published);
        $this->assertSame('needs_review', $post->sanitizedMeta->security_status);
        $this->assertStringContainsString('Devuelto a revisión', (string) $post->sanitizedMeta->editorial_notes);
        $this->assertStringContainsString('Editor Jefe', (string) $post->sanitizedMeta->editorial_notes);

        // Publicar por circuito formal -> pasa a Publicadas
        $pub = $this->actingAs($this->admin)->post(route('admin.editorial.approve', $post));
        $pub->assertStatus(302);
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_published);
    }

    /**
     * 3. Una efeméride conserva su tipo y categoría al editar texto o portada;
     *    cambiar expresamente el tipo actualiza la sección conservando categorías compatibles.
     */
    public function test_efemeride_preserves_classification_on_edit_and_supports_explicit_type_change(): void
    {
        $post = Post::create([
            'title'          => 'Led Zeppelin IV cumple aniversario',
            'slug'           => 'led-zeppelin-iv-aniversario',
            'content'        => '<p>Texto original.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'featured_image' => 'assets/lucille/logo.png',
            'categories'     => ['Hoy en el Rock', 'Rock'],
        ]);
        $this->attachCategory($post, 'Hoy en el Rock', 'hoy-en-el-rock');
        $this->attachCategory($post, 'Rock', 'rock');

        // Editar título y texto sin tocar el tipo -> debe seguir siendo Efeméride (Hoy en el Rock) + Rock
        $res1 = $this->actingAs($this->admin)->put(route('admin.posts.update', $post), [
            'title'           => 'Led Zeppelin IV: edición revisada',
            'slug'            => 'led-zeppelin-iv-aniversario',
            'content_text'    => '<p>Texto actualizado.</p>',
            'featured_image'  => 'assets/lucille/logo.png',
            'categories_text' => 'Hoy en el Rock, Rock',
            'editorial_type'  => 'efemeride',
            '_action'         => 'draft',
        ]);
        $res1->assertStatus(302);
        $res1->assertSessionHasNoErrors();

        $post->refresh()->load('taxonomies');
        $resolved1 = EditorialClassification::resolve($post);
        $this->assertSame(EditorialClassification::TYPE_EFEMERIDE, $resolved1['type']);
        $this->assertSame('Hoy en el Rock', $resolved1['destination']);
        $this->assertContains('Rock', $post->categories);

        // Cambio explícito de tipo a Noticia -> pasa a Noticias Rock y conserva Rock (sin conflicto)
        $res2 = $this->actingAs($this->admin)->put(route('admin.posts.update', $post), [
            'title'           => 'Led Zeppelin IV: edición revisada',
            'slug'            => 'led-zeppelin-iv-aniversario',
            'content_text'    => '<p>Texto actualizado.</p>',
            'featured_image'  => 'assets/lucille/logo.png',
            'categories_text' => 'Hoy en el Rock, Rock',
            'editorial_type'  => 'noticia',
            '_action'         => 'draft',
        ]);
        $res2->assertStatus(302);
        $res2->assertSessionHasNoErrors();

        $post->refresh()->load('taxonomies');
        $resolved2 = EditorialClassification::resolve($post);
        $this->assertSame(EditorialClassification::TYPE_NOTICIA, $resolved2['type']);
        $this->assertSame('Noticias Rock', $resolved2['destination']);
        $this->assertFalse($resolved2['has_conflict']);
        $this->assertContains('Rock', $post->categories);
        $this->assertNotContains('Hoy en el Rock', $post->categories);
    }

    /**
     * 4. Un registro sin clasificación o contradictorio no se etiqueta por suposición (nunca por palabras del título).
     */
    public function test_unclassified_and_contradictory_posts_are_never_guessed_from_title(): void
    {
        $unclassified = Post::create([
            'title'          => 'Efeméride histórica de Metallica en 1986',
            'slug'           => 'efemeride-sin-categoria',
            'content'        => '<p>Sin categoría guardada.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Sin categoria',
            'categories'     => [],
        ]);

        $contradictory = Post::create([
            'title'          => 'Artículo con categorías contradictorias',
            'slug'           => 'articulo-contradictorio',
            'content'        => '<p>Tiene ambas secciones.</p>',
            'status'         => 'draft',
            'is_published'   => false,
            'source_subject' => '[DV-1] Contradictorio',
            'categories'     => ['Noticias Rock', 'Hoy en el Rock'],
        ]);
        $this->attachCategory($contradictory, 'Noticias Rock', 'noticias-rock');
        $this->attachCategory($contradictory, 'Hoy en el Rock', 'hoy-en-el-rock');

        $resUnclassified = EditorialClassification::resolve($unclassified->fresh('taxonomies'));
        $this->assertSame(EditorialClassification::TYPE_UNCLASSIFIED, $resUnclassified['type']);
        $this->assertFalse($resUnclassified['has_conflict']);

        $resConflict = EditorialClassification::resolve($contradictory->fresh('taxonomies'));
        $this->assertSame(EditorialClassification::TYPE_UNCLASSIFIED, $resConflict['type']);
        $this->assertTrue($resConflict['has_conflict']);

        // En la bandeja, ambos aparecen bajo Sin clasificar y NO en Noticias ni en Efemérides
        $resFilterUnclass = $this->actingAs($this->admin)->get(route('admin.editorial.index', ['tab' => 'review', 'type' => 'unclassified']));
        $resFilterUnclass->assertOk();
        $resFilterUnclass->assertSee('Efeméride histórica de Metallica en 1986');
        $resFilterUnclass->assertSee('Artículo con categorías contradictorias');
        $resFilterUnclass->assertSee('Conflicto de clasificación');

        $resFilterEf = $this->actingAs($this->admin)->get(route('admin.editorial.index', ['tab' => 'review', 'type' => 'efemeride']));
        $resFilterEf->assertDontSee('Efeméride histórica de Metallica en 1986');
        $resFilterEf->assertDontSee('Artículo con categorías contradictorias');
    }
}

