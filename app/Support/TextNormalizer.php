<?php

namespace App\Support;

class TextNormalizer
{
    /**
     * Normaliza un titular (minúsculas, quita prefijos, colapsa espacios).
     */
    public static function normalizeTitle(string $title): string
    {
        $title = preg_replace('/^hoy en el rock:\s*/ui', '', $title);
        $title = preg_replace('/^noticia:\s*/ui', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    /**
     * Normaliza un slug o texto para comparación (minúsculas, sin acentos ni signos).
     */
    public static function normalizeSlug(string $text): string
    {
        $text = mb_strtolower($text);
        
        // Reemplazar caracteres acentuados
        $text = preg_replace('/[áàäâã]/u', 'a', $text);
        $text = preg_replace('/[éèëê]/u', 'e', $text);
        $text = preg_replace('/[íìïî]/u', 'i', $text);
        $text = preg_replace('/[óòöôõ]/u', 'o', $text);
        $text = preg_replace('/[úùüû]/u', 'u', $text);
        $text = preg_replace('/[ñ]/u', 'n', $text);
        
        // Quitar cualquier cosa que no sea letra, número o espacio
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Colapsar espacios
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Devuelve la similitud entre dos strings (0 a 1).
     */
    public static function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);
        
        return $percent / 100.0;
    }
}
