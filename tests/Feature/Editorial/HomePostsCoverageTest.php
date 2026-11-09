<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePostsCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_posts_includes_editorial_authored_news_and_excludes_stale_articles_by_sorting(): void
    {
        ThemeSetting::current();

        $category = PostTaxonomy::firstOrCreate(
            ['name' => 'Noticias Rock', 'type' => 'category'],
            ['slug' => 'noticias-rock']
        );

        // Crear una noticia antigua (ej. Sleep Token del 21 de octubre)
        $oldPost = Post::create([
            'title'        => 'Sleep Token bate récords de reproducciones',
            'slug'         => 'sleep-token-bate-records',
            'content'      => '<p>Noticia antigua de Sleep Token</p>',
            'excerpt'      => 'Noticia antigua de Sleep Token',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now()->subDays(19),
            'author_email' => 'dark.vader.agent@gmail.com',
            'categories'   => ['Noticias Rock'],
        ]);
        $oldPost->taxonomies()->sync([$category->id]);

        // Crear las 4 noticias recientes del 9 de noviembre
        // 1. Richard Lloyd (Darth Vader)
        $p1 = Post::create([
            'title'        => 'Muere Richard Lloyd, guitarrista fundador de Television, a los 75 años',
            'slug'         => 'muere-richard-lloyd-guitarrista-fundador-de-television-a-los-75-anos',
            'content'      => '<p>Fallece Richard Lloyd</p>',
            'excerpt'      => 'Fallece Richard Lloyd',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now(),
            'author_email' => 'dark.vader.agent@gmail.com',
            'categories'   => ['Noticias Rock'],
        ]);
        $p1->taxonomies()->sync([$category->id]);

        // 2. Sepultura (Darth Vader)
        $p2 = Post::create([
            'title'        => 'Sepultura cierran más de cuatro décadas de carrera con su concierto final en São Paulo',
            'slug'         => 'sepultura-cierran-mas-de-cuatro-decadas-de-carrera-con-su-concierto-final-en-sao-paulo',
            'content'      => '<p>Concierto final de Sepultura</p>',
            'excerpt'      => 'Concierto final de Sepultura',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now(),
            'author_email' => 'dark.vader.agent@gmail.com',
            'categories'   => ['Noticias Rock'],
        ]);
        $p2->taxonomies()->sync([$category->id]);

        // 3. John Tempesta (Elaboración propia editorial - autor editorial@sevenrockradio.com)
        $p3 = Post::create([
            'title'        => 'John Tempesta anuncia su salida de The Cult tras veinte años en la banda',
            'slug'         => 'john-tempesta-anuncia-su-salida-de-the-cult-tras-veinte-anos-en-la-banda',
            'content'      => '<p>Salida de John Tempesta de The Cult</p>',
            'excerpt'      => 'Salida de John Tempesta de The Cult',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now(),
            'author_email' => 'editorial@sevenrockradio.com', // Autor no Darth Vader
            'categories'   => ['Noticias Rock'],
        ]);
        $p3->taxonomies()->sync([$category->id]);

        // 4. Deftones (Darth Vader)
        $p4 = Post::create([
            'title'        => 'Deftones rescatan en directo el tema inédito «Melanie» de las sesiones de Eros',
            'slug'         => 'deftones-rescatan-en-directo-el-tema-inedito-melanie-de-las-sesiones-de-eros',
            'content'      => '<p>Deftones tocan Melanie</p>',
            'excerpt'      => 'Deftones tocan Melanie',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now(),
            'author_email' => 'dark.vader.agent@gmail.com',
            'categories'   => ['Noticias Rock'],
        ]);
        $p4->taxonomies()->sync([$category->id]);

        // Consultar la portada
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verificar que las 4 noticias recientes están en el HTML de la portada
        $response->assertSee('John Tempesta anuncia su salida de The Cult tras veinte años en la banda');
        $response->assertSee('Deftones rescatan en directo el tema inédito «Melanie» de las sesiones de Eros');
        $response->assertSee('Sepultura cierran más de cuatro décadas de carrera con su concierto final en São Paulo');
        $response->assertSee('Muere Richard Lloyd, guitarrista fundador de Television, a los 75 años');

        // Comprobar que en la variable $noticiasRock hay exactamente 4 elementos
        $noticiasRock = $response->viewData('noticiasRock');
        $this->assertCount(4, $noticiasRock);

        // Comprobar que John Tempesta ($p3) está entre las 4 noticias de portada
        $ids = $noticiasRock->pluck('id')->all();
        $this->assertContains($p3->id, $ids);
        $this->assertContains($p4->id, $ids);
        $this->assertContains($p2->id, $ids);
        $this->assertContains($p1->id, $ids);

        // Sleep Token NO debe estar en las 4 de la portada porque quedó desplazada por fecha/ID
        $this->assertNotContains($oldPost->id, $ids);
    }
}

