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

        $type = (string) ($block['type'] ?? 'raw');

        return match ($type) {
            'paragraph' => self::wrapHtml((string) ($block['value'] ?? '')),
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

        return trim($html);
    }

    private static function wrapHtml(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (preg_match('/<\s*(p|img|figure|blockquote|ul|ol|li|h[1-6]|div|section|article|br|iframe)\b/i', $text)) {
            return self::stripWpComments($text);
        }

        return '<p>'.e($text).'</p>';
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

        $html = '<blockquote><p>'.e($text).'</p>';
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

        return $html !== '' ? self::stripWpComments($html) : '';
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
