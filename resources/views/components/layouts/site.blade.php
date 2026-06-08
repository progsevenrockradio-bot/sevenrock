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
    @stack('preloads')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .section-band { background: rgba(8, 26, 36, 0.2) !important; }
        .home-section-texture::before { opacity: 0.5; }
        .lucille-page-heading .lucille-card-image + div[class="absolute inset-0"] { background: rgba(21, 21, 21, 0.3) !important; }
        .social-flyout {
            position: fixed;
            left: 0;
            top: 50%;
            z-index: 85;
            display: none;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .social-flyout:hover,
        .social-flyout:focus-within {
            pointer-events: auto;
        }
        .social-flyout__inner {
            display: flex;
            align-items: stretch;
            pointer-events: auto;
        }
        .social-flyout__tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            min-height: 162px;
            border: 1px solid rgba(255,255,255,.08);
            border-left: 0;
            border-radius: 0 14px 14px 0;
            background: linear-gradient(180deg, rgba(18,18,18,.86), rgba(10,10,10,.78));
            color: #b9b3ab;
            font-family: var(--lucille-heading-font, var(--font-display));
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .40em;
            text-transform: uppercase;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            box-shadow: 6px 0 18px rgba(0,0,0,.14);
            transition: transform .6s ease, background .6s ease, border-color .6s ease, color .6s ease;
        }
        .social-flyout__panel {
            width: 0;
            max-width: 0;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.08);
            border-left: 0;
            border-radius: 0 16px 16px 0;
            background:
                linear-gradient(180deg, rgba(14,14,14,.82), rgba(10,10,10,.72)),
                rgba(12,12,12,.78);
            backdrop-filter: blur(12px);
            box-shadow: 10px 0 28px rgba(0,0,0,.18);
            opacity: 0;
            transform: translateX(-12px);
            transition: max-width .6s ease, width .6s ease, opacity .6s ease, transform .6s ease;
        }
        .social-flyout:hover .social-flyout__panel,
        .social-flyout:focus-within .social-flyout__panel {
            width: 190px;
            max-width: 190px;
            opacity: 1;
            transform: translateX(0);
        }
        .social-flyout:hover .social-flyout__tab,
        .social-flyout:focus-within .social-flyout__tab {
            transform: translateX(1px);
            border-color: rgba(255,255,255,.12);
            color: #e6dfd7;
            background: linear-gradient(180deg, rgba(22,22,22,.92), rgba(12,12,12,.86));
        }
        .social-flyout__content {
            width: 190px;
            padding: 16px 14px 16px 12px;
        }
        .social-flyout__title {
            margin-bottom: 12px;
            color: #8f887d;
            font-size: 9px;
            letter-spacing: .36em;
            text-transform: uppercase;
        }
        .social-flyout__links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .social-flyout__link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 9999px;
            padding: 0 11px 0 9px;
            background: rgba(255,255,255,.025);
            color: #e8dfd8;
            text-decoration: none;
            transition: transform .22s ease, border-color .22s ease, background .22s ease, color .22s ease;
        }
        .social-flyout__link:hover {
            border-color: rgba(255,255,255,.14);
            background: rgba(255,255,255,.045);
            color: #fff;
            transform: translateX(2px);
        }
        .social-flyout__badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 9999px;
            background: rgba(255,255,255,.06);
            color: #d9d1c7;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .social-flyout__label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            line-height: 1;
        }
        @media (min-width: 1024px) {
            .social-flyout {
                display: block;
            }
        }
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
        .radio-player-mobile [data-player-volume-input],
        .radio-player-mobile [data-player-volume-output],
        .radio-player-mobile .rbcloud_tracktimer { display: none !important; }
        .radio-player-mobile [data-player-action="favorite"] { min-width: 30px !important; padding: 0 4px !important; }
        .back-to-top-button > span:last-child {
            display: none;
        }
        .back-to-top-button {
            width: 44px;
            min-width: 44px;
            min-height: 44px;
            padding: 0;
            border-radius: 12px;
            gap: 0;
        }
        .back-to-top-button__icon {
            width: 100%;
            height: 100%;
        }
        @media (max-width: 767px) {
            .back-to-top-button {
                right: 10px;
                bottom: 78px;
                width: auto;
                min-width: 40px;
                min-height: 40px;
                padding: 0 10px;
                gap: 5px;
                font-size: 9px;
                letter-spacing: .16em;
                box-shadow: 0 8px 20px rgba(0, 0, 0, .35);
            }

            .back-to-top-button__icon {
                width: 20px;
                height: 20px;
                font-size: 9px;
            }
        }
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

    <main id="site-main" class="flex min-h-screen flex-col">
        <div id="site-content" class="flex-1">
            {{ $slot }}
        </div>
    </main>

    @if (!request()->routeIs('programs*'))
        <x-radio.player />
    @endif

    <button
        type="button"
        x-data="backToTopButton()"
        x-cloak
        x-show="visible"
        x-transition.opacity.duration.200ms
        @click="scrollToTop()"
        class="back-to-top-button"
        aria-label="Volver arriba"
        title="Volver arriba"
    >
        <span class="back-to-top-button__icon">↑</span>
        <span>Arriba</span>
    </button>

    @php
        $preferredSocialOrder = ['facebook', 'instagram', 'youtube'];
        $socialLinks = collect($theme['social_links'] ?? [])
            ->filter(fn (array $social): bool => trim((string) ($social['url'] ?? '')) !== '')
            ->map(static function (array $social): array {
                $network = strtolower(trim((string) ($social['network'] ?? 'social')));
                $label = match ($network) {
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'youtube' => 'YouTube',
                    'x', 'twitter' => 'X',
                    'tiktok' => 'TikTok',
                    default => ucfirst($network !== '' ? $network : 'Social'),
                };

                $badge = match ($network) {
                    'facebook' => 'f',
                    'instagram' => 'ig',
                    'youtube' => 'yt',
                    'x', 'twitter' => 'x',
                    'tiktok' => 'tt',
                    default => strtoupper(substr($label, 0, 2)),
                };

                return [
                    'order' => $network,
                    'label' => $label,
                    'badge' => $badge,
                    'url' => trim((string) ($social['url'] ?? '')),
                ];
            })
            ->filter(fn (array $social): bool => in_array($social['order'], $preferredSocialOrder, true))
            ->sortBy(fn (array $social): int => array_search($social['order'], $preferredSocialOrder, true))
            ->values();
    @endphp

    @if ($socialLinks->isNotEmpty())
        <aside class="social-flyout" aria-label="Redes sociales">
            <div class="social-flyout__inner">
                <div class="social-flyout__tab">Social</div>
                <div class="social-flyout__panel">
                    <div class="social-flyout__content">
                        <div class="social-flyout__title">Síguenos</div>
                        <div class="social-flyout__links">
                            @foreach ($socialLinks as $social)
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="social-flyout__link" aria-label="Seguir en {{ $social['label'] }}">
                                    <span class="social-flyout__badge">{{ $social['badge'] }}</span>
                                    <span class="social-flyout__label">{{ $social['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    @endif

    <footer id="site-footer" class="bg-lucille-surface py-7 text-center text-[13px] text-[#7b7b7b]">
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
</body>
</html>
