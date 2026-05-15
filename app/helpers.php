<?php

if (! function_exists('formatear_titulo')) {
    /**
     * Format a title so the leading words stay white and the last word turns red.
     *
     * This mirrors the Lucille / Seven Rock heading treatment:
     * - "Upcoming Shows" => Upcoming (white) / Shows (red)
     * - "Send us a Message" => Send us a (white) / Message (red)
     * - one word => white
     */
    function formatear_titulo(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');

        if ($texto === '') {
            return '';
        }

        $palabras = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palabras === []) {
            return '';
        }

        if (count($palabras) === 1) {
            return '<span class="text-white">' . e($palabras[0]) . '</span>';
        }

        $accent = array_pop($palabras);
        $base = implode(' ', $palabras);

        return '<span class="text-white">' . e($base) . '</span> <span class="text-lucille-accent">' . e($accent) . '</span>';
    }
}
