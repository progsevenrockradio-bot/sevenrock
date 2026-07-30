@props(['items' => null, 'theme' => null])

@php
    $themeData = is_array($theme ?? null) ? $theme : $themeAppearance;
    $ui = $themeAppearance['ui_texts'];
    $featuredAlbumUrl = $themeData['featured_album_url'] ?? route('discography');
    $items ??= [
        ['label' => 'Inicio', 'route' => 'home'],
        ['label' => 'Multimedia', 'route' => 'multimedia', 'children' => [
            ['label' => 'Hub Multimedia', 'route' => 'multimedia'],
            ['label' => 'Galería de Fotos', 'url' => route('multimedia') . '#galeria'],
            ['label' => 'Videos', 'url' => route('multimedia') . '#videos'],
            ['label' => 'Catálogo de Música', 'url' => route('multimedia') . '#musica'],
            ['label' => 'Podcasts', 'url' => route('programs')],
        ]],
        ['label' => 'Eventos', 'route' => 'events', 'children' => [
            ['label' => 'Proximos eventos', 'route' => 'events.upcoming'],
            ['label' => 'Eventos pasados', 'route' => 'events.past'],
            ['label' => 'Todos los eventos', 'route' => 'events.all'],
            ['label' => 'Evento', 'url' => route('events.single', ['slug' => 'rockness-festival'])],
        ]],
        ['label' => 'Blog', 'route' => 'blog', 'children' => [
            ['label' => 'Ver Blog', 'route' => 'blog'],
            ['label' => 'Entrada', 'url' => route('posts.single', ['year' => '2016', 'month' => '09', 'day' => '06', 'slug' => 'inspiration'])],
        ]],
        ['label' => 'Muro del Rock', 'route' => 'talents.explore', 'children' => [
            ['label' => 'Explorar Bandas', 'route' => 'talents.explore'],
            ['label' => 'Muro de la Comunidad', 'route' => 'comunidad.muro'],
            ['label' => 'Nuevos Lanzamientos', 'route' => 'new-releases.index'],
            ['label' => 'Registrar Banda', 'route' => 'talents.register'],
            ['label' => 'Enviar Maqueta', 'route' => 'submissions.create'],
        ]],
        ['label' => 'Tienda', 'route' => 'shop'],

        ["label" => "Programas", "route" => "programs"],
        ['label' => 'Contacto', 'route' => 'contact'],
        ['label' => 'Iniciar Sesión', 'route' => 'talents.login'],
    ];
    $brandMark = $themeData['visual']['brand_mark'] ?? $themeData['brand_mark'] ?? $themeData['site_name'] ?? 'Seven Rock Radio';
    $brandDisplayMode = $themeData['visual']['brand_display_mode'] ?? $themeData['brand_display_mode'] ?? 'mark';
    $logoUrl = $themeData['media']['logo_url'] ?? $themeData['logo_url'] ?? $themeSettings->logo_url;
    $logoUrl = \App\Support\PublicMediaUrl::normalize($logoUrl) ?? $logoUrl;
    $logoHeight = $themeSettings->logo_height ?? 62;
@endphp

@once
<style>
    :root {
        --logo-normal-height: {{ (int)max(40, min(75, $logoHeight * 0.75)) }}px;
        --logo-sticky-height: {{ (int)max(30, min(50, $logoHeight * 0.55)) }}px;
        --logo-single-normal-height: {{ (int)$logoHeight }}px;
        --logo-single-sticky-height: {{ (int)min(45, $logoHeight) }}px;
    }

    .lucille-brand-logo-both {
        display: block;
        width: auto;
        height: var(--logo-normal-height);
        object-fit: contain;
        flex-shrink: 0;
        transition: height 0.95s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .rocks-header-sticky .lucille-brand-logo-both {
        height: var(--logo-sticky-height);
    }

    .lucille-brand-logo {
        display: block;
        width: auto;
        height: var(--logo-single-normal-height);
        object-fit: contain;
        flex-shrink: 0;
        transition: height 0.95s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .rocks-header-sticky .lucille-brand-logo {
        height: var(--logo-single-sticky-height);
    }

    .brand-mark-container {
        position: relative;
        display: inline-block;
        vertical-align: middle;
        font-family: var(--lucille-brand-font, "Rock Salt"), "Segoe Script", cursive;
        color: var(--color-lucille-accent);
        text-shadow: 0 1.5px 3px rgba(0, 0, 0, 0.3);
        transition: all 0.95s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: left center;
        overflow: visible;
    }

    .brand-mark-container.normal {
        font-size: clamp(0.9rem, 1.6vw, 1.45rem);
        width: 10.5em;
        height: 1.5em;
    }

    .brand-mark-container.sticky-active {
        font-size: 11px;
        width: 58px;
        height: 40px;
        transform: skewX(12deg) translateY(2px);
        text-shadow: 0 0.5px 1px rgba(0, 0, 0, 0.2);
    }

    .brand-word {
        position: absolute;
        display: inline-block;
        white-space: nowrap;
        line-height: 1;
        transition: all 0.95s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: left center;
    }

    /* Normal state positions */
    .brand-mark-container.normal .word-seven {
        left: 0;
        top: 50%;
        transform: translateY(-50%) rotate(0deg);
        transition-delay: 0.18s;
    }

    .brand-mark-container.normal .word-rock {
        left: 3.4em;
        top: 50%;
        transform: translateY(-50%) rotate(0deg);
        transition-delay: 0.09s;
    }

    .brand-mark-container.normal .word-radio {
        left: 6.3em;
        top: 50%;
        transform: translateY(-50%) rotate(0deg);
        transition-delay: 0s;
    }

    /* Sticky active state positions */
    .brand-mark-container.sticky-active .word-seven {
        left: 0;
        top: 0;
        transform: translateY(0) rotate(-4deg);
        transition-delay: 0s;
    }

    .brand-mark-container.sticky-active .word-rock {
        left: 0;
        top: 13px;
        transform: translateY(0) rotate(-4deg);
        transition-delay: 0.09s;
    }

    .brand-mark-container.sticky-active .word-radio {
        left: 0;
        top: 26px;
        transform: translateY(0) rotate(-4deg);
        transition-delay: 0.18s;
    }

    @media (max-width: 767px) {
        .rocks-header-top,
        .rocks-header-sticky {
            height: auto !important;
        }
        .rocks-header-top > div {
            padding-top: 40px !important;
            padding-bottom: 20px !important;
        }
        .rocks-header-sticky > div {
            padding-top: 16px !important;
            padding-bottom: 12px !important;
        }
        #site-content {
            margin-top: 136px !important;
        }
    }
</style>
@endonce

<header
    x-data="rocksNav"
    x-init="init"
    :class="sticky ? 'rocks-header-sticky' : 'rocks-header-top'"
    class="inset-x-0 top-0 z-50 transition-all duration-300"
>
    <div class="absolute inset-0 z-0 bg-cover bg-center md:hidden" style="background-image: linear-gradient(rgba(16, 16, 18, 0.75), rgba(16, 16, 18, 0.75)), var(--lucille-bg-image);" aria-hidden="true"></div>
    <div 
        class="mx-auto flex h-full max-w-[1180px] flex-col justify-center px-4 lg:px-6 md:flex-row md:items-center md:justify-between md:py-0 md:gap-4"
    >
        <div class="flex w-full items-center justify-between md:contents">
            <a href="{{ route('home') }}" class="relative z-10 flex h-full items-center gap-3 py-2 md:py-0" aria-label="{{ $brandMark }} home">
                @if ($brandDisplayMode === 'both' && $logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandMark }}" class="lucille-brand-logo-both" loading="lazy">
                    <span class="brand-mark-container" :class="sticky ? 'sticky-active' : 'normal'">
                        @php
                            $words = preg_split('/\s+/', trim($brandMark));
                            $wordClasses = ['word-seven', 'word-rock', 'word-radio'];
                        @endphp
                        @foreach ($words as $index => $word)
                            <span class="brand-word {{ $wordClasses[$index] ?? 'word-' . $index }}">{{ $word }}</span>
                        @endforeach
                    </span>
                @elseif ($brandDisplayMode === 'logo' && $logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandMark }}" class="lucille-brand-logo" loading="lazy">
                @else
                    <span class="brand-mark-container" :class="sticky ? 'sticky-active' : 'normal'">
                        @php
                            $words = preg_split('/\s+/', trim($brandMark));
                            $wordClasses = ['word-seven', 'word-rock', 'word-radio'];
                        @endphp
                        @foreach ($words as $index => $word)
                            <span class="brand-word {{ $wordClasses[$index] ?? 'word-' . $index }}">{{ $word }}</span>
                        @endforeach
                    </span>
                @endif
            </a>

            <div class="relative z-[60] flex items-center gap-5 text-white">
                <button type="button" class="relative h-11 w-11 lg:hidden mr-2 group" @click="open = ! open" :aria-expanded="open.toString()" aria-label="Toggle menu">
                    <span class="absolute left-1/2 top-4 h-[1.5px] w-5 -translate-x-1/2 rounded-full bg-white transition-all duration-300 ease-out" :class="open ? 'top-1/2 rotate-45' : 'group-hover:-translate-y-0.5'"></span>
                    <span class="absolute left-1/2 top-1/2 h-[1.5px] w-5 -translate-x-1/2 rounded-full bg-white transition-all duration-300 ease-out" :class="open ? 'opacity-0' : ''"></span>
                    <span class="absolute left-1/2 top-7 h-[1.5px] w-5 -translate-x-1/2 rounded-full bg-white transition-all duration-300 ease-out" :class="open ? 'top-1/2 -rotate-45' : 'group-hover:translate-y-0.5'"></span>
                </button>
            </div>
        </div>

        <div class="relative z-10 md:hidden w-full px-1 pb-3 pt-1 text-center">
            <span class="inline-block max-w-full font-display text-[8.5px] uppercase tracking-[.22em] leading-tight text-white/50">
                Todas las épocas del Rock, <span class="text-lucille-accent/70">están aquí</span>
            </span>
        </div>

        <nav class="hidden h-full items-center lg:flex">
            <ul class="flex h-full items-center">
                @foreach ($items as $item)
                    @if (in_array($item['label'], ['Tienda', 'Programas', 'Programa', 'Iniciar Sesión']))
                        @continue
                    @endif
                    <li class="group relative flex h-full items-center">
                        <a
                            href="{{ $item['url'] ?? route($item['route']) }}"
                            class="flex h-full items-center px-1.5 xl:px-4 font-display text-[9px] xl:text-[11px] font-medium uppercase tracking-[.1em] text-white/90 transition-colors duration-300 hover:text-lucille-accent"
                        >
                            {{ $item['label'] }}
                        </a>

                        @if (! empty($item['children']))
                            <ul class="invisible absolute left-0 top-full min-w-48 bg-[rgba(16,16,18,.96)] backdrop-blur-md border border-white/5 py-3 opacity-0 shadow-[0_10px_30px_rgba(0,0,0,.22)] transition-all duration-300 group-hover:visible group-hover:opacity-100">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] ?? route($child['route']) }}" class="block whitespace-nowrap px-5 py-2 text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="hidden items-center gap-2 xl:gap-6 border-l border-white/10 pl-2 xl:pl-6 lg:flex">
            <!-- Programas Icon -->
            <a href="{{ route('programs') }}" class="group text-white/80 transition-colors duration-300 hover:text-lucille-accent" aria-label="Programas" title="Programas">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                    <line x1="12" y1="19" x2="12" y2="23"></line>
                    <line x1="8" y1="23" x2="16" y2="23"></line>
                </svg>
            </a>

            <!-- Tienda Icon -->
            <a href="{{ route('shop') }}" class="group text-white/80 transition-colors duration-300 hover:text-lucille-accent" aria-label="Tienda" title="Tienda">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
            </a>

            {{-- P1-2: Ícono de Carrito con Badge "Próximamente" --}}
            <span class="relative group cursor-pointer flex items-center" title="Carrito — Próximamente" aria-label="Carrito (próximamente disponible)">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50 transition-all duration-300 group-hover:text-lucille-accent group-hover:scale-110">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                {{-- Badge animado --}}
                <span class="absolute -top-1.5 -right-1.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-lucille-accent/70 text-white transition-all duration-300 group-hover:bg-lucille-accent group-hover:scale-110">
                    <span class="font-display text-[7px] leading-none">0</span>
                </span>
                {{-- Tooltip --}}
                <span class="pointer-events-none absolute -bottom-9 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#161618] border border-white/10 px-2.5 py-1 text-[9px] uppercase tracking-[.1em] text-[#ccc] opacity-0 shadow-lg transition-all duration-300 group-hover:opacity-100 group-hover:-translate-y-1">
                    Próximamente
                </span>
            </span>

            <!-- Buscar Icon -->
            <button type="button" class="group text-white/80 transition-colors duration-300 hover:text-lucille-accent" @click="searchOpen = true" aria-label="Buscar" title="Buscar">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>

            <!-- Iniciar Sesión / Mi Cuenta Icon -->
            @auth('talent')
                <a href="{{ route('talents.dashboard') }}" class="group text-white/80 transition-colors duration-300 hover:text-lucille-accent" aria-label="Mi Cuenta" title="Mi Cuenta (Talentos)">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>
            @else
                <a href="{{ route('talents.login') }}" class="group text-white/80 transition-colors duration-300 hover:text-lucille-accent" aria-label="Iniciar Sesión" title="Iniciar Sesión (Talentos)">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:scale-110">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                </a>
            @endauth

            <a href="{{ route('app.download') }}" class="ml-2 flex items-center gap-1.5 rounded-full border border-lucille-accent/60 px-3 py-1.5 text-[9px] font-display font-medium uppercase tracking-[.1em] text-lucille-accent transition-all duration-300 hover:bg-lucille-accent hover:text-white" aria-label="Descargar la aplicación de la radio" title="Descargar App">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Descargar App</span>
            </a>
        </div>
    </div>

    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-[rgba(16,16,18,.98)] backdrop-blur-md pt-[100px] px-5 pb-6 lg:hidden overflow-y-auto flex flex-col">
        <nav class="flex-1 flex flex-col min-h-[calc(100vh-140px)]">
            <ul class="mx-auto w-full max-w-[1180px] divide-y divide-white/5 flex-1">
                @foreach ($items as $item)
                    @if($item['label'] === 'Iniciar Sesión')
                        @continue
                    @endif
                    @php
                        $isActive = isset($item['route']) && request()->routeIs($item['route']);
                    @endphp
                    <li x-data="{ childOpen: false }" class="py-1">
                        <div class="flex items-center justify-between">
                            <a
                                href="{{ $item['url'] ?? route($item['route']) }}"
                                class="block py-3 font-display text-sm uppercase tracking-[.08em] transition-colors duration-200 {{ $isActive ? 'text-lucille-accent' : 'text-white' }}"
                                @if($isActive) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                            @if (! empty($item['children']))
                                {{-- P2-4: Área táctil mínima 44×44px en el botón de submenú --}}
                                <button type="button" class="flex items-center justify-center w-11 h-11 text-white/60 hover:text-lucille-accent transition-colors duration-300" @click.prevent="childOpen = ! childOpen" aria-label="Toggle submenu">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300" :class="childOpen ? 'rotate-180 text-lucille-accent' : ''">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                            @endif
                        </div>
                        @if (! empty($item['children']))
                            <ul x-show="childOpen" x-transition class="pb-2 pl-5">
                                @foreach ($item['children'] as $child)
                                    @php
                                        $isChildActive = isset($child['route']) && request()->routeIs($child['route']);
                                    @endphp
                                    <li>
                                        <a
                                            href="{{ $child['url'] ?? route($child['route']) }}"
                                            class="block py-2 text-[13px] transition-colors duration-200 {{ $isChildActive ? 'text-lucille-accent' : 'text-[#b7b7b7]' }}"
                                            @if($isChildActive) aria-current="page" @endif
                                        >{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            
            <div class="mt-8 mb-24 flex flex-col gap-3 px-8">
                <a href="{{ route('app.download') }}" class="group flex w-full items-center justify-center gap-2 rounded-full bg-lucille-accent px-4 py-2.5 text-[10px] font-display font-medium uppercase tracking-[.1em] text-white shadow-lg shadow-lucille-accent/20 transition-all duration-300 hover:bg-[#d42c24] hover:shadow-lucille-accent/40 active:scale-95">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform duration-300 group-hover:-translate-y-0.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Descargar App</span>
                </a>
                <a href="{{ route('talents.login') }}" class="flex w-full items-center justify-center rounded-full border border-white/20 bg-transparent px-4 py-2.5 text-[10px] font-display font-medium uppercase tracking-[.1em] text-white transition-all duration-300 hover:bg-white/5 active:scale-95">
                    Iniciar Sesión
                </a>
            </div>
        </nav>
    </div>

    <div x-cloak x-show="searchOpen" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-6">
        <button type="button" class="absolute right-8 top-7 font-display text-3xl text-white" @click="searchOpen = false" aria-label="Close search">&times;</button>
        <form method="GET" action="{{ route('search') }}" class="w-full max-w-3xl">
            <input type="search" name="q" placeholder="{{ $ui['search_placeholder'] }}" class="w-full border-0 border-b border-white/40 bg-transparent px-0 py-5 font-display text-4xl uppercase tracking-[.04em] text-white placeholder:text-white/45 focus:border-lucille-accent focus:outline-none">
        </form>
    </div>
</header>
