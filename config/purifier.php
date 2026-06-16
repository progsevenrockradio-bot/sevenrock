<?php

/**
 * Configuración de HTMLPurifier para Seven Rock Radio
 *
 * Perfiles disponibles:
 *  - 'default'  → Posts de blog: permite HTML enriquecido, iframes de YouTube/Spotify/Vimeo
 *  - 'strict'   → Comentarios de usuarios: solo texto, formato básico, sin iframes ni scripts
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding'         => 'UTF-8',
    'finalize'         => true,
    'ignoreNonStrings' => false,
    'cachePath'        => storage_path('app/purifier'),
    'cacheFileMode'    => 0755,

    'settings' => [

        // ── Perfil para contenido de posts (admin) ──────────────────────────
        // Permite HTML enriquecido: headings, listas, figuras, iframes de confianza.
        // Se permiten iframes SOLO de dominios verificados (YouTube, Vimeo, Spotify).
        'default' => [
            'HTML.Doctype'    => 'HTML 4.01 Transitional',
            'HTML.Allowed'    => implode(',', [
                'p[style|class]',
                'br',
                'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'sub', 'sup',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li',
                'blockquote[cite]', 'cite',
                'a[href|title|target|rel]',
                'img[src|alt|width|height|loading|class]',
                'figure[class]', 'figcaption',
                'div[class]', 'span[class|style]',
                'table[class]', 'thead', 'tbody', 'tr', 'th[scope]', 'td[colspan|rowspan]',
                'code', 'pre', 'kbd', 'samp',
                'iframe[src|width|height|allowfullscreen|frameborder|allow|title|class|style]',
                'video[src|controls|width|height|poster|preload|class]',
                'source[src|type]',
                'hr',
            ]),
            'CSS.AllowedProperties' => implode(',', [
                'font-size', 'font-weight', 'font-style', 'font-family',
                'color', 'background-color', 'text-align', 'text-decoration',
                'padding', 'padding-left', 'margin', 'margin-top', 'margin-bottom',
                'width', 'max-width', 'height', 'aspect-ratio',
                'display', 'float', 'clear',
            ]),
            // Iframes permitidos: YouTube, Vimeo, Spotify, archive.org
            'HTML.SafeIframe'      => true,
            'URI.SafeIframeRegexp' => '%^https://(www\.youtube\.com/embed/|player\.vimeo\.com/video/|open\.spotify\.com/embed/|embed\.spotify\.com/|www\.youtube-nocookie\.com/embed/|archive\.org/embed/)%',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => true,
            'Attr.AllowedFrameTargets' => ['_blank', '_self'],
        ],

        // ── Perfil estricto para comentarios públicos ────────────────────────
        // Usuarios no autenticados — solo formato básico de texto, sin iframes.
        'strict' => [
            'HTML.Doctype'    => 'HTML 4.01 Transitional',
            'HTML.Allowed'    => implode(',', [
                'p', 'br',
                'strong', 'b', 'em', 'i',
                'ul', 'ol', 'li',
                'blockquote', 'a[href|title]',
                'code', 'pre',
            ]),
            'CSS.AllowedProperties'    => '',
            'HTML.SafeIframe'          => false,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty'   => true,
        ],

    ],
];
