<?php

namespace App\Support;

class TextList
{
    /**
     * Convierte texto plano (o un array) en una lista de párrafos limpia.
     * Siempre devuelve un array de strings.
     */
    public static function toArray(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return self::split((string) $value);
    }

    /**
     * Convierte un array (o un texto plano) en un solo string,
     * uniendo los párrafos con saltos de línea dobles.
     */
    public static function toString(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            return implode("\n\n", array_values(array_filter(array_map('trim', $value))));
        }

        return (string) $value;
    }

    /**
     * Divide un texto en párrafos.
     * Si tiene líneas en blanco (doble salto), parte por ahí.
     * Si no tiene líneas en blanco pero sí saltos simples, parte por saltos simples.
     */
    public static function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Si hay dobles saltos de línea (párrafos reales)
        if (preg_match('/\R{2,}/', $text)) {
            $parts = preg_split('/\R{2,}/', $text);
        } else {
            // Si no hay dobles saltos, pero sí saltos simples, los tratamos como párrafos
            $parts = preg_split('/\R/', $text);
        }

        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                // Normaliza espacios internos
                $result[] = preg_replace('/\s+/u', ' ', $part);
            }
        }

        return $result;
    }
}
