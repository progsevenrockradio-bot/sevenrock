@props(['items' => null, 'theme' => null])

@php
    $ui = $themeAppearance['ui_texts'];
    $items ??= [
        ['label' => 'Inicio', 'route' => 'home'],
        ['label' => 'Multimedia', 'route' => 'discography', 'children' => [
            ['label' => 'Discografía', 'route' => 'discography'],
            ['label' => 'Videos', 'route' => 'videos'],
            ['label' => 'Galería', 'route' => 'gallery'],
            ['label' => 'Álbum', 'url' => route('albums.single', ['slug' => 'nightride'])],
            ['label' => 'Video', 'url' => route('videos.single', ['slug' => 'gold-on-the-ceiling'])],
            ['label' => 'Álbum de fotos', 'route' => 'gallery.green-day'],
        ]],
        ['label' => 'Eventos', 'route' => 'events', 'children' => [
            ['label' => 'Proximos eventos', 'route' => 'events'],
            ['label' => 'Eventos pasados', 'route' => 'events'],
            ['label' => 'Todos los eventos', 'route' => 'events'],
            ['label' => 'Evento', 'url' => route('events.single', ['slug' => 'rockness-festival'])],
        ]],
        ['label' => 'Blog', 'route' => 'blog', 'children' => [
            ['label' => 'Ver Blog', 'route' => 'blog'],
            ['label' => 'Entrada', 'url' => route('posts.single', ['year' => '2016', 'month' => '09', 'day' => '06', 'slug' => 'inspiration'])],
        ]],
        // Muro del Rock disabled for .com
        ['label' => 'Tienda', 'route' => 'shop'],

        ["label" => "Programas", "route" => "programs"],
        ['label' => 'Contacto', 'route' => 'contact'],
    ];
    $themeData = is_array($theme ?? null) ? $theme : $themeAppearance;
    $brandMark = $themeData['visual']['brand_mark'] ?? $themeData['brand_mark'] ?? $themeData['site_name'] ?? 'Seven Rock Radio';
    $brandDisplayMode = $themeData['visual']['brand_display_mode'] ?? $themeData['brand_display_mode'] ?? 'mark';
    $logoUrl = $themeData['media']['logo_url'] ?? $themeData['logo_url'] ?? $themeSettings->logo_url;
@endphp

<header
    x-data="rocksNav"
    x-init="init"
    :class="sticky ? 'rocks-header-sticky' : 'rocks-header-top'"
    class="inset-x-0 top-0 z-50 transition-all duration-300"
>
    <div class="mx-auto flex h-full max-w-[1180px] flex-col justify-center px-5 py-2 lg:px-8 md:flex-row md:items-center md:justify-between md:py-0">
        <div class="flex w-full items-center justify-between md:contents">
            <a href="{{ route('home') }}" class="flex h-full items-center py-2 md:py-0" aria-label="{{ $brandMark }} home">
                @if ($brandDisplayMode === 'logo' && $logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandMark }}" class="lucille-brand-logo" loading="lazy">
                @else
                    <span class="lucille-brand-mark">{{ $brandMark }}</span>
                @endif
            </a>

            <div class="flex items-center gap-5 text-white">
                <button type="button" class="relative h-10 w-10 lg:hidden" @click="open = ! open" :aria-expanded="open.toString()" aria-label="Toggle menu">
                    <span class="absolute left-1/2 top-[12px] h-px w-9 -translate-x-1/2 bg-white transition duration-200" :class="open ? 'top-1/2 rotate-45' : ''"></span>
                    <span class="absolute left-1/2 top-1/2 h-px w-7 -translate-x-1/2 bg-white transition duration-200" :class="open ? 'opacity-0' : ''"></span>
                    <span class="absolute left-1/2 top-[28px] h-px w-9 -translate-x-1/2 bg-white transition duration-200" :class="open ? 'top-1/2 -rotate-45' : ''"></span>
                </button>
            </div>
        </div>

        <div class="md:hidden w-full px-1 pb-1 pt-1 text-center">
            <span class="inline-block max-w-full font-display text-[10px] uppercase tracking-[.24em] leading-tight text-white/80">
                Todas las épocas del Rock, <span class="text-lucille-accent">están aquí</span>
            </span>
        </div>

        <nav class="hidden h-full items-center lg:flex">
            <ul class="flex h-full items-center">
                @foreach ($items as $item)
                    <li class="group relative flex h-full items-center">
                        <a
                            href="{{ $item['url'] ?? route($item['route']) }}"
                            class="flex h-full items-center px-[18px] font-display text-xs font-light uppercase tracking-[.08em] text-white transition-colors duration-300 hover:text-lucille-accent"
                        >
                            {{ $item['label'] }}
                        </a>

                        @if (($item['label'] ?? '') === 'Muro del Rock')
                            <ul class="invisible absolute left-0 top-full min-w-48 bg-[rgba(8,26,36,.96)] py-3 opacity-0 shadow-[0_10px_30px_rgba(0,0,0,.22)] transition-all duration-300 group-hover:visible group-hover:opacity-100">
                                @auth('talent')
                                    <li>
                                        <a href="{{ route('talents.dashboard') }}" class="block whitespace-nowrap px-5 py-2 text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                            Mi Panel
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('talents.logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full whitespace-nowrap px-5 py-2 text-left text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                                Cerrar sesión
                                            </button>
                                        </form>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ route('talents.login') }}" class="block whitespace-nowrap px-5 py-2 text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                            Acceder
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('talents.register') }}" class="block whitespace-nowrap px-5 py-2 text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                            Registrarse
                                        </a>
                                    </li>
                                @endauth
                                <li>
                                    <a href="{{ route('talents.explore') }}" class="block whitespace-nowrap px-5 py-2 text-[13px] text-[#dddddd] transition-colors duration-300 hover:text-lucille-accent">
                                        Índice de bandas
                                    </a>
                                </li>
                            </ul>
                        @elseif (! empty($item['children']))
                            <ul class="invisible absolute left-0 top-full min-w-48 bg-[rgba(8,26,36,.96)] py-3 opacity-0 shadow-[0_10px_30px_rgba(0,0,0,.22)] transition-all duration-300 group-hover:visible group-hover:opacity-100">
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

        <button type="button" class="hidden text-lg text-white transition-colors duration-300 hover:text-lucille-accent lg:block" @click="searchOpen = true" aria-label="Open search">
            &#9906;
        </button>
    </div>

    <div x-cloak x-show="open" x-transition.opacity class="border-t border-[#003954] bg-[rgba(8,26,36,.9)] px-5 py-4 lg:hidden">
        <nav>
            <ul class="mx-auto max-w-[1180px] divide-y divide-[#003954]">
                @foreach ($items as $item)
                    <li x-data="{ childOpen: false }" class="py-1">
                        <div class="flex items-center justify-between">
                            <a href="{{ $item['url'] ?? route($item['route']) }}" class="block py-3 font-display text-sm uppercase tracking-[.08em] text-white">{{ $item['label'] }}</a>
                            @if (($item['label'] ?? '') === 'Muro del Rock')
                                <button type="button" class="px-4 py-3 text-white" @click.prevent="childOpen = ! childOpen" aria-label="Toggle submenu">+</button>
                            @elseif (! empty($item['children']))
                                <button type="button" class="px-4 py-3 text-white" @click.prevent="childOpen = ! childOpen" aria-label="Toggle submenu">+</button>
                            @endif
                        </div>
                        @if (($item['label'] ?? '') === 'Muro del Rock')
                            <ul x-show="childOpen" x-transition class="pb-2 pl-5">
                                @auth('talent')
                                    <li><a href="{{ route('talents.dashboard') }}" class="block py-2 text-[13px] text-[#b7b7b7]">Mi Panel</a></li>
                                    <li>
                                        <form method="POST" action="{{ route('talents.logout') }}">
                                            @csrf
                                            <button type="submit" class="block py-2 text-[13px] text-[#b7b7b7]">Cerrar sesión</button>
                                        </form>
                                    </li>
                                @else
                                    <li><a href="{{ route('talents.login') }}" class="block py-2 text-[13px] text-[#b7b7b7]">Acceder</a></li>
                                    <li><a href="{{ route('talents.register') }}" class="block py-2 text-[13px] text-[#b7b7b7]">Registrarse</a></li>
                                @endauth
                                <li><a href="{{ route('talents.explore') }}" class="block py-2 text-[13px] text-[#b7b7b7]">Índice de bandas</a></li>
                            </ul>
                        @elseif (! empty($item['children']))
                            <ul x-show="childOpen" x-transition class="pb-2 pl-5">
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['url'] ?? route($child['route']) }}" class="block py-2 text-[13px] text-[#b7b7b7]">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>

    <div x-cloak x-show="searchOpen" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-6">
        <button type="button" class="absolute right-8 top-7 font-display text-3xl text-white" @click="searchOpen = false" aria-label="Close search">&times;</button>
        <form class="w-full max-w-3xl">
            <input type="search" placeholder="{{ $ui['search_placeholder'] }}" class="w-full border-0 border-b border-white/40 bg-transparent px-0 py-5 font-display text-4xl uppercase tracking-[.04em] text-white placeholder:text-white/45 focus:border-lucille-accent focus:outline-none">
        </form>
    </div>
</header>
