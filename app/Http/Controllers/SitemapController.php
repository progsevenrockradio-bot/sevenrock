<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

final class SitemapController extends Controller
{
    public function xml(): Response
    {
        $posts = Post::query()->whereNotNull('published_at')->orderBy('published_at', 'desc')->get();
        $events = Event::query()->orderByDesc('created_at')->get();
        $videos = Video::query()->orderByDesc('created_at')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Home
        $xml .= $this->url(url('/'), '1.0', 'daily');

        // Páginas estáticas
        foreach (['/blog', '/events', '/discography', '/videos', '/gallery', '/shop', '/contact', '/talentos'] as $path) {
            $xml .= $this->url(url($path), '0.8', 'weekly');
        }

        // Posts
        foreach ($posts as $post) {
            $pub = $post->published_at;
            if (! $pub) continue;
            $url = url($pub->format('Y') . '/' . $pub->format('m') . '/' . $pub->format('d') . '/' . $post->slug);
            $xml .= $this->url($url, '0.6', 'monthly', $pub->toIso8601String());
        }

        // Eventos
        foreach ($events as $event) {
            $xml .= $this->url(route('events.single', $event->slug ?? ''), '0.5', 'monthly');
        }

        // Videos
        foreach ($videos as $video) {
            $xml .= $this->url(route('videos.single', $video->slug ?? ''), '0.5', 'monthly');
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function url(string $loc, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $out = '  <url>' . "\n";
        $out .= '    <loc>' . e($loc) . '</loc>' . "\n";
        if ($lastmod) {
            $out .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        }
        $out .= '    <changefreq>' . $changefreq . '</changefreq>' . "\n";
        $out .= '    <priority>' . $priority . '</priority>' . "\n";
        $out .= '  </url>' . "\n";
        return $out;
    }
}
