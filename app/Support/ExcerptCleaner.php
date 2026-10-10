<?php

namespace App\Support;

use Illuminate\Support\Str;

class ExcerptCleaner
{
    /**
     * Limpia un excerpt que pueda contener un JSON de bloques de contenido,
     * convirtiéndolo en un texto legible y truncado.
     */
    public static function clean(?string $excerpt, int $limit = 200): string
    {
        if (empty($excerpt)) {
            return '';
        }

        $text = $excerpt;

        // Comprobar si es un JSON
        if (str_starts_with(trim($excerpt), '[')) {
            $decoded = json_decode($excerpt, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Es un array de bloques
                $blocks = array_map('trim', $decoded);
                // Filtrar bloques vacíos o muy cortos (ej. ",")
                $blocks = array_filter($blocks, fn($block) => strlen($block) > 2);
                $text = implode(' ', $blocks);
            }
        }

        // Limpiar el texto de comillas sueltas y corchetes, \r\n, etc.
        $text = strip_tags($text);
        
        // Quitar saltos de línea repetidos
        $text = preg_replace('/[\r\n]+/', ' ', $text);
        
        // Colapsar espacios extra
        $text = preg_replace('/\s+/', ' ', $text);

        // Si era JSON mal formado que el json_decode no procesó, limpiar símbolos crudos del JSON.
        // Pero sólo si sospechamos que era JSON crudo.
        if (str_starts_with(trim($excerpt), '[')) {
            $text = str_replace(['["', '"]', '","', '", "', '\"', ' \r\n', '\r\n'], ' ', $text);
            $text = preg_replace('/\s+/', ' ', $text);
        }

        $text = trim($text);

        // Truncar sin partir palabras
        return Str::limit($text, $limit, '...');
    }
}
