<?php

namespace App\Support;

class YouTubeUrl
{
    /**
     * Devuelve el ID de 11 caracteres solo si la URL es un video reproducible.
     * Devuelve null si es un canal, playlist, etc.
     */
    public static function videoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Si la URL es un canal, usuario o playlist explícito, la descartamos inmediatamente.
        if (preg_match('/(\/@|\/channel\/|\/user\/|\/c\/|playlist\?list=)/i', $url)) {
            return null;
        }

        // Buscar el ID del video con varias posibles estructuras
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts|live)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i';

        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }

        return null;
    }
}
