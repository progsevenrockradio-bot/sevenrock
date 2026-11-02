@props([
    'title' => 'Seven Rock Radio',
    'description' => 'Seven Rock Radio — Música rock, entrevistas, eventos y la mejor vibra. Tu radio rock online.',
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'canonical' => null,
    'showPlayer' => true,
    'showSocialFlyout' => true,
    'ogType' => 'website',
    'twitterCard' => 'summary_large_image',
    'ogArticlePublishedTime' => null,
    'ogArticleAuthor' => null,
])

@php
    // Detect track share context (signature) via query string "v"
    $vParam = request()->query('v');
    if ($vParam) {
        $vDecoded = urldecode($vParam);
        $vTitle = '';
        $vArtist = '';
        $vCover = '';
        $vProgram = '';

        if (str_contains($vDecoded, '|')) {
            $parts = explode('|', $vDecoded);
            $vTitle = trim($parts[0] ?? '');
            $vArtist = trim($parts[1] ?? '');
            $vCover = trim($parts[2] ?? '');
            $vProgram = trim($parts[3] ?? '');
        } else {
            // Short key format from Cache
            $cached = \Illuminate\Support\Facades\Cache::get("share_track_{$vDecoded}");
            if ($cached) {
                $vTitle = trim($cached['title'] ?? '');
                $vArtist = trim($cached['artist'] ?? '');
                $vCover = trim($cached['cover'] ?? '');
                $vProgram = trim($cached['program'] ?? '');
            }
        }

        if ($vTitle !== '') {
            $trackLabel = $vArtist !== '' ? "{$vTitle} - {$vArtist}" : $vTitle;
            $title = "Escuchando: {$trackLabel} | Seven Rock Radio";
            $description = "Estoy escuchando a través de Seven Rock Radio" . ($vProgram !== '' ? " el programa {$vProgram}" : "") . ". ¡Sintoniza ahora!";
            if ($vCover !== '') {
                $ogImage = $vCover;
            }
        }
    }

    $theme = $themeAppearance;
    $siteUrl = rtrim((string) config('app.url', 'https://sevenrockradio.com'), '/');
    $logoUrl = $theme['media']['logo_url'] ?? $siteUrl . '/assets/lucille/logo.png';

    // Normalize localhost / 127.0.0.1 in logo URL
    $logoUrl = \App\Support\PublicMediaUrl::normalize($logoUrl) ?? $logoUrl;

    $finalTitle = $title;
    $finalDescription = $description;
    $finalOgTitle = $ogTitle ?? $title;
    $finalOgDescription = $ogDescription ?? $description;
    
    // Automatically generate a share image with a solid dark background for OG previews (resolves WhatsApp transparency grid issues)
    $finalOgImage = $ogImage;
    if ($finalOgImage) {
        $finalOgImage = \App\Support\PublicMediaUrl::normalize($finalOgImage) ?? $finalOgImage;
        if ($finalOgImage && !str_starts_with(strtolower($finalOgImage), 'http://') && !str_starts_with(strtolower($finalOgImage), 'https://')) {
            $finalOgImage = $siteUrl . '/' . ltrim($finalOgImage, '/');
        }
    } else {
        $finalOgImage = \Illuminate\Support\Facades\Cache::remember('og_share_image_url_' . md5($logoUrl), now()->addHours(24), function () use ($logoUrl, $siteUrl) {
            $computedUrl = $logoUrl;
            try {
                $logoPath = null;
                $parsedLogo = parse_url($logoUrl);
                $logoPathOnly = $parsedLogo['path'] ?? '';
                $host = $parsedLogo['host'] ?? '';
                $siteHost = parse_url($siteUrl, PHP_URL_HOST) ?: '';
                
                if ($logoPathOnly && (
                    $host === '' || 
                    $host === 'localhost' || 
                    $host === '127.0.0.1' || 
                    strcasecmp($host, $siteHost) === 0
                )) {
                    $logoPath = public_path($logoPathOnly);
                }
                
                if ($logoPath && file_exists($logoPath)) {
                    $dir = dirname($logoPath);
                    $filename = pathinfo($logoPath, PATHINFO_FILENAME);
                    $ext = pathinfo($logoPath, PATHINFO_EXTENSION);
                    $shareFilename = $filename . '_share.png';
                    $sharePath = $dir . DIRECTORY_SEPARATOR . $shareFilename;
                    
                    if (!file_exists($sharePath) || filemtime($logoPath) > filemtime($sharePath)) {
                        $src = null;
                        if (strtolower($ext) === 'png') {
                            $src = @imagecreatefrompng($logoPath);
                        } elseif (in_array(strtolower($ext), ['jpg', 'jpeg'])) {
                            $src = @imagecreatefromjpeg($logoPath);
                        } elseif (strtolower($ext) === 'webp') {
                            $src = @imagecreatefromwebp($logoPath);
                        }
                        
                        if ($src) {
                            $w = imagesx($src);
                            $h = imagesy($src);
                            
                            $dst = imagecreatetruecolor($w, $h);
                            $bgColor = imagecolorallocate($dst, 16, 16, 18); // Solid theme background (#101012)
                            imagefill($dst, 0, 0, $bgColor);
                            
                            imagealphablending($dst, true);
                            imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                            
                            imagepng($dst, $sharePath);
                            imagedestroy($src);
                            imagedestroy($dst);
                        }
                    }
                    
                    if (file_exists($sharePath)) {
                        $relativePath = str_replace(public_path(), '', $sharePath);
                        $relativePath = str_replace('\\', '/', $relativePath);
                        $computedUrl = $siteUrl . '/' . ltrim($relativePath, '/');
                    }
                }
            } catch (\Throwable $e) {
                // Graceful fallback to default logoUrl
                logger()->error('Error generating og:image share logo: ' . $e->getMessage());
            }
            return $computedUrl;
        });
    }
    
    // Strip query parameters and version tokens from canonical to guarantee pure canonical URLs
    $rawCanonical = $canonical ?? url()->current();
    if (is_string($rawCanonical) && str_contains($rawCanonical, '?')) {
        $rawCanonical = explode('?', $rawCanonical)[0];
    }
    $finalCanonical = \App\Support\PublicMediaUrl::normalize($rawCanonical) ?? $rawCanonical;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="scrollbar-gutter: stable;" data-theme="pinion-dark" data-tune="modern">
<head>
    <!-- DataLayer inicializado (Carga externa de GTM deshabilitada preventivamente hasta auditar contenedor) -->
    <script>
        window.dataLayer = window.dataLayer || [];
        window.SR_EXTERNAL_ANALYTICS_ENABLED = false;
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $finalTitle }}</title>
    <meta name="description" content="{{ $finalDescription }}">

    <!-- Canonical -->
    <link rel="canonical" href="{{ $finalCanonical }}">

    <!-- Open Graph -->
    <meta property="og:site_name" content="Seven Rock Radio">
    <meta property="og:title" content="{!! htmlspecialchars(html_entity_decode($finalOgTitle, ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') !!}">
    <meta property="og:description" content="{!! htmlspecialchars(html_entity_decode($finalOgDescription, ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') !!}">
    <meta property="og:image" content="{{ $finalOgImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ $finalCanonical }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:locale" content="es_ES">
    <?php if($ogType === 'article' && $ogArticlePublishedTime): ?>
        <meta property="article:published_time" content="{{ $ogArticlePublishedTime }}">
        <?php if($ogArticleAuthor): ?>
            <meta property="article:author" content="{{ $ogArticleAuthor }}">
        <?php endif; ?>
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{!! htmlspecialchars(html_entity_decode($finalOgTitle, ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') !!}">
    <meta name="twitter:description" content="{!! htmlspecialchars(html_entity_decode($finalOgDescription, ENT_QUOTES, 'UTF-8'), ENT_COMPAT, 'UTF-8') !!}">
    <meta name="twitter:image" content="{{ $finalOgImage }}">

    <!-- Structured Data (Schema.org) -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "RadioStation",
        "name": "Seven Rock Radio",
        "url": "{{ config('app.url') }}",
        "logo": "{{ $logoUrl }}",
        "description": "{{ $finalDescription }}",
        "genre": ["Rock", "Metal", "Hard Rock"]
    }
    </script>
    @stack('jsonld')

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/lucille/logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $theme['google_fonts_url'] }}&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="{{ $theme['google_fonts_url'] }}&display=swap" rel="stylesheet"></noscript>
    @stack('preloads')
    @vite(['resources/css/app.css', 'resources/js/app.js'])


    @php
        $rawBgUrl = (string) ($theme['media']['background_url'] ?? asset('assets/lucille/dark-background.jpg'));
        $isDefaultLucilleBg = str_ends_with(strtolower(parse_url($rawBgUrl, PHP_URL_PATH) ?: ''), '/assets/lucille/dark-background.jpg');
        $webpBgUrl = asset('assets/lucille/dark-background.webp');
        $isLucilleFlatScope = request()->routeIs('home', 'multimedia') || request()->is('/', 'multimedia');
    @endphp
    <style>
        :root {
            --lucille-accent: {{ $theme['visual']['accent_color'] }};
            --lucille-accent-readable: {{ $theme['visual']['accent_readable_color'] ?? '#ff5b52' }};
            --lucille-nav: {{ $theme['visual']['nav_color'] }};
            --lucille-surface: {{ $theme['visual']['surface_color'] }};
            --lucille-body: {{ $theme['visual']['body_color'] }};
            --lucille-heading: {{ $theme['visual']['heading_color'] }};
            --lucille-line: {{ $theme['visual']['line_color'] }};
            --lucille-body-font: '{{ $theme['brand_font'] }}';
            --lucille-heading-font: '{{ $theme['heading_font'] }}';
            --lucille-brand-font: '{{ $theme['brand_mark_font'] }}';
            --lucille-bg-image-fallback: url('{{ $rawBgUrl }}');
            @if ($isDefaultLucilleBg)
            --lucille-bg-image: image-set(url('{{ $webpBgUrl }}') type('image/webp'), url('{{ $rawBgUrl }}') type('image/jpeg'));
            @else
            --lucille-bg-image: url('{{ $rawBgUrl }}');
            @endif
        }
    </style>
    @stack('styles')
</head>
<body class="antialiased{{ $isLucilleFlatScope ? ' lucille-theme-v2' : '' }}">
    {{-- Enlace accesible para saltar al contenido principal --}}
    <a href="#site-main" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[9999] focus:px-5 focus:py-2.5 focus:bg-[#c32720] focus:text-white focus:font-display focus:text-xs focus:uppercase focus:tracking-[0.15em] focus:rounded-sm focus:shadow-[0_4px_25px_rgba(0,0,0,0.9)] focus:outline-none focus:ring-2 focus:ring-white">
        Saltar al contenido
    </a>

    <div class="lucille-fixed-bg" aria-hidden="true"></div>

    <x-navigation.rocks-menu :theme="$theme" />

    <main id="site-main" tabindex="-1" class="flex min-h-screen flex-col focus:outline-none">
        <div id="site-content" class="flex-1">
            {{ $slot }}
        </div>
    </main>

    @if ($showPlayer && !request()->routeIs('programs*') && !request()->routeIs('contratos*') && !request()->routeIs('new-releases*') && !request()->routeIs('lanzamientos*') && !request()->routeIs('nuevos-lanzamientos*') && !request()->routeIs('colaboradores*') && !request()->routeIs('agency.public-profile'))
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

    @if ($showSocialFlyout && $socialLinks->isNotEmpty())
        <aside class="social-flyout" aria-label="Redes sociales">
            <div class="social-flyout__inner">
                <div class="social-flyout__tab">
                    Social
                </div>
                <div class="social-flyout__panel" style="position: relative;">
                    <!-- Blood Drip Overlay at the top of the panel (non-squished aspect ratio) -->
                    <div style="position: absolute; top: 0; left: 0; right: 0; height: 40px; background: url('{{ asset('assets/lucille/blood_drip.png') }}?v=1.2') repeat-x top center; background-size: 50px 40px; pointer-events: none; opacity: 0.85; z-index: 10;"></div>
                    
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

    <footer id="site-footer" class="bg-lucille-surface py-7 text-center text-[13px] text-[#7b7b7b]"
        @if (!request()->routeIs('programs*') && !request()->routeIs('contratos*'))
            style="padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));"
        @endif
    >
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
            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] font-display tracking-[0.1em] text-[#9aa7b1] mt-1">
                <a href="{{ route('copyright-policy') }}" class="transition hover:text-lucille-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent rounded px-1">Términos y copyright</a>
                <span class="text-white/10 hidden sm:inline" aria-hidden="true">|</span>
                <a href="{{ route('privacy-policy') }}" class="transition hover:text-lucille-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent rounded px-1">Política de privacidad</a>
                <span class="text-white/10 hidden sm:inline" aria-hidden="true">|</span>
                <button type="button" onclick="openCookieSettings()" class="transition hover:text-lucille-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent rounded px-1">Preferencias de cookies</button>
                <span class="text-white/10 hidden sm:inline" aria-hidden="true">|</span>
                <button type="button" onclick="openAffiliateModal(this)" class="transition hover:text-lucille-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent rounded px-1">Patrocinador: RadioBoss</button>
            </div>
            
            {{-- Texto de Copyright Detallado --}}
            <div class="text-[11px] max-w-[850px] leading-relaxed text-[#5c5c5c] mt-2 select-none">
                © {{ date('Y') }} Seven Rock Radio. Todos los derechos reservados. Creado por jmSolutions. Queda prohibida la reproducción total o parcial de los contenidos, diseño y estructura de esta web sin autorización previa y por escrito de Seven Rock Radio.
            </div>
        </div>
    </footer>

    <!-- Banner de Cookies Estilo Ampwall -->
    <div id="cookie-consent-banner" class="fixed inset-x-2 sm:inset-x-3 bottom-2 sm:bottom-3 z-[250] mx-auto max-w-4xl rounded-xl border border-white/[0.08] bg-[#0f141c]/95 px-4 py-4 sm:px-5 sm:py-4 shadow-[0_20px_50px_rgba(0,0,0,0.8)] backdrop-blur-md transition-all duration-500 translate-y-[150%] opacity-0 md:inset-x-6 md:px-6" style="display: none;">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="space-y-2 text-left flex-1">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 shrink-0 text-[#9aa7b1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 0 9 9 3.4 3.4 0 0 1-4.2-3.3A3.4 3.4 0 0 1 12 3Z"/><circle cx="9.2" cy="10" r=".85" fill="currentColor" stroke="none"/><circle cx="13.2" cy="14" r=".85" fill="currentColor" stroke="none"/><circle cx="8.6" cy="15.2" r=".85" fill="currentColor" stroke="none"/></svg>
                    <h4 class="font-display text-xs sm:text-sm uppercase tracking-[.12em] text-white md:text-base">
                        Los banners de cookies pueden ser molestos, pero...
                    </h4>
                </div>
                <p class="text-[10px] sm:text-[11px] leading-snug text-[#9aa7b1] md:text-xs md:leading-relaxed">
                    En Seven Rock Radio usamos cookies para analizar nuestro tráfico, saber cuántos metaleros y rockeros nos sintonizan, y asegurarnos de que la plataforma y la transmisión funcionen al máximo nivel. Puedes leer más detalladamente en nuestra <a href="{{ route('privacy-policy') }}" class="text-lucille-accent hover:underline">Política de Privacidad</a>.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 md:shrink-0 w-full md:w-auto">
                <button type="button" onclick="acceptAllCookies()" class="w-full sm:w-auto rounded-full bg-lucille-accent px-5 py-2.5 text-center text-[10px] sm:text-[11px] font-display uppercase tracking-[0.12em] text-white transition-all duration-300 hover:bg-opacity-90 active:scale-98 shadow-md shadow-lucille-accent/20 shrink-0">
                    Genial, ¡que suene el Rock!
                </button>
                <button type="button" onclick="acceptNecessaryCookies()" class="w-full sm:w-auto rounded-full border border-white/10 bg-white/[0.02] px-5 py-2.5 text-center text-[10px] sm:text-[11px] font-display uppercase tracking-[0.12em] text-[#dcdcdc] transition-all duration-300 hover:border-lucille-accent hover:text-lucille-accent hover:bg-lucille-accent/[0.02] active:scale-98 shrink-0">
                    No, solo necesarias
                </button>
                <button type="button" onclick="openCookieSettingsModal()" class="w-full sm:w-auto whitespace-nowrap text-center text-[10px] sm:text-[11px] font-display uppercase tracking-[0.1em] text-[#7b7b7b] hover:text-[#9aa7b1] transition-colors focus:outline-none mt-1 sm:mt-0">
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
            if (window.SevenRockAnalytics && typeof window.SevenRockAnalytics.setConsent === 'function') {
                window.SevenRockAnalytics.setConsent(prefs);
            } else {
                if (typeof window.gtag === 'function') {
                    window.gtag('consent', 'update', {
                        'analytics_storage': prefs.analytics ? 'granted' : 'denied',
                        'functionality_storage': prefs.personalization ? 'granted' : 'denied'
                    });
                }
            }
        }
    </script>

    <x-home.affiliate-modal />

    @stack('modals')
    @stack('scripts')
</body>
</html>
