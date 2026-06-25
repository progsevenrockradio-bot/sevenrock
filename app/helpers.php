<?php

if (! function_exists('formatear_titulo')) {
    /**
     * Format a title with a white and red bicolor layout using a proportional split:
     * - 1 word: First half of characters white, second half red.
     * - 2 words: First word white, second word red.
     * - > 2 words: First ceil(N/2) words white, remaining words red.
     */
    function formatear_titulo(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');

        if ($texto === '') {
            return '';
        }

        $palabras = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalPalabras = count($palabras);

        if ($totalPalabras === 1) {
            $palabra = $palabras[0];
            $len = mb_strlen($palabra, 'UTF-8');
            if ($len <= 1) {
                return '<span class="text-white">' . e($palabra) . '</span>';
            }
            $half = (int) ceil($len / 2);
            $part1 = mb_substr($palabra, 0, $half, 'UTF-8');
            $part2 = mb_substr($palabra, $half, null, 'UTF-8');
            return '<span class="text-white">' . e($part1) . '</span><span class="text-lucille-accent">' . e($part2) . '</span>';
        }

        if ($totalPalabras === 2) {
            return '<span class="text-white">' . e($palabras[0]) . '</span> <span class="text-lucille-accent">' . e($palabras[1]) . '</span>';
        }

        $half = (int) ceil($totalPalabras / 2);
        $part1 = array_slice($palabras, 0, $half);
        $part2 = array_slice($palabras, $half);

        $str1 = implode(' ', $part1);
        $str2 = implode(' ', $part2);

        return '<span class="text-white">' . e($str1) . '</span> <span class="text-lucille-accent">' . e($str2) . '</span>';
    }
}

if (! function_exists('formatear_titulo_hover')) {
    /**
     * Format a title for hover-enabled white/red bicolor styling:
     * - Colors are only activated on hover of a parent element containing the "group" class.
     */
    function formatear_titulo_hover(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($texto)) ?? '');

        if ($texto === '') {
            return '';
        }

        $palabras = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $totalPalabras = count($palabras);

        if ($totalPalabras === 1) {
            $palabra = $palabras[0];
            $len = mb_strlen($palabra, 'UTF-8');
            if ($len <= 1) {
                return '<span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-white">' . e($palabra) . '</span>';
            }
            $half = (int) ceil($len / 2);
            $part1 = mb_substr($palabra, 0, $half, 'UTF-8');
            $part2 = mb_substr($palabra, $half, null, 'UTF-8');
            return '<span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-white">' . e($part1) . '</span><span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-lucille-accent">' . e($part2) . '</span>';
        }

        if ($totalPalabras === 2) {
            return '<span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-white">' . e($palabras[0]) . '</span> <span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-lucille-accent">' . e($palabras[1]) . '</span>';
        }

        $half = (int) ceil($totalPalabras / 2);
        $part1 = array_slice($palabras, 0, $half);
        $part2 = array_slice($palabras, $half);

        $str1 = implode(' ', $part1);
        $str2 = implode(' ', $part2);

        return '<span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-white">' . e($str1) . '</span> <span class="text-[#dcdcdc] transition-colors duration-300 group-hover:text-lucille-accent">' . e($str2) . '</span>';
    }
}

foreach ([
    App\Http\Controllers\SearchController::class => 'SearchController',
    App\Http\Controllers\CommentController::class => 'CommentController',
    App\Http\Controllers\Admin\UserController::class => 'UserController',
] as $targetClass => $legacyClass) {
    if (class_exists($targetClass) && ! class_exists($legacyClass, false)) {
        class_alias($targetClass, $legacyClass);
    }
}
