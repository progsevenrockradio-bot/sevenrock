<?php

namespace App\Support;

use App\Models\ThemeSetting;

class ThemeAppearance
{
    public static function fonts(): array
    {
        return [
            'body' => [
                'Open Sans' => 'Open Sans',
                'Inter' => 'Inter',
                'Roboto' => 'Roboto',
                'Montserrat' => 'Montserrat',
                'Poppins' => 'Poppins',
            ],
            'heading' => [
                'Oswald' => 'Oswald',
                'Bebas Neue' => 'Bebas Neue',
                'Montserrat' => 'Montserrat',
                'Inter' => 'Inter',
                'Roboto Condensed' => 'Roboto Condensed',
            ],
            'brand_mark' => [
                'Rock Salt' => 'Rock Salt',
                'Great Vibes' => 'Great Vibes',
                'Pacifico' => 'Pacifico',
                'Satisfy' => 'Satisfy',
                'Kaushan Script' => 'Kaushan Script',
            ],
        ];
    }

    public static function resolved(): array
    {
        $settings = ThemeSetting::current();
        $fonts = self::fonts();
        $visual = $settings->resolvedVisual();
        $media = $settings->resolvedMedia();
        $links = $settings->resolvedLinks();

        return [
            'settings' => $settings,
            'fonts' => $fonts,
            'visual' => $visual,
            'media' => $media,
            'links' => $links,
            'contact' => $settings->contact(),
            'site_name' => $visual['site_name'],
            'brand_mark' => $visual['brand_mark'],
            'brand_mark_font' => $visual['brand_mark_font'],
            'brand_display_mode' => $visual['brand_display_mode'],
            'google_fonts_url' => $visual['google_fonts_url'],
            'brand_font' => $visual['body_font'],
            'heading_font' => $visual['heading_font'],
            'brand_mark_family_css' => self::familyCss($fonts['brand_mark'][$visual['brand_mark_font']] ?? $visual['brand_mark_font']),
            'accent_color' => $visual['accent_color'],
            'nav_color' => $visual['nav_color'],
            'surface_color' => $visual['surface_color'],
            'body_color' => $visual['body_color'],
            'heading_color' => $visual['heading_color'],
            'line_color' => $visual['line_color'],
            'logo_url' => $media['logo_url'],
            'background_url' => $media['background_url'],
            'hero_slide_primary_url' => $media['hero_slide_primary_url'],
            'hero_slide_secondary_url' => $media['hero_slide_secondary_url'],
            'home_album_cover_url' => $media['home_album_cover_url'],
            'home_video_image_url' => $media['home_video_image_url'],
            'hero_video_url' => $media['hero_video_media_url'],
            'hero_video_disabled' => (bool) $media['hero_video_disabled'],
            'social_links' => $links['social_links'],
            'featured_stories' => $settings->featuredStories(),
            'latest_podcasts' => $settings->latestPodcasts(),
            'home_headings' => $settings->homeHeadings(),
            'ui_texts' => $settings->uiTexts(),
            'admin_texts' => $settings->adminTexts(),
            'font_faces_css' => '',
            'brand_family_css' => self::familyCss($fonts['body'][$visual['body_font']] ?? $visual['body_font']),
            'heading_family_css' => self::familyCss($fonts['heading'][$visual['heading_font']] ?? $visual['heading_font']),
        ];
    }

    public static function normalizeHeroExternalVideoUrl(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $url = filter_var($trimmed, FILTER_SANITIZE_URL);
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        if (preg_match('~(?:youtube\.com|youtu\.be)~', $host)) {
            $candidate = '';
            if ($host === 'youtu.be') {
                $candidate = trim($path, '/');
            } else {
                parse_str($query, $parsedQuery);
                $candidate = (string) ($parsedQuery['v'] ?? '');
                if ($candidate === '' && preg_match('~/embed/([^/?]+)~', $path, $matches)) {
                    $candidate = $matches[1];
                }
            }

            return $candidate !== '' ? 'https://www.youtube.com/watch?v=' . $candidate : '';
        }

        if (str_contains($host, 'vimeo.com')) {
            if (preg_match('~/(\d+)~', $path, $matches)) {
                return 'https://vimeo.com/' . $matches[1];
            }
        }

        if (preg_match('/\.(mp4|webm)$/i', $path)) {
            return $url;
        }

        return '';
    }

    public static function heroSlide3Media(?ThemeSetting $settings = null): array
    {
        $settings ??= ThemeSetting::current();

        if ($settings->hero_video_disabled) {
            return [
                'mode' => 'image',
                'provider' => 'disabled',
                'embed_url' => '',
                'video_url' => '',
                'video_mime' => '',
                'image_url' => $settings->hero_slide_secondary_url,
            ];
        }

        $external = self::normalizeHeroExternalVideoUrl((string) $settings->hero_video_url);
        if ($external !== '') {
            return [
                'mode' => 'embed',
                'provider' => 'external',
                'embed_url' => $external,
                'video_url' => '',
                'video_mime' => '',
                'image_url' => '',
            ];
        }

        if ($settings->hero_video_path) {
            $resolved = PublicMediaUrl::normalize($settings->hero_video_path);

            return [
                'mode' => 'video',
                'provider' => 'local',
                'embed_url' => '',
                'video_url' => $resolved ?? asset($settings->hero_video_path),
                'video_mime' => str_ends_with(strtolower((string) pathinfo($settings->hero_video_path, PATHINFO_EXTENSION)), 'webm') ? 'video/webm' : 'video/mp4',
                'image_url' => '',
            ];
        }

        return [
            'mode' => 'image',
            'provider' => 'fallback',
            'embed_url' => '',
            'video_url' => '',
            'video_mime' => '',
            'image_url' => $settings->hero_slide_secondary_url,
        ];
    }

    private static function familyCss(string $value): string
    {
        return '"' . trim($value) . '"';
    }
}
