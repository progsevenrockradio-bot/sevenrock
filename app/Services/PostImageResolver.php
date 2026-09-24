<?php

namespace App\Services;

use App\Models\RadioArtist;
use App\Models\ThemeSetting;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Message;
use DOMDocument;

class PostImageResolver
{
    /**
     * Resuelve la imagen para un post a partir del correo.
     * 
     * Contexto esperado:
     * - 'message': \Webklex\PHPIMAP\Message
     * - 'body': string HTML o texto
     * - 'subject': string
     * - 'clean_title': string titular limpio final
     * - 'is_dark_vader': bool
     * - 'artist_name': string|null
     *
     * @return array{url: string, source: string, credit: ?string, article_url: ?string}
     */
    public function resolveForPost(array $context): array
    {
        /** @var Message $message */
        $message = $context['message'];
        $body = $context['body'];
        $cleanTitle = $context['clean_title'];
        $isDarkVader = $context['is_dark_vader'] ?? false;
        $artistName = $context['artist_name'] ?? null;
        
        $settings = ThemeSetting::current();
        
        $defaultUrl = rtrim(config('app.url'), '/') . '/' . ltrim($settings->email_default_cover_path ?: 'assets/lucille/album3.jpg', '/');

        $result = [
            'url' => $defaultUrl,
            'source' => 'default',
            'credit' => null,
            'article_url' => null,
        ];

        // Extraer créditos del cuerpo
        $credit = $this->extractCredit($body);
        if ($credit) {
            $result['credit'] = $credit;
        }

        // a) Attachment
        $attachmentUrl = $this->resolveFromAttachment($message, $isDarkVader);
        if ($attachmentUrl) {
            $result['url'] = $attachmentUrl;
            $result['source'] = 'attachment';
            return $result;
        }

        // b) Source URL (FUENTE: <url>)
        $articleUrlFromSource = $this->extractSourceUrl($body);
        if ($articleUrlFromSource) {
            $result['article_url'] = $articleUrlFromSource;
            $ogImage = $this->resolveFromOgImage($articleUrlFromSource);
            if ($ogImage) {
                $result['url'] = $ogImage;
                $result['source'] = 'source_url';
                return $result;
            }
        }

        // c) RSS
        if ($credit) {
            $rssImageInfo = $this->resolveFromRss($credit, $cleanTitle, $settings, $artistName);
            if ($rssImageInfo && $rssImageInfo['url']) {
                $result['url'] = $rssImageInfo['url'];
                $result['source'] = 'rss';
                $result['article_url'] = $rssImageInfo['article_url'] ?? $result['article_url'];
                return $result;
            }
        }

        // d) Artist Catalog
        if ($artistName) {
            $artistUrl = $this->resolveFromArtistCatalog($artistName);
            if ($artistUrl) {
                $result['url'] = $artistUrl;
                $result['source'] = 'artist_catalog';
                return $result;
            }
        }

        return $result;
    }

    private function resolveFromAttachment(Message $message, bool $isDarkVader): ?string
    {
        $imageMinSize = $isDarkVader ? 10240 : 40960;
        $candidates = [];
        
        foreach ($message->getAttachments() as $attachment) {
            $filename = (string) $attachment->getName();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Ignorar explicitamente archivos que no puedan ser imagenes
            if (in_array($ext, ['mp3', 'wav', 'flac', 'pdf', 'zip', 'rar', 'doc', 'docx'])) {
                continue;
            }

            $content = $attachment->getContent();
            $sizeInBytes = strlen((string) $content);

            $info = @getimagesizefromstring($content);
            if ($info === false) {
                continue;
            }

            $width = $info[0];
            $height = $info[1];
            $mime = $info['mime'];

            if ($width < 200 || $height < 200) {
                continue;
            }

            if ($sizeInBytes < $imageMinSize) {
                continue;
            }

            $realExt = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => null,
            };

            if (!$realExt) {
                continue;
            }

            $ratio = $width / $height;
            $area = $width * $height;
            $isSquare = ($ratio >= 0.9 && $ratio <= 1.1);

            $candidates[] = [
                'content' => $content,
                'ext' => $realExt,
                'width' => $width,
                'height' => $height,
                'area' => $area,
                'is_square' => $isSquare,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            if ($a['is_square'] && !$b['is_square']) {
                return -1;
            }
            if (!$a['is_square'] && $b['is_square']) {
                return 1;
            }

            if (!$a['is_square'] && !$b['is_square']) {
                $aValidWidth = $a['width'] >= 400;
                $bValidWidth = $b['width'] >= 400;
                if ($aValidWidth && !$bValidWidth) {
                    return -1;
                }
                if (!$aValidWidth && $bValidWidth) {
                    return 1;
                }
            }

            return $b['area'] <=> $a['area'];
        });

        $bestCandidate = $candidates[0];

        try {
            $uploaded = app(\App\Services\FileUploadService::class)->uploadRaw(
                $bestCandidate['content'],
                'catalog/releases/covers/' . Str::uuid()->toString() . '.' . $bestCandidate['ext']
            );
            return rtrim(config('app.url'), '/') . '/' . ltrim($uploaded['url'], '/');
        } catch (\Throwable $e) {
            Log::error("PostImageResolver: Fallo al subir portada adjunta: " . $e->getMessage());
        }

        return null;
    }

    private function extractSourceUrl(string $body): ?string
    {
        // Buscar FUENTE: https://...
        if (preg_match('/(?:FUENTE|Source):\s*(https?:\/\/[^\s<]+)/i', strip_tags($body), $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function resolveFromOgImage(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withUserAgent('SevenRockBot/1.0 (+https://sevenrockradio.com/bot)')
                ->get($url);
                
            if ($response->successful()) {
                $html = $response->body();
                if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches) || 
                    preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $matches)) {
                    
                    $ogUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
                    if ($this->isValidImageDomain($ogUrl)) {
                        return $ogUrl;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("PostImageResolver: Error al extraer og:image de {$url}: " . $e->getMessage());
        }
        return null;
    }

    private function extractCredit(string $body): ?string
    {
        if (preg_match('/(?:Creditos|Créditos|Fuente|Source)?\s*:\s*([a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,})/i', strip_tags($body), $matches)) {
            return trim($matches[1]);
        }
        
        if (preg_match('/Creditos?:\s*([A-Za-z0-9\.\-]+)/i', strip_tags($body), $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    private function resolveFromRss(string $creditDomain, string $cleanTitle, ThemeSetting $settings, ?string $artistName = null): ?array
    {
        $creditDomain = strtolower($creditDomain);

        $feeds = config('services.press_feeds', []);

        if ($settings->press_feeds_extra) {
            $lines = explode("\n", $settings->press_feeds_extra);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_contains($line, '=')) {
                    [$domain, $url] = explode('=', $line, 2);
                    $feeds[strtolower(trim($domain))] = trim($url);
                }
            }
        }

        $feedUrl = null;
        foreach ($feeds as $domain => $url) {
            if (str_contains($creditDomain, $domain)) {
                $feedUrl = $url;
                break;
            }
        }

        if (!$feedUrl) {
            return null;
        }

        $cacheKey = 'rss_feed_' . md5($feedUrl);
        $xmlContent = Cache::remember($cacheKey, 15 * 60, function () use ($feedUrl) {
            try {
                $response = Http::timeout(15)
                    ->withUserAgent('SevenRockBot/1.0 (+https://sevenrockradio.com/bot)')
                    ->get($feedUrl);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                Log::warning("PostImageResolver: Error al descargar feed {$feedUrl}: " . $e->getMessage());
            }
            return null;
        });

        if (!$xmlContent) {
            return null;
        }

        $dom = new DOMDocument();
        @$dom->loadXML($xmlContent);

        $items = $dom->getElementsByTagName('item');

        // --- Entidades del titular del post (en español) ---
        $entities = $this->extractEntities($cleanTitle);

        // Si el contexto trae artist_name y no hay entidades suficientes, usarlo como entidad principal
        if ($artistName && array_sum(array_column($entities, 'score')) < 2) {
            $artistWords = explode(' ', TextNormalizer::normalizeSlug($artistName));
            if (count($artistWords) >= 2) {
                $entities[] = ['term' => implode(' ', $artistWords), 'score' => 2];
            } elseif (count($artistWords) === 1 && $artistWords[0] !== '') {
                $entities[] = ['term' => $artistWords[0], 'score' => 1];
            }
        }

        Log::debug("PostImageResolver RSS: título='{$cleanTitle}' entidades=" . json_encode(array_column($entities, 'term')));

        // --- Puntuar cada ítem del feed ---
        $candidates = [];
        foreach ($items as $item) {
            $itemTitleNode = $item->getElementsByTagName('title')->item(0);
            if (!$itemTitleNode) {
                continue;
            }

            $itemTitle    = $itemTitleNode->nodeValue;
            $normItemTitle = TextNormalizer::normalizeSlug($itemTitle);

            // Calcular puntuación por entidades
            $score = 0;
            foreach ($entities as $entity) {
                if (str_contains($normItemTitle, $entity['term'])) {
                    $score += $entity['score'];
                }
            }

            if ($score < 2) {
                continue;
            }

            // Desempate: similitud de títulos (sin umbral mínimo, solo ordena)
            $similarity = 0;
            similar_text(TextNormalizer::normalizeSlug($cleanTitle), $normItemTitle, $similarity);

            $candidates[] = [
                'item'       => $item,
                'itemTitle'  => $itemTitle,
                'score'      => $score,
                'similarity' => $similarity,
            ];
        }

        if (empty($candidates)) {
            Log::info("PostImageResolver RSS: sin coincidencia para '{$cleanTitle}' (entidades: " . implode(', ', array_column($entities, 'term')) . ")");
            return null;
        }

        // Ordenar: mayor puntuación primero; en empate, mayor similitud
        usort($candidates, fn ($a, $b) =>
            $b['score'] <=> $a['score'] ?: $b['similarity'] <=> $a['similarity']
        );

        $best = $candidates[0];
        $item = $best['item'];

        // --- Extraer imagen y URL del ítem ganador ---
        $imageUrl   = null;
        $articleUrl = null;

        $linkNode = $item->getElementsByTagName('link')->item(0);
        if ($linkNode) {
            $articleUrl = $linkNode->nodeValue;
        }

        $mediaNodes = $item->getElementsByTagNameNS('http://search.yahoo.com/mrss/', 'content');
        if ($mediaNodes->length > 0) {
            $imageUrl = $mediaNodes->item(0)->getAttribute('url');
        }

        if (!$imageUrl) {
            $enclosures = $item->getElementsByTagName('enclosure');
            if ($enclosures->length > 0) {
                foreach ($enclosures as $enclosure) {
                    $type = strtolower($enclosure->getAttribute('type'));
                    if (str_contains($type, 'image')) {
                        $imageUrl = $enclosure->getAttribute('url');
                        break;
                    }
                }
            }
        }

        if (!$imageUrl) {
            $descNode = $item->getElementsByTagName('description')->item(0);
            if ($descNode) {
                $desc = $descNode->nodeValue;
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $matches)) {
                    $imageUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
                }
            }
        }

        // Si el ítem no trae imagen pero sí URL, extraer og:image de la página
        if (!$imageUrl && $articleUrl) {
            $imageUrl = $this->resolveFromOgImage($articleUrl);
        }

        if ($imageUrl && $this->isValidImageDomain($imageUrl)) {
            Log::info(sprintf(
                "PostImageResolver RSS: ✅ Match '%s' → '%s' (score=%d, sim=%.1f%%) url=%s entidades=%s",
                $cleanTitle, $best['itemTitle'], $best['score'], $best['similarity'],
                $articleUrl, implode(', ', array_column($entities, 'term'))
            ));
            return [
                'url'         => $imageUrl,
                'article_url' => $articleUrl,
            ];
        }

        return null;
    }

    /**
     * Extrae entidades significativas del titular del post para el emparejamiento con feeds.
     *
     * Reglas:
     * - Palabras capitalizadas y frases entre comillas, normalizadas (minúsculas, sin acentos).
     * - Descarta palabras vacías en español y números de 4 dígitos.
     * - Frases multi-palabra valen 2 puntos; palabras sueltas valen 1 punto.
     *
     * @return array<int, array{term: string, score: int}>
     */
    private function extractEntities(string $title): array
    {
        static $stopwords = [
            'de','la','el','y','en','con','sin','para','un','una','los','las',
            'del','al','mas','hoy','septiembre','octubre','noviembre','diciembre',
            'enero','febrero','marzo','abril','mayo','junio','julio','agosto',
            'que','por','se','su','sus','le','les','nos','es','fue','son','era',
            'este','esta','estos','estas','ese','esa','esos','esas',
            'pero','como','sobre','hasta','desde','entre','durante',
            'nuevo','nueva','nuevos','nuevas','gran','grandes','todo','todos',
            'a','o','e','u','ni','si','no','ya',
        ];

        $entities = [];

        // 1. Frases entre comillas (alta confianza, 2 pts)
        if (preg_match_all('/[«»"""\'\'](.*?)[«»"""\'\']/u', $title, $m)) {
            foreach ($m[1] as $phrase) {
                $norm = TextNormalizer::normalizeSlug($phrase);
                if ($norm !== '' && !in_array($norm, $stopwords, true)) {
                    $entities[] = ['term' => $norm, 'score' => str_contains($norm, ' ') ? 2 : 1];
                }
            }
        }

        // 2. Palabras capitalizadas (secuencias de 1 o más palabras con inicial mayúscula)
        // Extraer secuencias de palabras capitalizadas (ej: "East Bay Ray", "Pink Floyd")
        preg_match_all('/(?:[A-ZÁÉÍÓÚÑÜ][a-záéíóúñü0-9]+(?:\s+[A-ZÁÉÍÓÚÑÜ][a-záéíóúñü0-9]+)*)/u', $title, $capMatches);

        foreach ($capMatches[0] as $phrase) {
            $norm = TextNormalizer::normalizeSlug($phrase);
            $words = explode(' ', $norm);

            // Filtrar palabras vacías de la secuencia
            $filtered = array_filter($words, fn ($w) =>
                $w !== '' && !in_array($w, $stopwords, true) && !preg_match('/^\d{4}$/', $w)
            );

            if (count($filtered) === 0) {
                continue;
            }

            $term = implode(' ', $filtered);

            // Evitar duplicados
            $already = array_column($entities, 'term');
            if (in_array($term, $already, true)) {
                continue;
            }

            $score = count($filtered) >= 2 ? 2 : 1;
            $entities[] = ['term' => $term, 'score' => $score];
        }

        return $entities;
    }

    private function resolveFromArtistCatalog(string $artistName): ?string
    {
        $normName = $this->normalizeArtistName($artistName);
        
        $artists = RadioArtist::whereNotNull('image_path')->get();
        
        foreach ($artists as $artist) {
            $dbName = $this->normalizeArtistName($artist->name);
            
            if ($dbName === $normName || str_contains($normName, $dbName) || str_contains($dbName, $normName)) {
                return rtrim(config('app.url'), '/') . '/' . ltrim($artist->image_path, '/');
            }
        }
        
        return null;
    }

    private function normalizeArtistName(string $name): string
    {
        $name = TextNormalizer::normalizeSlug($name);
        $name = preg_replace('/^the\s+/i', '', $name);
        return trim($name);
    }

    private function isValidImageDomain(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $lowerUrl = strtolower($url);
        if (str_contains($lowerUrl, 'brevo.net/mk/op') ||
            str_contains($lowerUrl, 'sendgrid.net/wf/open') ||
            str_contains($lowerUrl, 'tracker') ||
            str_contains($lowerUrl, 'analytics')) {
            return false;
        }
        return true;
    }
}
