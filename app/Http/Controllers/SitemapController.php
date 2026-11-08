<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MasterProgram;
use App\Models\NewRelease;
use App\Models\Post;
use App\Models\RadioProgram;
use App\Models\Talent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SitemapController extends Controller
{
    public const CACHE_KEY = 'site_sitemap_xml';
    public const CACHE_TTL_SECONDS = 1800; // 30 minutos

    public function xml(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): string {
            return $this->generateXml();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=' . self::CACHE_TTL_SECONDS . ', s-maxage=' . self::CACHE_TTL_SECONDS,
        ]);
    }

    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function generateXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $seenUrls = [];

        // 1. Páginas estáticas canónicas principales (excluye /talentos por ser 301)
        $staticRoutes = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => route('talents.explore'), 'priority' => '0.8', 'changefreq' => 'weekly'], // /muro-del-rock
            ['loc' => route('blog'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => route('events'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('programs'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('multimedia'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => route('new-releases.index'), 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => route('comunidad.muro'), 'priority' => '0.6', 'changefreq' => 'weekly'],
            ['loc' => route('contact'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticRoutes as $page) {
            $loc = $page['loc'];
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $xml .= $this->url($loc, $page['priority'], $page['changefreq']);
        }

        // 2. Noticias editoriales publicadas (excluye borradores, futuros y efemérides)
        $posts = Post::query()
            ->where('is_published', true)
            ->where(function ($q): void {
                $q->whereNull('status')
                  ->orWhere('status', 'published');
            })
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereDoesntHave('taxonomies', fn ($q) => $q->where('name', 'Hoy en el Rock'))
            ->where(function ($q): void {
                $q->whereNull('categories')
                  ->orWhere('categories', 'not like', '%Hoy en el Rock%');
            })
            ->orderBy('published_at', 'desc')
            ->get();

        foreach ($posts as $post) {
            $pub = $post->published_at;
            if (! $pub || empty($post->slug)) {
                continue;
            }
            $loc = url($pub->format('Y') . '/' . $pub->format('m') . '/' . $pub->format('d') . '/' . $post->slug);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($post->updated_at ?? $pub)->toIso8601String();
            $xml .= $this->url($loc, '0.7', 'monthly', $lastmod);
        }

        // 3. Eventos publicados
        $events = Event::query()
            ->published()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($events as $event) {
            if (empty($event->slug)) {
                continue;
            }
            $loc = route('events.single', $event->slug);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($event->updated_at ?? $event->created_at ?? now())->toIso8601String();
            $xml .= $this->url($loc, '0.6', 'monthly', $lastmod);
        }

        // 4. Programas maestros activos (MasterProgram)
        $programs = MasterProgram::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($programs as $program) {
            $id = $program->archive_identifier ?: Str::slug($program->nombre);
            if (empty($id)) {
                continue;
            }
            $loc = route('programs.detail', $id);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($program->updated_at ?? $program->created_at ?? now())->toIso8601String();
            $xml .= $this->url($loc, '0.7', 'weekly', $lastmod);
        }

        // 5. Episodios de podcast aprobados, no pendientes de procesamiento ni futuros
        $episodes = RadioProgram::query()
            ->whereIn('editorial_status', [
                RadioProgram::EDITORIAL_STATUS_APPROVED,
                RadioProgram::EDITORIAL_STATUS_APPROVED_AUTONOMOUS,
            ])
            ->whereNotNull('numero_episodio')
            ->where(function ($q): void {
                $q->whereNull('fecha_emision')
                  ->orWhere('fecha_emision', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('processing_started_at')
                  ->orWhereNotNull('processing_finished_at');
            })
            ->whereNotNull('archivo_mp3')
            ->where('archivo_mp3', '!=', '')
            ->whereHas('masterProgram', fn ($q) => $q->where('activo', true))
            ->with('masterProgram')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($episodes as $episode) {
            $master = $episode->masterProgram;
            if (! $master || ! $master->activo) {
                continue;
            }
            $programSlug = $master->publicSlug();
            if (empty($programSlug)) {
                continue;
            }
            $loc = route('public.podcasts.episode', [
                'programSlug' => $programSlug,
                'episodeNumber' => (int) $episode->numero_episodio,
            ]);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($episode->updated_at ?? $episode->created_at ?? now())->toIso8601String();
            $xml .= $this->url($loc, '0.6', 'monthly', $lastmod);
        }

        // 6. Perfiles públicos activos del Muro del Rock (Talent)
        $talents = Talent::query()
            ->publiclyVisible()
            ->whereNotNull('band_name')
            ->where('band_name', '!=', '')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($talents as $talent) {
            if (empty($talent->band_name)) {
                continue;
            }
            $loc = route('talents.show', ['bandName' => $talent->band_name]);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($talent->updated_at ?? $talent->created_at ?? now())->toIso8601String();
            $xml .= $this->url($loc, '0.6', 'weekly', $lastmod);
        }

        // 7. Nuevos lanzamientos activos y no futuros
        $releases = NewRelease::query()
            ->publiclyAccessible()
            ->where('is_active', true)
            ->where('show_in_feed', true)
            ->whereNotNull('audio_path')
            ->where('audio_path', '!=', '')
            ->where(function ($q): void {
                $q->whereNull('released_at')
                  ->orWhere('released_at', '<=', now());
            })
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('released_at')
            ->get();

        foreach ($releases as $release) {
            if (empty($release->slug)) {
                continue;
            }
            $loc = route('new-releases.single', $release->slug);
            if (isset($seenUrls[$loc])) {
                continue;
            }
            $seenUrls[$loc] = true;
            $lastmod = ($release->updated_at ?? $release->created_at ?? now())->toIso8601String();
            $xml .= $this->url($loc, '0.6', 'weekly', $lastmod);
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function url(string $loc, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $out = "  <url>\n";
        $out .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        if ($lastmod) {
            $out .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
        }
        $out .= '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . "</changefreq>\n";
        $out .= '    <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . "</priority>\n";
        $out .= "  </url>\n";

        return $out;
    }
}
