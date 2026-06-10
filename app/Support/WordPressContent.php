<?php

namespace App\Support;

final class WordPressContent
{
    /**
     * @param mixed $content
     * @return array<int, array<string, string>>
     */
    public static function toEditorBlocks(mixed $content): array
    {
        return array_values(array_filter(array_map(
            fn ($block) => self::normalizeEditorBlock($block),
            self::normalizeToArray($content)
        )));
    }

    /**
     * @param mixed $content
     * @return array<int, string>
     */
    public static function toRenderableBlocks(mixed $content): array
    {
        return array_values(array_filter(array_map(
            fn ($block) => self::serializeBlock($block),
            self::normalizeToArray($content)
        )));
    }

    /**
     * @param mixed $content
     * @return array<int, mixed>
     */
    private static function normalizeToArray(mixed $content): array
    {
        if (is_array($content)) {
            return $content;
        }

        if (is_object($content)) {
            return (array) $content;
        }

        if (! is_string($content)) {
            return [];
        }

        $content = trim($content);

        if ($content === '') {
            return [];
        }

        return preg_split('/\R{2,}/', $content) ?: [];
    }

    /**
     * @param mixed $block
     * @return array<string, string>
     */
    private static function normalizeEditorBlock(mixed $block): array
    {
        $html = self::extractString($block);

        if ($html === '') {
            return [];
        }

        $html = self::stripWpComments($html);

        if ($html === '') {
            return [];
        }

        if (str_contains($html, 'wp-block-gallery')) {
            return [
                'type' => 'gallery',
                'items' => self::extractGalleryItems($html),
            ];
        }

        if (preg_match('/<figure[^>]*>.*?<img\b[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>.*<\/figure>/is', $html, $matches)) {
            return [
                'type' => 'image',
                'src' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
                'alt' => html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5),
            ];
        }

        if (preg_match('/<img\b[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/is', $html, $matches)) {
            return [
                'type' => 'image',
                'src' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5),
                'alt' => html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5),
            ];
        }

        if (preg_match('/<blockquote\b[^>]*>(.*?)<\/blockquote>/is', $html, $matches)) {
            return [
                'type' => 'quote',
                'value' => self::unwrapBlock($matches[1]),
                'cite' => self::extractBlockQuoteCite($html),
            ];
        }

        if (preg_match('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches)) {
            return [
                'type' => 'heading',
                'level' => (string) $matches[1],
                'value' => self::unwrapBlock($matches[2]),
            ];
        }

        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            return [
                'type' => 'paragraph',
                'value' => self::unwrapBlock($matches[1]),
            ];
        }

        return [
            'type' => 'raw',
            'value' => trim($html),
        ];
    }

    /**
     * @param mixed $block
     */
    private static function serializeBlock(mixed $block): string
    {
        if (is_string($block) || is_int($block) || is_float($block)) {
            $text = trim((string) $block);
            if ($text === '') {
                return '';
            }

            return self::wrapHtml($text);
        }

        if (is_object($block)) {
            return self::serializeBlock((array) $block);
        }

        if (! is_array($block)) {
            return '';
        }

        // Normalize: WordPress blocks use 'content' key instead of 'value'
        if (!isset($block['value'])) {
            if (isset($block['content']['html']) && is_string($block['content']['html'])) {
                $block['value'] = $block['content']['html'];
            } elseif (isset($block['content']) && is_string($block['content'])) {
                $block['value'] = $block['content'];
            }
        }

        $type = (string) ($block['type'] ?? 'raw');

        return match ($type) {
            'paragraph' => self::wrapHtml((string) ($block['value'] ?? ''), $block['links'] ?? []),
            'heading' => self::wrapHeading((string) ($block['value'] ?? ''), (int) ($block['level'] ?? 2)),
            'quote' => self::wrapQuote((string) ($block['value'] ?? ''), (string) ($block['cite'] ?? '')),
            'image' => self::wrapImage((string) ($block['src'] ?? ''), (string) ($block['alt'] ?? '')),
            'gallery' => self::wrapGallery(is_array($block['items'] ?? null) ? $block['items'] : []),
            default => self::wrapRaw((string) ($block['value'] ?? '')),
        };
    }

    private static function stripWpComments(string $html): string
    {
        $html = preg_replace('/<!--\s*wp:[^>]*-->/i', '', $html) ?? $html;
        $html = preg_replace('/<!--\s*\/wp:[^>]*-->/i', '', $html) ?? $html;

        return trim($html);
    }

    private static function unwrapBlock(string $html): string
    {
        $html = self::stripWpComments($html);
        $html = preg_replace('/^\s*<p[^>]*>|<\/p>\s*$/i', '', $html) ?? $html;
        $html = preg_replace('/^\s*<div[^>]*>|<\/div>\s*$/i', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return trim($html);
    }

    private static function wrapHtml(string $text, array $links = []): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Process inline links (word → URL replacements)
        if ($links !== []) {
            $placeholders = [];
            $replacements = [];
            $i = 0;
            $workingText = $text;

            foreach ($links as $link) {
                $word = trim((string) ($link['word'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));
                if ($word === '' || $url === '') {
                    continue;
                }
                $placeholder = "\x00LINK{$i}\x00";
                // Escape the word for regex and replace first occurrence
                $pattern = '/' . preg_quote($word, '/') . '/i';
                if (preg_match($pattern, $workingText, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1];
                    $workingText = substr_replace($workingText, $placeholder, $pos, strlen($matches[0][0]));
                    $placeholders[] = $placeholder;
                    $replacements[] = '<a href="' . e($url) . '">' . e($word) . '</a>';
                    $i++;
                }
            }

            if ($placeholders !== []) {
                // Escape the text, then replace placeholders with actual links
                $escaped = e($workingText);
                $escaped = str_replace($placeholders, $replacements, $escaped);
                return '<p>' . self::rewriteBareUrlsInHtml($escaped) . '</p>';
            }
        }

        if (preg_match('/<\s*(p|img|figure|blockquote|ul|ol|li|h[1-6]|div|section|article|br|iframe)\b/i', $text)) {
            return self::rewriteBareUrlsInHtml(
                PublicMediaUrl::rewriteLegacyWordPressUploadsInHtml(self::stripWpComments($text))
            );
        }

        return '<p>'.self::rewriteBareUrlsInText($text).'</p>';
    }

    private static function wrapHeading(string $text, int $level): string
    {
        $level = max(1, min(6, $level));
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return '<h'.$level.'>'.e($text).'</h'.$level.'>';
    }

    private static function wrapQuote(string $text, string $cite = ''): string
    {
        $text = trim($text);
        $cite = trim($cite);

        if ($text === '') {
            return '';
        }

        $html = '<blockquote><p>'.nl2br(e($text)).'</p>';
        if ($cite !== '') {
            $html .= '<cite>'.e($cite).'</cite>';
        }

        return $html.'</blockquote>';
    }

    private static function wrapImage(string $src, string $alt): string
    {
        $src = trim($src);
        $alt = trim($alt);

        if ($src === '') {
            return '';
        }

        $src = PublicMediaUrl::normalizePublicUrl($src) ?: $src;

        return '<figure class="wp-block-image"><img src="'.e($src).'" alt="'.e($alt).'"></figure>';
    }

    /**
     * @param array<int, mixed> $items
     */
    private static function wrapGallery(array $items): string
    {
        $images = [];

        foreach ($items as $item) {
            $src = trim((string) ($item['src'] ?? ''));
            if ($src === '') {
                continue;
            }

            $src = PublicMediaUrl::normalizePublicUrl($src) ?: $src;
            $alt = trim((string) ($item['alt'] ?? ''));
            $images[] = '<figure class="wp-block-image"><img src="'.e($src).'" alt="'.e($alt).'"></figure>';
        }

        if ($images === []) {
            return '';
        }

        return '<figure class="wp-block-gallery">'.implode('', $images).'</figure>';
    }

    /**
     * @return array<int, array{src:string,alt:string}>
     */
    private static function extractGalleryItems(string $html): array
    {
        preg_match_all('/<img\b[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/is', $html, $matches, PREG_SET_ORDER);

        return array_values(array_filter(array_map(static function (array $match): array {
            return [
                'src' => html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5),
                'alt' => html_entity_decode($match[2] ?? '', ENT_QUOTES | ENT_HTML5),
            ];
        }, $matches)));
    }

    private static function extractBlockQuoteCite(string $html): string
    {
        if (! preg_match('/<cite\b[^>]*>(.*?)<\/cite>/is', $html, $matches)) {
            return '';
        }

        return self::unwrapBlock($matches[1]);
    }

    private static function wrapRaw(string $html): string
    {
        $html = trim($html);

        return $html !== ''
            ? self::rewriteBareUrlsInHtml(
                PublicMediaUrl::rewriteLegacyWordPressUploadsInHtml(self::stripWpComments($html))
            )
            : '';
    }

    private static function rewriteBareUrlsInText(string $text): string
    {
        return nl2br(self::rewriteBareUrlsInHtml(e($text)));
    }

    private static function rewriteBareUrlsInHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $segments = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($segments === false) {
            return $html;
        }

        $output = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (str_starts_with($segment, '<')) {
                $output .= $segment;
                continue;
            }

            $output .= self::rewriteBareUrlsInTextSegment($segment);
        }

        return $output;
    }

    private static function rewriteBareUrlsInTextSegment(string $text): string
    {
        $pattern = '/\bhttps?:\/\/[^\s<>"\'`]+/i';

        return preg_replace_callback($pattern, static function (array $matches): string {
            $url = (string) ($matches[0] ?? '');

            if ($url === '') {
                return '';
            }

            [$cleanUrl, $trailing] = self::splitTrailingPunctuation($url);

            return self::renderUrlCard($cleanUrl).$trailing;
        }, $text) ?? $text;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitTrailingPunctuation(string $url): array
    {
        $trailing = '';

        while ($url !== '') {
            $lastChar = substr($url, -1);

            if (! in_array($lastChar, ['.', ',', ';', ':', '!', '?'], true)) {
                break;
            }

            $trailing = $lastChar.$trailing;
            $url = substr($url, 0, -1);
        }

        return [$url, $trailing];
    }

    private static function renderUrlCard(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $domainLabel = $host !== '' ? $host : 'sitio web';

        return match (true) {
            str_contains($host, 'spotify.com') => self::renderLinkButton(
                $url,
                '🎵 Escuchar en Spotify',
                'border-[#ff4d5e]/35 bg-[#2a0b0f]/85 text-[#ffecec] hover:border-[#ff6675]/55 hover:bg-[#3a0f15]/92 w-full md:w-max'
            ),
            str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => self::renderLinkButton(
                $url,
                '📺 Ver en YouTube',
                'border-[#ff5a67]/32 bg-[#23090d]/88 text-[#fff3f3] hover:border-[#ff7a86]/58 hover:bg-[#330d12]/95 w-full md:w-max'
            ),
            str_contains($host, 'instagram.com') => self::renderLinkButton(
                $url,
                '📸 Ver en Instagram',
                'border-[#ff6b8a]/28 bg-[#23090d]/88 text-[#fff1f5] hover:border-[#ff8fa3]/54 hover:bg-[#330d12]/95 w-full md:w-max'
            ),
            default => self::renderLinkButton(
                $url,
                '🔗 Visitar '.$domainLabel,
                'border-[#ff5b68]/24 bg-[#22080c]/82 text-[#f5dddd] hover:border-[#ff7481]/44 hover:bg-[#310d12]/94 w-full md:w-max'
            ),
        };
    }

    private static function renderLinkButton(string $url, string $label, string $variantClasses): string
    {
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="%s inline-flex items-center justify-center gap-2 rounded-full border px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] transition duration-200">%s</a>',
            e($url),
            e($variantClasses),
            e($label)
        );
    }

    /**
     * @param mixed $block
     */
    private static function extractString(mixed $block): string
    {
        if (is_string($block) || is_int($block) || is_float($block)) {
            return trim((string) $block);
        }

        if (is_object($block)) {
            return self::extractString((array) $block);
        }

        if (! is_array($block)) {
            return '';
        }

        if (isset($block['content']['html']) && is_string($block['content']['html'])) {
            return trim($block['content']['html']);
        }

        if (isset($block['html']) && is_string($block['html'])) {
            return trim($block['html']);
        }

        if (isset($block['content']) && is_string($block['content'])) {
            return trim($block['content']);
        }

        return trim(implode("\n", array_filter(array_map(
            fn ($value) => self::extractString($value),
            $block
        ))));
    }
}
