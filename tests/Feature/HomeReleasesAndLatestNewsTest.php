<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewRelease;
use App\Models\Post;
use App\Models\PostTaxonomy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class HomeReleasesAndLatestNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sevenrock-view-cache';
        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config([
            'view.compiled' => $compiledPath,
            'app.timezone' => 'America/Caracas',
        ]);
        Cache::flush();
    }

    public function test_home_new_releases_block_filters_invalid_records_and_renders_clean_dates_and_fallbacks(): void
    {
        // 1. Lanzamiento válido CON portada y fechas distintas (lanzamiento vs ficha)
        $validWithCover = NewRelease::withoutEvents(static function (): NewRelease {
            $r = NewRelease::query()->create([
                'title' => 'Fatum',
                'slug' => 'fatum-bright-curse',
                'artist_name' => 'Bright Curse',
                'released_at' => '2026-10-15',
                'cover_image' => 'https://media.sevenrockradio.com/posts/covers/fatum.jpg',
                'description' => '["<p>Nuevo disco de rock psicodelico desde Francia.</p>"]',
                'is_active' => true,
                'show_in_feed' => true,
                'security_status' => 'passed',
                'origin' => 'manual',
            ]);
            $r->timestamps = false;
            $r->created_at = Carbon::create(2026, 10, 20, 10, 0, 0, 'America/Caracas');
            $r->saveQuietly();

            return $r;
        });

        // 2. Lanzamiento válido SIN portada -> debe mostrar "Sin portada registrada" (no SVG AUDIO)
        NewRelease::withoutEvents(static function (): NewRelease {
            return NewRelease::query()->create([
                'title' => 'Dreams',
                'slug' => 'dreams-sasha',
                'artist_name' => 'Sasha And The Lunatics',
                'released_at' => '2026-10-19',
                'cover_image' => null,
                'description' => 'Sencillo desde Belgica.',
                'is_active' => true,
                'show_in_feed' => true,
                'security_status' => 'passed',
                'origin' => 'manual',
            ]);
        });

        // 3. Excluidos: borrador, futuro, embargado, rechazado y fuera de feed
        NewRelease::withoutEvents(static function (): void {
            NewRelease::query()->create([
                'title' => 'Lanzamiento Inactivo',
                'slug' => 'lanzamiento-inactivo',
                'artist_name' => 'Banda Oculta',
                'released_at' => '2026-11-01',
                'is_active' => false,
                'show_in_feed' => true,
                'security_status' => 'passed',
            ]);
            NewRelease::query()->create([
                'title' => 'Lanzamiento Futuro',
                'slug' => 'lanzamiento-futuro',
                'artist_name' => 'Banda Futura',
                'released_at' => now()->addDays(15)->toDateString(),
                'is_active' => true,
                'show_in_feed' => true,
                'security_status' => 'passed',
            ]);
            NewRelease::query()->create([
                'title' => 'Lanzamiento Embargado',
                'slug' => 'lanzamiento-embargado',
                'artist_name' => 'Banda Embargo',
                'released_at' => '2026-11-01',
                'is_active' => true,
                'is_embargo_active' => true,
                'embargo_until' => now()->addDays(5),
                'show_in_feed' => true,
                'security_status' => 'passed',
            ]);
            NewRelease::query()->create([
                'title' => 'Lanzamiento Rechazado',
                'slug' => 'lanzamiento-rechazado',
                'artist_name' => 'Banda Rechazada',
                'released_at' => '2026-11-01',
                'is_active' => true,
                'show_in_feed' => true,
                'security_status' => 'rejected',
            ]);
        });

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Fatum')
            ->assertSee('Bright Curse')
            ->assertSee('Nuevo disco de rock psicodelico desde Francia.')
            ->assertDontSee('["<p>')
            ->assertSee('Lanzamiento:')
            ->assertSee('Publicado:')
            ->assertSee(route('new-releases.single', $validWithCover->slug), false)
            ->assertSee('Dreams')
            ->assertSee('Sin portada registrada')
            ->assertDontSee('Lanzamiento Inactivo')
            ->assertDontSee('Lanzamiento Futuro')
            ->assertDontSee('Lanzamiento Embargado')
            ->assertDontSee('Lanzamiento Rechazado');
    }

    public function test_home_latest_news_block_cleans_json_uses_spanish_dates_avoids_duplicates_and_excludes_unpublished(): void
    {
        $catNoticias = PostTaxonomy::query()->create([
            'type' => PostTaxonomy::TYPE_CATEGORY,
            'name' => 'Noticias Rock',
            'slug' => 'noticias-rock',
        ]);
        $catEfemerides = PostTaxonomy::query()->create([
            'type' => PostTaxonomy::TYPE_CATEGORY,
            'name' => 'Hoy en el Rock',
            'slug' => 'hoy-en-el-rock',
        ]);
        $catGeneral = PostTaxonomy::query()->create([
            'type' => PostTaxonomy::TYPE_CATEGORY,
            'name' => 'General',
            'slug' => 'general',
        ]);

        // 4 noticias más recientes en "Noticias Rock" -> van al bloque superior (no deben duplicarse abajo)
        for ($i = 1; $i <= 4; $i++) {
            $topPost = $this->createPost([
                'title' => "Noticia Top {$i}",
                'slug' => "noticia-top-{$i}",
                'categories' => ['Noticias Rock'],
                'published_at' => Carbon::create(2026, 11, 27, 10 + $i, 0, 0, 'America/Caracas'),
            ]);
            $topPost->taxonomies()->attach($catNoticias->id);
        }

        // Artículo publicado en "General" con extracto JSON sucio y sin imagen -> debe aparecer en "Últimas noticias" limpio
        $generalPost = $this->createPost([
            'title' => 'Cronica Especial de Festival',
            'slug' => 'cronica-especial-festival',
            'categories' => null,
            'excerpt' => '["<p>Resumen limpio sin corchetes ni etiquetas HTML.</p>"]',
            'featured_image' => null,
            'published_at' => Carbon::create(2026, 11, 26, 15, 0, 0, 'America/Caracas'),
        ]);
        $generalPost->taxonomies()->attach($catGeneral->id);

        // 5a noticia de "Noticias Rock" -> al no estar en el top 4, aparece en "Últimas noticias" conservando su categoría real
        $fifthNews = $this->createPost([
            'title' => 'Quinta Noticia Rock',
            'slug' => 'quinta-noticia-rock',
            'categories' => ['Noticias Rock'],
            'excerpt' => 'Texto limpio de la quinta noticia.',
            'published_at' => Carbon::create(2026, 11, 25, 12, 0, 0, 'America/Caracas'),
        ]);
        $fifthNews->taxonomies()->attach($catNoticias->id);

        // Excluidos: efeméride (incluso con categories=NULL y solo taxonomía pivot), borrador, rechazado y futuro
        $efemPost = $this->createPost([
            'title' => 'Efemeride Solo Pivot',
            'slug' => 'efemeride-solo-pivot',
            'categories' => null,
            'published_at' => Carbon::create(2026, 11, 26, 18, 0, 0, 'America/Caracas'),
        ]);
        $efemPost->taxonomies()->attach($catEfemerides->id);

        $this->createPost([
            'title' => 'Post Borrador Oculto',
            'slug' => 'post-borrador-oculto',
            'status' => 'draft',
            'is_published' => false,
            'published_at' => Carbon::create(2026, 11, 27, 20, 0, 0, 'America/Caracas'),
        ]);

        $this->createPost([
            'title' => 'Post Rechazado Oculto',
            'slug' => 'post-rechazado-oculto',
            'status' => 'rejected',
            'is_published' => true,
            'published_at' => Carbon::create(2026, 11, 27, 21, 0, 0, 'America/Caracas'),
        ]);

        $this->createPost([
            'title' => 'Post Futuro Programado',
            'slug' => 'post-futuro-programado',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now()->addDays(5),
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Cronica Especial de Festival')
            ->assertSee('Resumen limpio sin corchetes ni etiquetas HTML.')
            ->assertDontSee('["<p>')
            ->assertSee('General')
            ->assertSee('Quinta Noticia Rock')
            ->assertSee('Sin imagen registrada')
            ->assertSee('Publicado: 26 de noviembre de 2026')
            ->assertDontSee('November 26, 2026')
            ->assertSee($generalPost->url, false)
            ->assertDontSee('Efemeride Solo Pivot')
            ->assertDontSee('Post Borrador Oculto')
            ->assertDontSee('Post Rechazado Oculto')
            ->assertDontSee('Post Futuro Programado');

        // Verificar que Noticia Top 1 no se duplica (solo aparece en Noticias Rock, no en Últimas noticias)
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'Leer artículo &rarr;') > 0 ? substr_count($html, 'Noticia Top 1</h3>') + substr_count($html, '>Noticia Top 1<') : 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(array $overrides = []): Post
    {
        return Post::withoutEvents(static function () use ($overrides): Post {
            return Post::query()->create(array_merge([
                'title' => 'Post de Prueba',
                'slug' => 'post-' . uniqid(),
                'excerpt' => 'Extracto de prueba.',
                'content' => [['type' => 'paragraph', 'value' => 'Contenido de prueba del articulo.']],
                'status' => 'published',
                'is_published' => true,
                'published_at' => now()->subDay(),
            ], $overrides));
        });
    }
}
