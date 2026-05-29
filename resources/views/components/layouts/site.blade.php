@props([
    'title' => 'Seven Rock Radio',
    'description' => 'Seven Rock Radio — Música rock, entrevistas, eventos y la mejor vibra. Tu radio rock online.',
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'canonical' => null,
])

@php
    $theme = $themeAppearance;
    $siteUrl = config('app.url', 'https://sevenrockradio.shop');
    $logoUrl = $theme['media']['logo_url'] ?? $siteUrl . '/assets/lucille/logo.png';

    $finalTitle = $title;
    $finalDescription = $description;
    $finalOgTitle = $ogTitle ?? $title;
    $finalOgDescription = $ogDescription ?? $description;
    $finalOgImage = $ogImage ?? $logoUrl;
    $finalCanonical = $canonical ?? url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="scrollbar-gutter: stable;">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $finalTitle }}</title>
    <meta name="description" content="{{ $finalDescription }}">

    <!-- Canonical -->
    <link rel="canonical" href="{{ $finalCanonical }}">

    <!-- Open Graph -->
    <meta property="og:site_name" content="Seven Rock Radio">
    <meta property="og:title" content="{{ $finalOgTitle }}">
    <meta property="og:description" content="{{ $finalOgDescription }}">
    <meta property="og:image" content="{{ $finalOgImage }}">
    <meta property="og:url" content="{{ $finalCanonical }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $finalOgTitle }}">
    <meta name="twitter:description" content="{{ $finalOgDescription }}">
    <meta name="twitter:image" content="{{ $finalOgImage }}">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme['google_fonts_url'] }}" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .section-band { background: rgba(8, 26, 36, 0.2) !important; }
        .home-section-texture::before { opacity: 0.5; }
        .lucille-page-heading .lucille-card-image + div[class="absolute inset-0"] { background: rgba(21, 21, 21, 0.3) !important; }
        .radio-player-mobile {
            grid-template-columns: 40px 1fr auto !important;
            gap: 6px !important;
            padding: 6px 8px !important;
            min-height: 44px !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            transform: none !important;
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            border-bottom: none !important;
        }
        .radio-player-mobile .radio-player-cover { width: 32px !important; height: 32px !important; }
        .radio-player-mobile .radio-player-meta strong { font-size: 10px !important; letter-spacing: .04em !important; }
        .radio-player-mobile .radio-player-meta span { font-size: 9px !important; }
        .radio-player-mobile .radio-player-actions { gap: 3px !important; }
        .radio-player-mobile .radio-player-icon { width: 30px !important; height: 30px !important; min-width: 30px !important; font-size: 9px !important; }
        .radio-player-mobile .radio-player-icon span:last-child { display: none !important; }
        .radio-player-mobile [data-player-volume-input],
        .radio-player-mobile [data-player-volume-output],
        .radio-player-mobile .rbcloud_tracktimer { display: none !important; }
        .radio-player-mobile [data-player-action="favorite"] { min-width: 30px !important; padding: 0 4px !important; }
    </style>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ env('GOOGLE_ANALYTICS_ID') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ env('GOOGLE_ANALYTICS_ID') }}');
    </script>
</head>
<body
    class="antialiased"
    style="
        --lucille-accent: {{ $theme['visual']['accent_color'] }};
        --lucille-nav: {{ $theme['visual']['nav_color'] }};
        --lucille-surface: {{ $theme['visual']['surface_color'] }};
        --lucille-body: {{ $theme['visual']['body_color'] }};
        --lucille-heading: {{ $theme['visual']['heading_color'] }};
        --lucille-line: {{ $theme['visual']['line_color'] }};
        --lucille-body-font: '{{ $theme['brand_font'] }}';
        --lucille-heading-font: '{{ $theme['heading_font'] }}';
        --lucille-brand-font: '{{ $theme['brand_mark_font'] }}';
        --lucille-bg-image: url('{{ $theme['media']['background_url'] }}');
    "
>
    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    <x-navigation.rocks-menu :theme="$theme" />

    <main class="flex min-h-screen flex-col">
        <div class="flex-1 pb-40">
            {{ $slot }}
        </div>
    </main>

    <x-radio.player />

    <footer class="bg-lucille-surface py-7 text-center text-[13px] text-[#7b7b7b]">
        <div class="mx-auto flex max-w-[1180px] flex-col items-center gap-3 px-5">
            <div class="flex flex-wrap items-center justify-center gap-4">
                @foreach ($theme['social_links'] as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noreferrer" class="transition hover:text-lucille-accent">
                        {{ strtoupper($social['network']) }}
                    </a>
                @endforeach
            </div>
            <div>{{ $theme['site_name'] }} © Laravel Blade Edition</div>
        </div>
    </footer>
    @stack('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function fixRadioPlayer() {
            var dock = document.querySelector('.radio-player-dock');
            if (!dock) return;
            if (window.innerWidth < 768) {
                dock.classList.add('radio-player-mobile');
            } else {
                dock.classList.remove('radio-player-mobile');
            }
        }
        fixRadioPlayer();
        window.addEventListener('resize', fixRadioPlayer);
    });
    </script>
</body>
</html>
