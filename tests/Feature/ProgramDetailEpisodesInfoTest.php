<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SiteController;
use App\Models\MasterProgram;
use App\Models\RadioProgram;
use App\Services\ArchiveOrgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgramDetailEpisodesInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_episode_review_text_sanitizes_html_and_unserializes(): void
    {
        // 1. Texto con HTML y entidades
        $rawHtml = '<p>Gran reseña con <strong>Rock &amp; Roll</strong> y saltos.<br></p>';
        $cleaned = SiteController::cleanEpisodeReviewText($rawHtml);
        $this->assertSame('Gran reseña con Rock & Roll y saltos.', $cleaned);

        // 2. Serialización PHP
        $serialized = serialize('Texto recuperado de serializacion PHP');
        $this->assertSame('Texto recuperado de serializacion PHP', SiteController::cleanEpisodeReviewText($serialized));

        // 3. JSON encode
        $json = json_encode(['Capítulo dedicado a Black Sabbath y sus primeros 4 álbumes']);
        $this->assertSame('Capítulo dedicado a Black Sabbath y sus primeros 4 álbumes', SiteController::cleanEpisodeReviewText($json));

        // 4. Nombre técnico de archivo debe ser descartado (null)
        $this->assertNull(SiteController::cleanEpisodeReviewText('01_rock_made_in_amazon.mp3'));
        $this->assertNull(SiteController::cleanEpisodeReviewText('show_episode_12.wav'));

        // 5. Coincidencia con descripción general del programa maestro debe ser descartada
        $generalDesc = 'Este es el programa oficial de rock clásico de Seven Rock Radio emitido los domingos.';
        $this->assertNull(SiteController::cleanEpisodeReviewText($generalDesc, $generalDesc));
        $this->assertNull(SiteController::cleanEpisodeReviewText('Este es el programa oficial de rock clásico', $generalDesc));
    }

    public function test_extract_episode_number_extracts_integers_reliably(): void
    {
        $this->assertSame(1, SiteController::extractEpisodeNumber('01_ROCK_MADE.mp3'));
        $this->assertSame(45, SiteController::extractEpisodeNumber('Episodio 45 - Especial Deep Purple'));
        $this->assertSame(12, SiteController::extractEpisodeNumber('Show', 'rock_ep_12.mp3'));
        $this->assertSame(105, SiteController::extractEpisodeNumber('Capitulo #105'));
        $this->assertNull(SiteController::extractEpisodeNumber('SinNumero', 'podcast_special.mp3'));
    }

    public function test_program_detail_renders_episode_description_and_expansion_button_for_long_review(): void
    {
        $master = MasterProgram::create([
            'nombre' => 'Rock Made In Amazon',
            'conductor' => 'Jose Font',
            'dia_transmision' => 'SABADO',
            'hora_transmision' => '20:00',
            'genero' => 'Rock',
            'archive_identifier' => 'rock_made_in_amazon_archive',
            'storage_folder' => 'rock-made-in-amazon',
            'descripcion' => 'Programa insignia sobre el rock en la Amazonía.',
            'red_social1_url' => 'https://instagram.com/rockmadeinamazon',
            'red_social2_url' => 'https://facebook.com/rockmadeinamazon',
            'url_mixcloud' => 'https://mixcloud.com/rockmadeinamazon',
            'activo' => true,
        ]);

        $longReview = 'En este capítulo especial realizamos un recorrido detallado por la historia de las primeras bandas de garage de los años setenta en la región amazónica. Analizamos cómo influyeron los sonidos psicodélicos importados desde Manaos y Lima, exploramos anécdotas de grabación inéditas compartidas por los músicos originales, y presentamos pistas remasterizadas exclusivas de vinilos de colección nunca antes digitalizados.';

        // Episodio 1: reseña larga y aprobado
        $ep1 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => $master->nombre,
            'conductor' => $master->conductor,
            'titulo_episodio' => 'Especial Garage Amazónico',
            'numero_episodio' => 1,
            'archivo_mp3' => 'episodes/01_garage_amazonico.mp3',
            'resena' => $longReview,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
        ]);

        // Episodio 2: reseña corta y aprobado
        $shortReview = 'Breve repaso acústico de los años noventa.';
        $ep2 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => $master->nombre,
            'conductor' => $master->conductor,
            'titulo_episodio' => 'Acústicos 90s',
            'numero_episodio' => 2,
            'archivo_mp3' => 'episodes/02_acusticos_90s.mp3',
            'resena' => $shortReview,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_APPROVED,
        ]);

        // Episodio 3: sin reseña y en borrador (no aprobado)
        $ep3 = RadioProgram::create([
            'master_program_id' => $master->id,
            'titulo_programa' => $master->nombre,
            'conductor' => $master->conductor,
            'titulo_episodio' => 'Sesión Experimental Sin Reseña',
            'numero_episodio' => 3,
            'archivo_mp3' => 'episodes/03_experimental.mp3',
            'resena' => null,
            'editorial_status' => RadioProgram::EDITORIAL_STATUS_PENDING,
        ]);

        // Mock de Archive.org cache para retornar los tres episodios
        $cacheKey = "site.program.detail.archive_data." . md5('rock_made_in_amazon_archive');
        Cache::put($cacheKey, [
            'episodes' => [
                [
                    'id' => '01_garage_amazonico.mp3',
                    'title' => 'Especial Garage Amazónico',
                    'src' => 'https://archive.org/download/rock_made_in_amazon_archive/01_garage_amazonico.mp3',
                    'published_at' => time() - 3600,
                    'views' => 120,
                    'duration' => '54:12',
                    'size' => 50000000,
                    'archive_desc' => null,
                ],
                [
                    'id' => '02_acusticos_90s.mp3',
                    'title' => 'Acústicos 90s',
                    'src' => 'https://archive.org/download/rock_made_in_amazon_archive/02_acusticos_90s.mp3',
                    'published_at' => time() - 7200,
                    'views' => 85,
                    'duration' => '42:10',
                    'size' => 40000000,
                    'archive_desc' => null,
                ],
                [
                    'id' => '03_experimental.mp3',
                    'title' => 'Sesión Experimental Sin Reseña',
                    'src' => 'https://archive.org/download/rock_made_in_amazon_archive/03_experimental.mp3',
                    'published_at' => time() - 10800,
                    'views' => 30,
                    'duration' => '30:00',
                    'size' => 30000000,
                    'archive_desc' => null,
                ],
            ],
            'program' => [
                'id' => 'rock_made_in_amazon_archive',
                'title' => $master->nombre,
                'name' => $master->nombre,
                'cover' => 'https://sevenrockradio.com/cover.jpg',
                'host' => 'Jose Font',
                'conductor' => 'Jose Font',
                'schedule' => 'Sábados 20:00',
                'description' => $master->descripcion,
                'social_1' => $master->red_social1_url,
                'social_2' => $master->red_social2_url,
                'mixcloud' => $master->url_mixcloud,
            ],
        ], now()->addMinutes(10));

        $response = $this->get('/programas/rock_made_in_amazon_archive');
        $response->assertOk();

        // 1. Redes sociales visibles en la cabecera
        $response->assertSee('Instagram');
        $response->assertSee('Facebook');
        $response->assertSee('Mixcloud');
        $response->assertSee('https://instagram.com/rockmadeinamazon');

        // 2. Episodio 1 (reseña larga): texto presente y botón "Ver más"
        $response->assertSee('En este capítulo especial realizamos un recorrido detallado');
        $response->assertSee('Ver más');
        $response->assertSee('Ver menos');
        $response->assertSee(':aria-expanded', false);

        // Ficha del episodio 1 (aprobado): enlace presente
        $expectedUrlEp1 = route('public.podcasts.episode', [
            'programSlug' => $master->publicSlug(),
            'episodeNumber' => 1,
        ]);
        $response->assertSee($expectedUrlEp1, false);
        $response->assertSee('Ver ficha del episodio');

        // 3. Episodio 2 (reseña corta): texto presente
        $response->assertSee('Breve repaso acústico de los años noventa.');
        // Ficha del episodio 2 (aprobado): enlace presente
        $expectedUrlEp2 = route('public.podcasts.episode', [
            'programSlug' => $master->publicSlug(),
            'episodeNumber' => 2,
        ]);
        $response->assertSee($expectedUrlEp2, false);

        // 4. Episodio 3 (sin reseña): texto discreto de ausencia
        $response->assertSee('Este episodio todavía no tiene descripción');

        // Episodio 3 no está aprobado: NO debe tener enlace a ficha
        $notExpectedUrlEp3 = route('public.podcasts.episode', [
            'programSlug' => $master->publicSlug(),
            'episodeNumber' => 3,
        ]);
        $response->assertDontSee($notExpectedUrlEp3, false);
    }

    public function test_program_detail_archive_org_description_handling_and_long_titles(): void
    {
        $generalDesc = 'Descripción general del programa maestro de hard rock y heavy metal.';
        $master = MasterProgram::create([
            'nombre' => 'La Esencia Del Rock',
            'conductor' => 'Pedro Garcia',
            'dia_transmision' => 'VIERNES',
            'hora_transmision' => '22:00',
            'genero' => 'Heavy Metal',
            'archive_identifier' => 'la_esencia_del_rock_archive',
            'storage_folder' => 'la-esencia-del-rock',
            'descripcion' => $generalDesc,
            'activo' => true,
        ]);

        $longTitle = 'Episodio 88 - Crónica exhaustiva del festival Monsters of Rock en Donington Park 1988 con Iron Maiden, Kiss, David Lee Roth y Megadeth';

        // Cache de Archive con:
        // Ep A: archivo con descripción que es una copia idéntica de la descripción general (debe descartarse)
        // Ep B: archivo con descripción propia y título largo (debe mantenerse)
        $cacheKey = "site.program.detail.archive_data." . md5('la_esencia_del_rock_archive');
        Cache::put($cacheKey, [
            'episodes' => [
                [
                    'id' => 'ep87_general_dup.mp3',
                    'title' => 'Episodio 87',
                    'src' => 'https://archive.org/download/la_esencia_del_rock_archive/ep87_general_dup.mp3',
                    'published_at' => time() - 86400,
                    'duration' => '60:00',
                    'size' => 60000000,
                    'archive_desc' => $generalDesc, // Idéntica a la general -> debe descartarse
                ],
                [
                    'id' => 'ep88_monsters_of_rock.mp3',
                    'title' => $longTitle,
                    'src' => 'https://archive.org/download/la_esencia_del_rock_archive/ep88_monsters_of_rock.mp3',
                    'published_at' => time() - 172800,
                    'duration' => '75:00',
                    'size' => 75000000,
                    'archive_desc' => 'Entrevista exclusiva a miembros de la producción y análisis del mítico concierto.',
                ],
            ],
            'program' => [
                'id' => 'la_esencia_del_rock_archive',
                'title' => $master->nombre,
                'name' => $master->nombre,
                'cover' => 'https://sevenrockradio.com/cover2.jpg',
                'host' => 'Pedro Garcia',
                'conductor' => 'Pedro Garcia',
                'schedule' => 'Viernes 22:00',
                'description' => $generalDesc,
            ],
        ], now()->addMinutes(10));

        $response = $this->get('/programas/la_esencia_del_rock_archive');
        $response->assertOk();

        // Ep A: NO debe mostrar la descripción general en la reseña del episodio
        // Debe mostrar "Este episodio todavía no tiene descripción"
        $response->assertSee('Este episodio todavía no tiene descripción');

        // Ep B: título largo visible sin truncate (break-words leading-snug)
        $response->assertSee($longTitle);
        $response->assertSee('break-words leading-snug', false);
        $response->assertSee('Entrevista exclusiva a miembros de la producción');
    }
}
