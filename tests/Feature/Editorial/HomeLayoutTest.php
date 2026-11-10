<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Post;
use App\Models\PostTaxonomy;
use App\Models\ThemeSetting;

class HomeLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_reorganized_layout_successfully(): void
    {
        ThemeSetting::current();

        $category = PostTaxonomy::firstOrCreate(
            ['name' => 'Noticias Rock', 'type' => 'category'],
            ['slug' => 'noticias-rock']
        );

        for ($i = 1; $i <= 4; $i++) {
            $post = Post::create([
                'title'        => 'Noticia de prueba ' . $i,
                'slug'         => 'noticia-de-prueba-' . $i,
                'content'      => '<p>Contenido de prueba para validar maquetacion.</p>',
                'excerpt'      => 'Resumen breve ' . $i,
                'status'       => 'published',
                'is_published' => true,
                'published_at' => now()->subMinutes($i * 10),
                'author_email' => 'dark.vader.agent@gmail.com',
                'categories'   => ['Noticias Rock'],
            ]);
            $post->taxonomies()->sync([$category->id]);
        }

        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();

        // Reemplazar rutas de Vite para visualización local
        $buildDir = 'file:///' . str_replace('\\', '/', realpath(base_path('public/build')));
        $html = str_replace('"/build', '"' . $buildDir, $html);
        $html = str_replace('\'/build', '\'' . $buildDir, $html);
        $html = str_replace('https://sevenrockradio.test/build', $buildDir, $html);
        $html = str_replace('http://sevenrockradio.test/build', $buildDir, $html);

        if (!is_dir(base_path('scratch'))) {
            @mkdir(base_path('scratch'), 0777, true);
        }
        file_put_contents(base_path('scratch/candidate_home_viewable.html'), $html);

        // 1. Aserciones de estructura para Programación (next-program)
        $this->assertStringContainsString('aria-label="Programas en parrilla de programación"', $html);
        $this->assertStringContainsString('activeProgram.title', $html);
        $this->assertStringContainsString('activeProgram.schedule', $html);
        $this->assertStringContainsString('activeProgram.summary', $html);

        // 2. Aserciones de estructura para Podcasts (latest-podcasts)
        $this->assertStringContainsString('aria-label="Últimos episodios de podcast"', $html);
        $this->assertStringContainsString('activeEpisode.title', $html);
        $this->assertStringContainsString('x-ref="audio"', $html);
        $this->assertStringContainsString('lucille-range-slider', $html);
    }

    public function test_efemerides_cintillo_renders_correctly_with_readable_date_and_pause_control(): void
    {
        ThemeSetting::current();

        $catEf = PostTaxonomy::firstOrCreate(
            ['name' => 'Hoy en el Rock', 'type' => 'category'],
            ['slug' => 'hoy-en-el-rock']
        );

        $post = Post::create([
            'title'        => 'Nace Ronnie James Dio en Portsmouth',
            'slug'         => 'nace-ronnie-james-dio',
            'content'      => '<p>Efeméride de prueba.</p>',
            'excerpt'      => 'Ronnie James Dio',
            'status'       => 'published',
            'is_published' => true,
            'published_at' => now(),
            'author_email' => 'dark.vader.agent@gmail.com',
            'categories'   => ['Hoy en el Rock'],
        ]);
        $post->taxonomies()->sync([$catEf->id]);

        $response = $this->get('/');
        $response->assertStatus(200);

        $html = $response->getContent();

        // 1. Contenedor y cintillo
        $this->assertStringContainsString('id="efemerides-ticker"', $html);
        $this->assertStringContainsString('🎸 Hoy en el Rock', $html);
        $this->assertStringContainsString('id="efem-ticker-toggle"', $html);
        $this->assertStringContainsString('aria-label="Pausar movimiento de efemérides"', $html);

        // 2. Control de texto y pista
        $this->assertStringContainsString('class="efem-track', $html);
        $this->assertStringContainsString('Nace Ronnie James Dio en Portsmouth', $html);
    }
}
