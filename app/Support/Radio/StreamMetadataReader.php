<?php

declare(strict_types=1);

namespace App\Support\Radio;

use Illuminate\Support\Facades\Cache;

class StreamMetadataReader
{
    private const DEFAULT_TIMEOUT = 2;
    private const CACHE_TTL_SECONDS = 5;

    /**
     * Lee los metadatos ICY directamente del stream de audio activo.
     * Se apoya en una breve caché en memoria para no saturar sockets ni ralentizar peticiones concurrentes.
     *
     * @return array{
     *     raw: string,
     *     title: string,
     *     artist: string,
     *     success: bool
     * }
     */
    public function read(bool $forceRefresh = false, ?string $hintTitle = null, ?string $hintArtist = null): array
    {
        $directUrl = trim((string) config('player.streams.direct', ''));
        $altUrl = trim((string) config('player.streams.alt_direct', ''));

        $cacheKey = 'radio.stream_icy.' . md5($directUrl . '|' . $altUrl);

        if (! $forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ! empty($cached['success'])) {
                return $this->refineWithHints($cached, $hintTitle, $hintArtist);
            }
        }

        $result = [];
        if ($directUrl !== '') {
            $result = $this->fetchIcy($directUrl);
        }

        if (empty($result['success']) && $altUrl !== '' && $altUrl !== $directUrl) {
            $result = $this->fetchIcy($altUrl);
        }

        if (empty($result['success'])) {
            $result = [
                'raw' => '',
                'title' => '',
                'artist' => '',
                'success' => false,
            ];
        } else {
            Cache::put($cacheKey, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $this->refineWithHints($result, $hintTitle, $hintArtist);
    }

    /**
     * Conexión socket/stream HTTP para leer el bloque ICY (icy-metaint).
     */
    private function fetchIcy(string $url): array
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "Icy-MetaData: 1\r\nUser-Agent: SevenRockRadio-Reader/1.0\r\n",
                'timeout' => self::DEFAULT_TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        $ctx = stream_context_create($opts);
        $fp = @fopen($url, 'r', false, $ctx);
        if (! $fp) {
            return [];
        }

        try {
            stream_set_timeout($fp, self::DEFAULT_TIMEOUT);
            $meta = stream_get_meta_data($fp);
            $metaint = 0;

            if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
                foreach ($meta['wrapper_data'] as $h) {
                    if (stripos($h, 'icy-metaint:') === 0) {
                        $metaint = (int) trim(substr($h, 12));
                        break;
                    }
                }
            }

            if ($metaint <= 0) {
                return [];
            }

            $read = 0;
            while ($read < $metaint && ! feof($fp)) {
                $chunk = fread($fp, min(8192, $metaint - $read));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $read += strlen($chunk);
            }

            $lenByte = fread($fp, 1);
            if ($lenByte === false || strlen($lenByte) === 0) {
                return [];
            }

            $len = ord($lenByte) * 16;
            $rawTitle = '';
            if ($len > 0) {
                $rawMeta = fread($fp, $len);
                if (preg_match("/StreamTitle='(.*?)';/s", (string) $rawMeta, $m) || preg_match("/StreamTitle='(.*?)'/s", (string) $rawMeta, $m)) {
                    $rawTitle = trim($m[1]);
                }
            }

            if ($rawTitle === '') {
                return [];
            }

            [$title, $artist] = $this->splitStreamTitle($rawTitle);

            return [
                'raw' => $rawTitle,
                'title' => $title,
                'artist' => $artist,
                'success' => ($title !== '' || $artist !== ''),
            ];
        } catch (\Throwable) {
            return [];
        } finally {
            @fclose($fp);
        }
    }

    /**
     * Divide la cadena en título y artista según el servidor activo.
     * En RadioBOSS (nuestra emisora principal) el formato de emisión es %title - %artist.
     * En Shoutcast / EXTASSIS el estándar suele ser %artist - %title.
     *
     * @return array{0:string, 1:string}
     */
    public function splitStreamTitle(string $rawTitle): array
    {
        $rawTitle = trim($rawTitle);
        if ($rawTitle === '') {
            return ['', ''];
        }

        $activeServer = strtolower(trim((string) config('player.active_server', 'radioboss')));

        foreach ([' - ', ' – ', ' — ', ' | '] as $sep) {
            $pos = strpos($rawTitle, $sep);
            if ($pos !== false && $pos > 0) {
                $part0 = trim(substr($rawTitle, 0, $pos));
                $part1 = trim(substr($rawTitle, $pos + strlen($sep)));

                if ($part0 !== '' && $part1 !== '') {
                    if ($activeServer === 'extassis') {
                        // Shoutcast: Artista - Título
                        return [$part1, $part0];
                    }
                    // RadioBOSS: Título - Artista
                    return [$part0, $part1];
                }
            }
        }

        return [$rawTitle, ''];
    }

    /**
     * Si disponemos de pistas externas (de la API o el estado guardado), comprobamos
     * si invierten el orden título/artista para garantizar concordancia al 100%.
     *
     * @param array<string, mixed> $result
     * @return array{raw: string, title: string, artist: string, success: bool}
     */
    private function refineWithHints(array $result, ?string $hintTitle, ?string $hintArtist): array
    {
        if (empty($result['raw']) || empty($result['title']) || empty($result['artist'])) {
            return $result;
        }

        $hintTitle = trim((string) $hintTitle);
        $hintArtist = trim((string) $hintArtist);

        if ($hintTitle === '' && $hintArtist === '') {
            return $result;
        }

        $tLower = mb_strtolower($result['title']);
        $aLower = mb_strtolower($result['artist']);
        $htLower = mb_strtolower($hintTitle);
        $haLower = mb_strtolower($hintArtist);

        // Si el título inferido coincide con el artista de la pista, y el artista inferido coincide con el título, invertimos
        if ($htLower !== '' && $haLower !== '') {
            if ($tLower === $haLower && $aLower === $htLower) {
                return [
                    'raw' => $result['raw'],
                    'title' => $result['artist'],
                    'artist' => $result['title'],
                    'success' => true,
                ];
            }
        } elseif ($haLower !== '' && $tLower === $haLower) {
            return [
                'raw' => $result['raw'],
                'title' => $result['artist'],
                'artist' => $result['title'],
                'success' => true,
            ];
        } elseif ($htLower !== '' && $aLower === $htLower) {
            return [
                'raw' => $result['raw'],
                'title' => $result['artist'],
                'artist' => $result['title'],
                'success' => true,
            ];
        }

        return $result;
    }
}
