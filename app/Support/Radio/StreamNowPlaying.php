<?php

declare(strict_types=1);

namespace App\Support\Radio;

use App\Support\ExternalHttp;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * "Suena ahora" leido del SERVIDOR DE EMISION ACTIVO (config('player.active_server')).
 *
 * ============================================================================
 *  REGLA DE ORO: si la emisora activa es RadioBOSS, esta clase NO toca nada.
 *  Devuelve un array vacio y RadioBOSS sigue funcionando con su cascada de
 *  siempre (RadioBossService, webhook, widget, etc.). Todo queda como estaba.
 * ============================================================================
 *
 *  Cuando la emisora activa es otra (EXTASSIS, o una futura), aqui se lee su
 *  "suena ahora" y se devuelve con las MISMAS claves que ya usa el reproductor
 *  (title, artist, album, cover, listeners), asi que la caratula y el titulo de
 *  toda la web (que ya se pintan con track.title / track.cover) se actualizan
 *  solos, sin tocar ni una linea de las vistas.
 *
 *  Soporta los dos formatos de estadisticas que usan estos servidores:
 *    - ShoutCast DNAS v2:  /stats?sid=1&json=1     -> streamstatus, songtitle, currentlisteners
 *    - Icecast / RadioBOSS: /status-json.xsl       -> icestats.source[].title
 */
final class StreamNowPlaying
{
    /**
     * Estado de "suena ahora" de la emisora activa.
     * Array vacio = no hay nada que aportar (RadioBOSS activo, o sin datos).
     *
     * @return array{title?:string,artist?:string,album?:string,cover?:string,listeners?:int}
     */
    public static function state(): array
    {
        $activo = strtolower(trim((string) config('player.active_server', 'radioboss')));

        // RadioBOSS manda: no se toca nada de lo que ya funciona.
        if ($activo === '' || $activo === 'radioboss') {
            return [];
        }

        $statsUrl = trim((string) Arr::get((array) config('player.servers', []), "{$activo}.stats", ''));
        if ($statsUrl === '') {
            return [];
        }

        $segundos = max(5, (int) config('player.poll_interval', 10));

        $estado = Cache::remember("radio.now_playing.{$activo}", $segundos, static function () use ($statsUrl): array {
            return self::fetch($statsUrl);
        });

        return is_array($estado) ? $estado : [];
    }

    /**
     * Lee el endpoint de estadisticas y lo normaliza. Nunca lanza excepciones.
     *
     * @return array<string,mixed>
     */
    public static function fetch(string $statsUrl): array
    {
        try {
            $respuesta = ExternalHttp::client()
                ->connectTimeout(2)
                ->timeout(4)
                ->withHeaders(['Accept' => 'application/json, text/plain, */*'])
                ->get($statsUrl);

            if (! $respuesta->successful()) {
                return [];
            }

            $json = $respuesta->json();

            return is_array($json) ? self::parse($json) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Reconoce el formato y devuelve el estado normalizado. Metodo puro (sin red).
     *
     * @param  array<string,mixed>  $json
     * @return array<string,mixed>
     */
    public static function parse(array $json): array
    {
        if (isset($json['icestats'])) {
            return self::parseIcecast($json);
        }

        if (array_key_exists('streamstatus', $json) || array_key_exists('songtitle', $json)) {
            return self::parseShoutcast($json);
        }

        return [];
    }

    /**
     * Icecast / RadioBOSS (status-json.xsl). En RadioBOSS, icestats.source es una LISTA
     * con una entrada util (la que trae titulo) y otra "dummy" por cada punto de escucha.
     *
     * @param  array<string,mixed>  $json
     * @return array<string,mixed>
     */
    private static function parseIcecast(array $json): array
    {
        $fuente = Arr::get($json, 'icestats.source', []);
        if (is_array($fuente) && ! Arr::isAssoc($fuente)) {
            $elegida = [];
            foreach ($fuente as $entrada) {
                if (! is_array($entrada)) {
                    continue;
                }
                if (trim((string) Arr::get($entrada, 'title', '')) !== ''
                    || trim((string) Arr::get($entrada, 'yp_currently_playing', '')) !== '') {
                    $elegida = $entrada;
                    break;
                }
            }
            $fuente = $elegida;
        }
        $fuente = is_array($fuente) ? $fuente : [];

        $linea = self::firstFilled([
            Arr::get($fuente, 'title'),
            Arr::get($fuente, 'yp_currently_playing'),
        ]);

        [$artista, $titulo] = self::splitLine($linea);

        return array_filter([
            'title' => $titulo !== '' ? $titulo : $linea,
            'artist' => $artista,
            'album' => '',
            'cover' => '',
            'listeners' => self::enteroODefecto(Arr::get($fuente, 'listeners')),
            'program_name' => (string) Arr::get($fuente, 'server_name', ''),
        ], static fn ($valor): bool => $valor !== '' && $valor !== null);
    }

    /**
     * ShoutCast DNAS v2 (stats?sid=1&json=1). Si streamstatus es 0 no hay emisor:
     * devolvemos vacio a proposito, para no pintar una cancion que no suena.
     *
     * @param  array<string,mixed>  $json
     * @return array<string,mixed>
     */
    private static function parseShoutcast(array $json): array
    {
        $estado = Arr::get($json, 'streamstatus');

        $linea = self::firstFilled([
            Arr::get($json, 'songtitle'),
            Arr::get($json, 'servertitle'),
        ]);

        if ($linea === '') {
            return [];
        }

        if ($estado !== null && (int) $estado === 0) {
            return [];
        }

        [$artista, $titulo] = self::splitLine($linea);

        return array_filter([
            'title' => $titulo !== '' ? $titulo : $linea,
            'artist' => $artista,
            'album' => '',
            // ShoutCast no da caratula: se deja vacio a proposito para que el reproductor
            // siga usando la suya (caratula de la cancion en nuestra base, o la de reserva).
            'cover' => '',
            'listeners' => self::enteroODefecto(Arr::get($json, 'currentlisteners')),
            'program_name' => (string) Arr::get($json, 'servertitle', ''),
        ], static fn ($valor): bool => $valor !== '' && $valor !== null);
    }

    /**
     * "Artista - Titulo" -> [artista, titulo]. Si no hay separador, todo es titulo.
     *
     * @return array{0:string,1:string}
     */
    public static function splitLine(string $linea): array
    {
        $linea = trim($linea);
        if ($linea === '') {
            return ['', ''];
        }

        foreach ([' - ', ' – ', ' — ', ' | '] as $separador) {
            // strpos/substr en bytes: al cortar justo en los limites del separador (que es UTF-8
            // completo) no se parte ningun caracter, y asi no depende de la extension mbstring.
            $posicion = strpos($linea, $separador);
            if ($posicion !== false && $posicion > 0) {
                $artista = trim(substr($linea, 0, $posicion));
                $titulo = trim(substr($linea, $posicion + strlen($separador)));

                if ($artista !== '' && $titulo !== '') {
                    return [$artista, $titulo];
                }
            }
        }

        return ['', $linea];
    }

    /**
     * @param  mixed  $valor
     */
    private static function firstFilled(mixed $valor): string
    {
        foreach (is_array($valor) ? $valor : [$valor] as $candidato) {
            $texto = trim((string) $candidato);
            if ($texto !== '') {
                return $texto;
            }
        }

        return '';
    }

    /**
     * @param  mixed  $valor
     */
    private static function enteroODefecto(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : max(0, (int) $valor);
    }
}
