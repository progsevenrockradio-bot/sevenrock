{{--
    Vista: PWA Mi Música / Biblioteca
    Layout: layouts.pwa
    Datos: $artists (Collection), $newReleases (Collection)
--}}
<x-layouts.pwa title="Mi Música — Seven Rock Radio">

<div class="min-h-screen pb-4" style="background: #121212;">

    {{-- ═══════════════════════════════════════════════
         CABECERA
    ═══════════════════════════════════════════════ --}}
    <div class="px-4 pt-4 pb-4">
        <h1 class="font-display text-2xl font-bold text-white tracking-wide">Mi Música</h1>
        <p class="text-sm text-gray-500 mt-1">Artistas y lanzamientos de Seven Rock Radio</p>
    </div>

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: EPISODIOS FAVORITOS (localStorage)
    ═══════════════════════════════════════════════ --}}
    <section class="mb-6" x-data x-show="$store.favorites && $store.favorites.items.length > 0">
        <div class="flex items-center justify-between px-4 mb-3">
            <h2 class="section-heading">❤ Mis Favoritos</h2>
            <button class="text-xs text-gray-600 hover:text-red-400 transition-colors"
                    @click="if(confirm('¿Borrar todos los favoritos?')) $store.favorites.clear()">
                Borrar todo
            </button>
        </div>

        <div class="space-y-2 px-4">
            <template x-for="ep in $store.favorites.items" :key="ep.id">
                <div class="pwa-card flex items-center gap-3 p-3 cursor-pointer group"
                     @click="playEpisode(ep)">
                    {{-- Carátula --}}
                    <div class="w-12 h-12 rounded-xl overflow-hidden bg-[#1e1e1e] shrink-0">
                        <img :src="ep.cover || '{{ asset('assets/lucille/podcats.webp') }}'"
                             :alt="ep.title"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="font-display text-sm font-semibold text-white truncate leading-tight" x-text="ep.title"></p>
                        <p class="text-xs text-gray-500 truncate mt-0.5" x-text="ep.program || ep.artist || ''"></p>
                    </div>

                    {{-- Botones --}}
                    <div class="flex items-center gap-2 shrink-0">
                        {{-- Quitar de favoritos --}}
                        <button @click.stop="$store.favorites.toggle(ep)"
                                class="w-7 h-7 flex items-center justify-center text-red-500 hover:text-red-400 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                        </button>

                        {{-- Play --}}
                        <div class="w-8 h-8 rounded-full border border-red-600/40 flex items-center justify-center hover:bg-red-600/10 transition-colors">
                            <svg class="w-3.5 h-3.5 text-red-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: ARTISTAS
    ═══════════════════════════════════════════════ --}}
    @if($artists->isNotEmpty())
    <section class="mb-8">
        <div class="flex items-center justify-between px-4 mb-3">
            <h2 class="section-heading">Artistas</h2>
        </div>

        {{-- Grid de artistas (2 columnas) --}}
        <div class="grid grid-cols-2 gap-3 px-4">
            @foreach($artists as $artist)
            <a href="{{ route('agency.public-profile', $artist->slug) }}"
               class="pwa-card flex items-center gap-3 p-3 cursor-pointer group">

                {{-- Avatar circular --}}
                <div class="w-14 h-14 rounded-full overflow-hidden bg-[#1e1e1e] shrink-0 border-2 border-[#2a2a2a] group-hover:border-red-600/40 transition-colors">
                    @if($artist->logo_path)
                        <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($artist->logo_path) ?? asset($artist->logo_path) }}"
                             alt="{{ $artist->name }}"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-900/20 to-[#1e1e1e]">
                            <svg class="w-7 h-7 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="min-w-0">
                    <p class="font-display text-sm font-semibold text-white truncate">{{ $artist->name }}</p>
                    <p class="text-[10px] text-gray-500 mt-0.5">Artista</p>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: NEW RELEASES
    ═══════════════════════════════════════════════ --}}
    @if($newReleases->isNotEmpty())
    <section class="mb-8">
        <div class="flex items-center justify-between px-4 mb-3">
            <h2 class="section-heading">Nuevos Lanzamientos</h2>
            <a href="/new-releases" class="text-xs text-red-500 font-semibold uppercase tracking-wider hover:text-red-400 transition-colors">
                Ver todo →
            </a>
        </div>

        <div class="space-y-2 px-4">
            @foreach($newReleases as $release)
            <div class="pwa-card flex items-center gap-3 p-3 cursor-pointer group"
                 @click="playSong({{ Js::from([
                     'src'    => $release->audio_path ?? '',
                     'title'  => $release->title,
                     'artist' => $release->artist_name,
                     'cover'  => $release->cover_image ? asset($release->cover_image) : asset('assets/lucille/album3.jpg'),
                 ]) }})">

                {{-- Carátula --}}
                <div class="relative w-14 h-14 rounded-xl overflow-hidden bg-[#1e1e1e] shrink-0">
                    @if($release->cover_image)
                        <img src="{{ asset($release->cover_image) }}"
                             alt="{{ $release->title }}"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-900/20 to-gray-900">
                            <svg class="w-6 h-6 text-red-600/40" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Overlay play --}}
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="font-display text-sm font-semibold text-white truncate leading-tight">
                        {{ $release->title }}
                    </p>
                    <p class="text-xs text-gray-500 truncate mt-0.5">{{ $release->artist_name }}</p>
                    @if($release->released_at)
                        <p class="text-[10px] text-gray-600 mt-0.5">
                            {{ $release->released_at->format('Y') }}
                        </p>
                    @endif
                </div>

                {{-- Controles --}}
                <div class="flex items-center gap-2 shrink-0">
                    {{-- Link a la página del lanzamiento --}}
                    <a href="{{ route('new-releases.single', $release->slug) }}"
                       class="w-8 h-8 rounded-full border border-[#3a3a3a] flex items-center justify-center hover:border-gray-500 transition-colors"
                       @click.stop>
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </a>

                    {{-- Play --}}
                    @if($release->audio_path)
                    <div class="w-9 h-9 rounded-full bg-red-600/10 border border-red-600/30 flex items-center justify-center hover:bg-red-600/20 transition-colors">
                        <svg class="w-4 h-4 text-red-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                    @else
                    <div class="w-9 h-9 rounded-full border border-[#2a2a2a] flex items-center justify-center opacity-30 cursor-not-allowed">
                        <svg class="w-4 h-4 text-gray-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════════
         PLACEHOLDER: PLAYLISTS (Futuro)
    ═══════════════════════════════════════════════ --}}
    <section class="px-4 mb-4">
        <h2 class="section-heading mb-3">Mis Playlists</h2>

        <div class="pwa-card p-8 text-center border border-dashed border-[#2a2a2a]" style="background: transparent;">
            <svg class="w-12 h-12 text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
            </svg>
            <p class="text-sm text-gray-600 mb-1">Próximamente</p>
            <p class="text-xs text-gray-700">Las playlists personales estarán disponibles pronto.</p>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         ACCESO RÁPIDO: SECCIONES DEL SITIO
    ═══════════════════════════════════════════════ --}}
    <section class="px-4 mt-6">
        <h2 class="section-heading mb-3">Explorar</h2>

        <div class="grid grid-cols-2 gap-3">
            @php
            $quickLinks = [
                ['icon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3', 'label' => 'Discografía', 'href' => '/discography', 'color' => 'from-purple-900/40'],
                ['icon' => 'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'label' => 'Videos', 'href' => '/videos', 'color' => 'from-blue-900/40'],
                ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Eventos', 'href' => '/events', 'color' => 'from-green-900/40'],
                ['icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z', 'label' => 'Blog', 'href' => '/blog', 'color' => 'from-orange-900/40'],
            ];
            @endphp

            @foreach($quickLinks as $link)
            <a href="{{ $link['href'] }}"
               class="pwa-card flex items-center gap-3 p-4 cursor-pointer group hover:border hover:border-red-600/20 transition-colors"
               style="background: linear-gradient(135deg, var(--pwa-card) 0%, transparent 100%);">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                     style="background: linear-gradient(135deg, {{ str_replace(['from-', '/40'], ['rgba(', ', 0.4)'], $link['color']) }} 0%, transparent 100%);">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/>
                    </svg>
                </div>
                <span class="font-display text-sm font-semibold text-white group-hover:text-red-400 transition-colors">
                    {{ $link['label'] }}
                </span>
            </a>
            @endforeach
        </div>
    </section>

</div>

</x-layouts.pwa>
