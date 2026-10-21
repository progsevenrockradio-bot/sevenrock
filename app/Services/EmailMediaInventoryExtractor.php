<?php

declare(strict_types=1);

namespace App\Services;

class EmailMediaInventoryExtractor
{
    protected const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];
    protected const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', 'm4v'];
    protected const AUDIO_EXTENSIONS = ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'wma'];
    protected const DOC_EXTENSIONS   = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'];

    protected const MUSIC_DOMAINS = [
        'spotify.com',
        'open.spotify.com',
        'bandcamp.com',
        'music.apple.com',
        'soundcloud.com',
        'deezer.com',
        'tidal.com',
        'music.youtube.com',
        'amazon.com/music',
        'audiomack.com',
    ];

    protected const VIDEO_DOMAINS = [
        'youtube.com',
        'youtu.be',
        'vimeo.com',
        'dailymotion.com',
        'tiktok.com',
    ];

    /**
     * Extrae el inventario multimedia completo a partir del cuerpo y de los adjuntos conocidos.
     *
     * @param string $htmlOrText Cuerpo del correo o del post
     * @param array|null $attachments Lista de adjuntos (nombre o array con 'filename'/'name'/'ext')
     * @return array
     */
    public function extract(string $htmlOrText, ?array $attachments = null): array
    {
        // 1. Contadores de adjuntos (null si no hay información de adjuntos)
        $attachedPhotos = null;
        $attachedVideos = null;
        $attachedMp3s   = null;
        $attachedDocs   = null;

        if ($attachments !== null) {
            $attachedPhotos = 0;
            $attachedVideos = 0;
            $attachedMp3s   = 0;
            $attachedDocs   = 0;

            foreach ($attachments as $att) {
                $filename = is_array($att) ? ($att['filename'] ?? $att['name'] ?? '') : (string) $att;
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, self::PHOTO_EXTENSIONS, true)) {
                    $attachedPhotos++;
                } elseif (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                    $attachedVideos++;
                } elseif (in_array($ext, self::AUDIO_EXTENSIONS, true)) {
                    $attachedMp3s++;
                } elseif (in_array($ext, self::DOC_EXTENSIONS, true)) {
                    $attachedDocs++;
                }
            }
        }

        // 2. Extracción de enlaces e imágenes enlazadas en el cuerpo
        $linkedMedia = $this->extractFromHtml($htmlOrText);

        $hasReserved = ($attachedMp3s !== null && $attachedMp3s > 0)
            || ($attachedDocs !== null && $attachedDocs > 0)
            || !empty($linkedMedia['reserved_links']);

        return [
            'attached_photos_count' => $attachedPhotos,
            'linked_photos_count'   => $linkedMedia['linked_photos_count'],
            'attached_videos_count' => $attachedVideos,
            'linked_videos_count'   => $linkedMedia['linked_videos_count'],
            'attached_mp3s_count'   => $attachedMp3s,
            'linked_music_count'    => $linkedMedia['linked_music_count'],
            'attached_docs_count'   => $attachedDocs,
            'linked_docs_count'     => $linkedMedia['linked_docs_count'],
            'public_audio_links'    => $linkedMedia['public_audio_links'],
            'public_video_links'    => $linkedMedia['public_video_links'],
            'has_reserved_materials'=> $hasReserved,
            'inventory_status'      => ($attachments !== null) ? 'complete' : 'links_only',
        ];
    }

    /**
     * Resuelve el inventario efectivo. Si en BD ya existía, lo respeta; si falta,
     * analiza el contenido dinámicamente mostrando adjuntos como null (no disponibles).
     */
    public function getEffectiveInventory(?array $savedInventory, string $contentHtmlOrText): array
    {
        $default = $this->extract($contentHtmlOrText, null);
        if ($savedInventory !== null && !empty($savedInventory)) {
            return array_merge($default, $savedInventory);
        }

        return $default;
    }

    /**
     * Analiza el HTML para extraer enlaces categorizados e imágenes enlazadas.
     */
    protected function extractFromHtml(string $html): array
    {
        $linkedPhotos = 0;
        $linkedVideos = 0;
        $linkedMusic  = 0;
        $linkedDocs   = 0;
        $publicAudioLinks = [];
        $publicVideoLinks = [];
        $reservedLinks = [];

        if (trim($html) === '') {
            return [
                'linked_photos_count' => 0,
                'linked_videos_count' => 0,
                'linked_music_count'  => 0,
                'linked_docs_count'   => 0,
                'public_audio_links'  => [],
                'public_video_links'  => [],
                'reserved_links'      => [],
            ];
        }

        // Extraer imágenes <img> (descartando píxeles 1x1)
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $imgMatches);
        $seenImages = [];
        foreach ($imgMatches[1] ?? [] as $src) {
            $src = trim($src);
            if ($src === '' || isset($seenImages[$src])) {
                continue;
            }
            // Ignorar píxeles de seguimiento evidentes
            if (preg_match('/(?:tracking|pixel|beacon|\b1x1\b|open\.php)/i', $src)) {
                continue;
            }
            $seenImages[$src] = true;
            $linkedPhotos++;
        }

        // Extraer enlaces <a href="..."> y URLs en texto
        preg_match_all('/https?:\/\/[^\s"\'<>]+/i', $html, $urlMatches);
        $seenUrls = [];

        foreach ($urlMatches[0] ?? [] as $rawUrl) {
            $url = rtrim($rawUrl, '.,;:)');
            if (isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            $parsedHost = strtolower((string) parse_url($url, PHP_URL_HOST));
            $path = strtolower((string) parse_url($url, PHP_URL_PATH));
            $ext = pathinfo($path, PATHINFO_EXTENSION);

            // Verificar si es imagen enlazada
            if (in_array($ext, self::PHOTO_EXTENSIONS, true) && !isset($seenImages[$url])) {
                $linkedPhotos++;
                continue;
            }

            // Verificar si es documento enlazado
            if (in_array($ext, self::DOC_EXTENSIONS, true)) {
                $linkedDocs++;
                continue;
            }

            // Verificar si es plataforma de vídeo
            $isVideo = false;
            foreach (self::VIDEO_DOMAINS as $vDomain) {
                if (str_ends_with($parsedHost, $vDomain)) {
                    $isVideo = true;
                    $linkedVideos++;
                    $publicVideoLinks[] = [
                        'platform' => $this->detectVideoPlatform($parsedHost),
                        'url'      => $url,
                        'label'    => 'Vídeo en ' . ucfirst($this->detectVideoPlatform($parsedHost)),
                    ];
                    break;
                }
            }
            if ($isVideo) {
                continue;
            }

            // Verificar si es plataforma de música pública
            $isMusic = false;
            foreach (self::MUSIC_DOMAINS as $mDomain) {
                if (str_ends_with($parsedHost, $mDomain)) {
                    $isMusic = true;
                    $linkedMusic++;
                    $publicAudioLinks[] = [
                        'platform' => $this->detectMusicPlatform($parsedHost),
                        'url'      => $url,
                        'label'    => 'Música en ' . ucfirst($this->detectMusicPlatform($parsedHost)),
                    ];
                    break;
                }
            }
            if ($isMusic) {
                continue;
            }

            // Verificar si es plataforma EPK / almacenamiento privado
            if (preg_match('/(?:haulix|promojukebox|disco\.ac|ipool|wetransfer|dropbox|drive\.google)/i', $parsedHost)) {
                $reservedLinks[] = $url;
            }
        }

        return [
            'linked_photos_count' => $linkedPhotos,
            'linked_videos_count' => $linkedVideos,
            'linked_music_count'  => $linkedMusic,
            'linked_docs_count'   => $linkedDocs,
            'public_audio_links'  => $publicAudioLinks,
            'public_video_links'  => $publicVideoLinks,
            'reserved_links'      => $reservedLinks,
        ];
    }

    protected function detectMusicPlatform(string $host): string
    {
        if (str_contains($host, 'spotify')) return 'spotify';
        if (str_contains($host, 'bandcamp')) return 'bandcamp';
        if (str_contains($host, 'apple')) return 'apple_music';
        if (str_contains($host, 'soundcloud')) return 'soundcloud';
        if (str_contains($host, 'deezer')) return 'deezer';
        if (str_contains($host, 'tidal')) return 'tidal';
        if (str_contains($host, 'youtube')) return 'youtube_music';
        return 'audio';
    }

    protected function detectVideoPlatform(string $host): string
    {
        if (str_contains($host, 'youtu')) return 'youtube';
        if (str_contains($host, 'vimeo')) return 'vimeo';
        if (str_contains($host, 'tiktok')) return 'tiktok';
        return 'video';
    }
}
