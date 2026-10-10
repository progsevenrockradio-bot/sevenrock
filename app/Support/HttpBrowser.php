<?php

namespace App\Support;

class HttpBrowser
{
    public const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Devuelve las opciones base de HTTP para simular un navegador y usar el cacert.pem local
     * para asegurar la verificación SSL.
     */
    public static function defaultOptions(): array
    {
        return [
            'verify' => storage_path('app/cacert.pem'),
            'allow_redirects' => true,
        ];
    }

    /**
     * Devuelve las cabeceras estándar para simular un navegador.
     */
    public static function defaultHeaders(): array
    {
        return [
            'User-Agent' => self::BROWSER_UA,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
        ];
    }
}
