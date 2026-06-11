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
    $siteUrl = config('app.url', 'https://sevenrockradio.com');
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
    <link rel="icon" type="image/png" href="{{ $logoUrl }}">
    <link rel="apple-touch-icon" href="{{ $logoUrl }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme['google_fonts_url'] }}" rel="stylesheet">
    @stack('preloads')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Cookie Toggles Switch Styles */
        .cookie-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .cookie-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .cookie-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.15);
            transition: .3s ease;
            border-radius: 24px;
        }
        .cookie-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s ease;
            border-radius: 50%;
        }
        input:checked + .cookie-slider {
            background-color: var(--lucille-accent, #c32720);
        }
        input:checked + .cookie-slider:before {
            transform: translateX(20px);
        }

        .section-band { background: rgba(8, 26, 36, 0.2) !important; }
        .home-section-texture::before { background-image: none !important; }
        .lucille-page-heading .lucille-card-image + div[class="absolute inset-0"] { background: rgba(21, 21, 21, 0.3) !important; }
        @media (max-width: 767px) {
            #site-footer {
                padding-top: 28px !important;
                padding-bottom: 96px !important;
                font-size: 11px !important;
            }
        }
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
            width: 22px;
            height: 22px;
            border-radius: 9999px;
            background: rgba(255,255,255,.06);
            color: #d9d1c7;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .social-flyout__badge svg {
            width: 12px;
            height: 12px;
            display: block;
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

    @if (!request()->routeIs('programs*') && !request()->routeIs('contratos*'))
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
            ->values();
    @endphp

    @if ($socialLinks->isNotEmpty())
        <aside class="social-flyout" aria-label="Redes sociales">
            <div class="social-flyout__inner">
                <div class="social-flyout__tab">
                    Social
                </div>
                <div class="social-flyout__panel" style="position: relative;">
                    <!-- Blood Drip Overlay at the top of the panel (non-squished aspect ratio) -->
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 40px; background: url('{{ asset('assets/lucille/blood_drip.png') }}') repeat-x top center; background-size: 50px 40px; pointer-events: none; opacity: 0.85; z-index: 10;"></div>
                    
                    <div class="social-flyout__content" style="padding-top: 36px;">
                        <div class="social-flyout__title">Síguenos</div>
                        <div class="social-flyout__links">
                            @foreach ($socialLinks as $social)
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="social-flyout__link" aria-label="Seguir en {{ $social['label'] }}">
                                    <span class="social-flyout__badge">
                                        @if ($social['order'] === 'facebook')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                            </svg>
                                        @elseif ($social['order'] === 'instagram')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                            </svg>
                                        @elseif ($social['order'] === 'youtube')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
                                                <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
                                            </svg>
                                        @elseif (in_array($social['order'], ['x', 'twitter']))
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
                                                <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                                            </svg>
                                        @elseif ($social['order'] === 'tiktok')
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
                                            </svg>
                                        @else
                                            {{ $social['badge'] }}
                                        @endif
                                    </span>
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
                    @php
                        $network = strtolower(trim($social['network']));
                    @endphp
                    <a href="{{ $social['url'] }}" target="_blank" rel="noreferrer" class="transition hover:text-lucille-accent" aria-label="{{ ucfirst($network) }}" title="{{ ucfirst($network) }}">
                        @if ($network === 'facebook')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                        @elseif ($network === 'instagram')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        @elseif ($network === 'youtube')
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
                                <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
                            </svg>
                        @elseif (in_array($network, ['x', 'twitter']))
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
                                <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                            </svg>
                        @else
                            {{ strtoupper($social['network']) }}
                        @endif
                    </a>
                @endforeach
            </div>
            {{-- Menú Legal en el Footer --}}
            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] font-display uppercase tracking-[0.1em] text-[#9aa7b1] mt-1">
                <a href="{{ route('copyright-policy') }}" class="transition hover:text-lucille-accent">Términos y Copyright</a>
                <span class="text-white/10 hidden sm:inline">|</span>
                <a href="{{ route('privacy-policy') }}" class="transition hover:text-lucille-accent">Política de Privacidad</a>
                <span class="text-white/10 hidden sm:inline">|</span>
                <button type="button" onclick="openCookieSettings()" class="transition hover:text-lucille-accent focus:outline-none">Preferencias de Cookies</button>
            </div>
            
            {{-- Texto de Copyright Detallado --}}
            <div class="text-[11px] max-w-[850px] leading-relaxed text-[#5c5c5c] mt-2 select-none">
                © {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados. Creado por jmSolutions. Queda prohibida la reproducción total o parcial de los contenidos, diseño y estructura de esta web sin autorización previa y por escrito de Seven Rock Radio.
            </div>
        </div>
    </footer>

    <!-- Banner de Cookies Estilo Ampwall -->
    <div id="cookie-consent-banner" class="fixed bottom-6 left-6 right-6 z-[250] mx-auto max-w-4xl rounded-2xl border border-white/[0.08] bg-[#0f141c]/95 p-6 shadow-[0_20px_50px_rgba(0,0,0,0.8)] backdrop-blur-md transition-all duration-500 translate-y-[150%] opacity-0 md:p-8" style="display: none;">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-3 lg:max-w-[65%] text-left">
                <div class="flex items-center gap-3">
                    <span class="text-3xl select-none">🍪</span>
                    <h4 class="font-display text-base uppercase tracking-wider text-white md:text-lg">
                        Los banners de cookies pueden ser molestos, pero...
                    </h4>
                </div>
                <p class="text-xs leading-relaxed text-[#9aa7b1] md:text-sm">
                    En Seven Rock Radio usamos cookies para analizar nuestro tráfico, saber cuántos metaleros y rockeros nos sintonizan, y asegurarnos de que la plataforma y la transmisión funcionen al máximo nivel. Puedes leer más detalladamente en nuestra <a href="{{ route('privacy-policy') }}" class="text-lucille-accent hover:underline">Política de Privacidad</a>.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col lg:w-[30%] shrink-0">
                <button type="button" onclick="acceptAllCookies()" class="w-full rounded-full bg-lucille-accent px-5 py-3 text-center text-[10px] font-display uppercase tracking-[0.15em] text-white transition-all duration-300 hover:bg-opacity-90 active:scale-98 shadow-md shadow-lucille-accent/20">
                    Genial, ¡que suene el Rock!
                </button>
                <button type="button" onclick="acceptNecessaryCookies()" class="w-full rounded-full border border-white/10 bg-white/[0.02] px-5 py-3 text-center text-[10px] font-display uppercase tracking-[0.15em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] active:scale-98">
                    No, solo necesarias
                </button>
                <button type="button" onclick="openCookieSettingsModal()" class="w-full text-center text-[10px] font-display uppercase tracking-[0.1em] text-[#7b7b7b] hover:text-[#9aa7b1] transition-colors mt-1 focus:outline-none">
                    Gestionar preferencias
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ajustes de Cookies (Preferencias Individuales) -->
    <div id="cookie-settings-modal" class="fixed inset-0 z-[300] hidden items-center justify-center p-4" style="background-color: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px);">
        <div class="relative w-full max-w-xl rounded-2xl border border-white/[0.08] bg-[#10161b] p-6 shadow-2xl md:p-8 text-left">
            <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
                <h3 class="font-display text-sm uppercase tracking-wider text-white">Preferencias de Cookies</h3>
                <button type="button" onclick="closeCookieSettingsModal()" class="text-[#7b7b7b] hover:text-white transition-colors focus:outline-none" aria-label="Cerrar modal">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="text-xs leading-relaxed text-[#9aa7b1] mb-6">
                Personaliza cómo Seven Rock Radio recopila información. Las cookies ayudan a que la radio suene con la mejor fidelidad técnica y contenido optimizado.
            </p>

            <div class="space-y-4">
                <!-- Cookies Necesarias -->
                <div class="flex items-start justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.01] p-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-white text-xs font-display uppercase tracking-wider">Técnicas y de Transmisión</span>
                            <span class="rounded bg-white/10 px-2 py-0.5 text-[9px] text-[#7b7b7b] uppercase">Obligatorio</span>
                        </div>
                        <p class="text-[11px] leading-relaxed text-[#7b7b7b]">
                            Esenciales para cargar el reproductor de audio, mantener la reproducción activa y controlar el volumen. No se pueden desactivar.
                        </p>
                    </div>
                    <div class="shrink-0 mt-1">
                        <div class="relative inline-flex items-center cursor-not-allowed">
                            <div class="w-11 h-6 bg-lucille-accent rounded-full opacity-60"></div>
                            <div class="absolute left-6 top-1 bg-white w-4 h-4 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <!-- Cookies Analíticas -->
                <div class="flex items-start justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.01] p-4">
                    <div class="space-y-1">
                        <span class="text-white text-xs font-display uppercase tracking-wider">Métrica de Oyentes (Analíticas)</span>
                        <p class="text-[11px] leading-relaxed text-[#7b7b7b]">
                            Nos permite saber de manera totalmente anónima cuántos oyentes (metaleros y rockeros) están sintonizados y qué programas son los preferidos.
                        </p>
                    </div>
                    <label class="cookie-switch shrink-0 mt-1">
                        <input type="checkbox" id="cookie-opt-analytics" checked>
                        <span class="cookie-slider"></span>
                    </label>
                </div>

                <!-- Cookies Personalización -->
                <div class="flex items-start justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.01] p-4">
                    <div class="space-y-1">
                        <span class="text-white text-xs font-display uppercase tracking-wider">Ajustes del Reproductor (Personalización)</span>
                        <p class="text-[11px] leading-relaxed text-[#7b7b7b]">
                            Guardar tu volumen preferido o si deseas silenciar la radio al cargar la página para que no tengas que configurarlo en cada visita.
                        </p>
                    </div>
                    <label class="cookie-switch shrink-0 mt-1">
                        <input type="checkbox" id="cookie-opt-personalization" checked>
                        <span class="cookie-slider"></span>
                    </label>
                </div>
            </div>

            <div class="mt-8">
                <button type="button" onclick="saveCookiePreferences()" class="w-full rounded-full bg-lucille-accent py-3 text-center text-xs font-display uppercase tracking-[0.15em] text-white transition-all duration-300 hover:bg-opacity-90 active:scale-98 shadow-md">
                    Guardar Preferencias
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const consent = localStorage.getItem('sevenrock_cookie_consent');
            const banner = document.getElementById('cookie-consent-banner');
            
            if (!consent) {
                setTimeout(() => {
                    banner.style.display = 'block';
                    // Trigger reflow
                    banner.offsetHeight;
                    banner.classList.remove('translate-y-[150%]', 'opacity-0');
                    banner.classList.add('translate-y-0', 'opacity-100');
                }, 1000);
            }
        });

        function hideCookieBanner() {
            const banner = document.getElementById('cookie-consent-banner');
            banner.classList.remove('translate-y-0', 'opacity-100');
            banner.classList.add('translate-y-[150%]', 'opacity-0');
            setTimeout(() => {
                banner.style.display = 'none';
            }, 500);
        }

        function acceptAllCookies() {
            const preferences = { necessary: true, analytics: true, personalization: true };
            localStorage.setItem('sevenrock_cookie_consent', JSON.stringify(preferences));
            applyCookiePreferences(preferences);
            hideCookieBanner();
        }

        function acceptNecessaryCookies() {
            const preferences = { necessary: true, analytics: false, personalization: false };
            localStorage.setItem('sevenrock_cookie_consent', JSON.stringify(preferences));
            applyCookiePreferences(preferences);
            hideCookieBanner();
        }

        function openCookieSettingsModal() {
            const modal = document.getElementById('cookie-settings-modal');
            const consent = localStorage.getItem('sevenrock_cookie_consent');
            if (consent) {
                const prefs = JSON.parse(consent);
                document.getElementById('cookie-opt-analytics').checked = !!prefs.analytics;
                document.getElementById('cookie-opt-personalization').checked = !!prefs.personalization;
            }
            modal.style.display = 'flex';
        }

        function closeCookieSettingsModal() {
            const modal = document.getElementById('cookie-settings-modal');
            modal.style.display = 'none';
        }

        function saveCookiePreferences() {
            const analytics = document.getElementById('cookie-opt-analytics').checked;
            const personalization = document.getElementById('cookie-opt-personalization').checked;
            const preferences = { necessary: true, analytics, personalization };
            localStorage.setItem('sevenrock_cookie_consent', JSON.stringify(preferences));
            applyCookiePreferences(preferences);
            closeCookieSettingsModal();
            hideCookieBanner();
        }

        function openCookieSettings() {
            openCookieSettingsModal();
        }

        function applyCookiePreferences(prefs) {
            console.log('Cookie preferences applied:', prefs);
        }
    </script>

    @stack('scripts')
</body>
</html>
