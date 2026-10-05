<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SiteController;
use App\Models\Post;
use App\Models\PostTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EfemeridesExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_efemerides_post_does_not_appear_in_recent_posts(): void
    {
        // 1. Post normal publicado
        $normalPost = Post::create([
            'title' => 'Noticia Normal de Rock',
            'slug' => 'noticia-normal-de-rock',
            'content' => 'Contenido de la noticia.',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
            'categories' => ['Noticias Rock'],
        ]);

        // 2. Post con taxonomía 'Hoy en el Rock'
        $efemeridePost = Post::create([
            'title' => 'Efeméride 1975 Nace Icono',
            'slug' => 'efemeride-1975-nace-icono',
            'content' => 'Contenido de la efeméride.',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
            'categories' => ['Hoy en el Rock'],
        ]);

        $taxonomy = PostTaxonomy::firstOrCreate(
            ['type' => PostTaxonomy::TYPE_CATEGORY, 'slug' => 'hoy-en-el-rock'],
            ['name' => 'Hoy en el Rock']
        );
        $efemeridePost->taxonomies()->sync([$taxonomy->id]);

        // A. Verificar en la vista de post individual (singlePost)
        $singleUrl = route('posts.single', [
            'year' => $normalPost->published_at->format('Y'),
            'month' => $normalPost->published_at->format('m'),
            'day' => $normalPost->published_at->format('d'),
            'slug' => $normalPost->slug,
        ]);

        $responseSingle = $this->get($singleUrl);
        $responseSingle->assertStatus(200);

        $recentPostsSingle = collect($responseSingle->viewData('recentPosts'));
        $this->assertTrue(
            $recentPostsSingle->contains('title', 'Noticia Normal de Rock'),
            'El listado de recentPosts en post individual debe incluir noticias normales.'
        );
        $this->assertFalse(
            $recentPostsSingle->contains('title', 'Efeméride 1975 Nace Icono'),
            'El listado de recentPosts en post individual NO debe incluir efemérides (Hoy en el Rock).'
        );

        // B. Verificar en el sidebar del blog (blogListing)
        $responseBlog = $this->get('/blog');
        $responseBlog->assertStatus(200);

        $recentPostsBlog = collect($responseBlog->viewData('recentPosts'));
        $this->assertTrue(
            $recentPostsBlog->contains('title', 'Noticia Normal de Rock'),
            'El listado de recentPosts en el sidebar del blog debe incluir noticias normales.'
        );
        $this->assertFalse(
            $recentPostsBlog->contains('title', 'Efeméride 1975 Nace Icono'),
            'El listado de recentPosts en el sidebar del blog NO debe incluir efemérides (Hoy en el Rock).'
        );
    }

    public function test_efemerides_post_appears_in_cached_efemerides_archive(): void
    {
        // Crear efeméride con taxonomía 'Hoy en el Rock'
        $efemeridePost = Post::create([
            'title' => 'Efeméride 1980 Lanzamiento Épico',
            'slug' => 'efemeride-1980-lanzamiento-epico',
            'content' => 'Texto histórico sobre el lanzamiento.',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
            'categories' => ['Hoy en el Rock'],
        ]);

        $taxonomy = PostTaxonomy::firstOrCreate(
            ['type' => PostTaxonomy::TYPE_CATEGORY, 'slug' => 'hoy-en-el-rock'],
            ['name' => 'Hoy en el Rock']
        );
        $efemeridePost->taxonomies()->sync([$taxonomy->id]);

        // A. Verificar mediante reflexión llamando al método cachedEfemeridesArchive de SiteController
        $controller = app(SiteController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('cachedEfemeridesArchive');
        $method->setAccessible(true);
        $archiveItems = collect($method->invoke($controller));

        $this->assertTrue(
            $archiveItems->contains('title', 'Efeméride 1980 Lanzamiento Épico'),
            'El post con taxonomía "Hoy en el Rock" debe aparecer en cachedEfemeridesArchive.'
        );

        // B. Verificar en la vista del blog (donde se inyecta $sidebarEfemerides)
        $responseBlog = $this->get('/blog');
        $responseBlog->assertStatus(200);

        $sidebarEfemerides = collect($responseBlog->viewData('sidebarEfemerides'));
        $this->assertTrue(
            $sidebarEfemerides->contains('title', 'Efeméride 1980 Lanzamiento Épico'),
            'El post con taxonomía "Hoy en el Rock" debe aparecer en sidebarEfemerides en la vista del blog.'
        );
    }
}
