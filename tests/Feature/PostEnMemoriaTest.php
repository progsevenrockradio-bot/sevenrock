<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PostEnMemoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        if (\Illuminate\Support\Facades\Schema::hasTable('posts')) {
            Post::query()->delete();
        }
    }

    public function test_post_with_en_memoria_true_displays_luto_component_on_home_and_single_post_page(): void
    {
        $post = Post::query()->create([
            'title' => 'Tributo a Leyenda del Rock',
            'slug' => 'tributo-a-leyenda-del-rock',
            'author' => 'Admin',
            'excerpt' => 'Post en homenaje',
            'content' => 'Contenido del post',
            'featured_image' => 'assets/lucille/logo.png',
            'categories' => ['Noticias Rock'],
            'is_published' => true,
            'status' => 'published',
            'published_at' => now(),
            'en_memoria' => true,
            'en_memoria_nombre' => 'Phil Campbell',
        ]);

        // Portada
        $responseHome = $this->get(route('home'));
        $responseHome->assertOk();
        $responseHome->assertSee('luto-lazo');
        $responseHome->assertSee('En memoria - Phil Campbell');

        // Ficha del post
        $postUrl = route('posts.single', [
            'year' => $post->published_at->format('Y'),
            'month' => $post->published_at->format('m'),
            'day' => $post->published_at->format('d'),
            'slug' => $post->slug,
        ]);

        $responseSingle = $this->get($postUrl);
        $responseSingle->assertOk();
        $responseSingle->assertSee('luto-lazo');
        $responseSingle->assertSee('En memoria - Phil Campbell');
    }

    public function test_post_without_en_memoria_does_not_display_luto_component(): void
    {
        $post = Post::query()->create([
            'title' => 'Noticia Normal de Rock',
            'slug' => 'noticia-normal-de-rock',
            'author' => 'Admin',
            'excerpt' => 'Noticia estándar',
            'content' => 'Contenido de la noticia',
            'featured_image' => 'assets/lucille/logo.png',
            'categories' => ['Noticias Rock'],
            'is_published' => true,
            'status' => 'published',
            'published_at' => now(),
            'en_memoria' => false,
            'en_memoria_nombre' => null,
        ]);

        // Portada
        $responseHome = $this->get(route('home'));
        $responseHome->assertOk();
        $responseHome->assertDontSee('luto-lazo');

        // Ficha del post
        $postUrl = route('posts.single', [
            'year' => $post->published_at->format('Y'),
            'month' => $post->published_at->format('m'),
            'day' => $post->published_at->format('d'),
            'slug' => $post->slug,
        ]);

        $responseSingle = $this->get($postUrl);
        $responseSingle->assertOk();
        $responseSingle->assertDontSee('luto-lazo');
    }
}
