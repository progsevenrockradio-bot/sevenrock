{{--
    Vista: PWA Inicio / Feed
    Layout: layouts.pwa (standalone PWA)
    Datos: $posts, $episodes, $artists, $newReleases
--}}
<x-layouts.pwa title="Inicio — Seven Rock Radio">

<div class="min-h-screen" style="background: #121212;">

    {{-- ═══════════════════════════════════════════════
         HERO: BANNER "EN VIVO AHORA"
    ═══════════════════════════════════════════════ --}}
    <section class="relative overflow-hidden">
        {{-- Fondo con gradiente rock --}}
        <div class="relative h-52 flex items-end"
             style="background: linear-gradient(135deg, #1a0000 0%, #2d0505 40%, #121212 100%);">

            {{-- Textura de ruido sutil --}}
            <div class="absolute inset-0 opacity-5"
                 style="background-image: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%224%22 height=%224%22><rect width=%224%22 height=%224%22 fill=%22%23ffffff%22 opacity=%220.15%22/><rect width=%221%22 height=%221%22 fill=%22%23ffffff%22 opacity=%220.3%22/></svg>');"></div>

            {{-- Artwork difuminado de fondo --}}
            <div class="absolute inset-0 opacity-20"
                 style="background: radial-gradient(ellipse at 80% 50%, #DC2626 0%, transparent 70%);"></div>

            {{-- Contenido del hero --}}
            <div class="relative z-10 w-full px-5 pb-5">
                {{-- Badge En Vivo --}}
                <div class="flex items-center gap-2 mb-2">
                    <span class="flex items-center gap-1.5 bg-red-600/20 border border-red-600/50 text-red-400 text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                        En el Aire
                    </span>
                </div>

                <h1 class="font-display text-3xl font-bold text-white leading-none mb-1">
                    SEVEN ROCK RADIO
                </h1>
                <p class="text-sm text-gray-400 mb-4">Tu radio de rock online · 24/7</p>

                {{-- Botón Play Live --}}
                <button @click="playLive()"
                        class="btn-accent flex items-center gap-2 px-6 py-3 text-sm font-bold uppercase tracking-wider">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    Escuchar Ahora
                </button>
            </div>
        </div>

        {{-- Difuminado inferior para transición suave --}}
        <div class="h-8" style="background: linear-gradient(to bottom, transparent, #121212);"></div>
    </section>

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: ÚLTIMAS NOTICIAS (Carrusel horizontal táctil)
    ═══════════════════════════════════════════════ --}}
    <section class="px-4 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="section-heading">Últimas Noticias</h2>
            <a href="/blog" class="text-xs text-red-500 font-semibold uppercase tracking-wider hover:text-red-400 transition-colors">
                Ver todo →
            </a>
        </div>

        <div class="scroll-snap-x">
            @forelse($posts as $post)
            <div class="pwa-card w-64 cursor-pointer"
                 onclick="window.location.href='{{ $post->url }}'">
                {{-- Imagen del post --}}
                <div class="relative h-36 overflow-hidden bg-[#1e1e1e]">
                    @if($post->featured_image_url)
                        <img src="{{ $post->featured_image_url }}"
                             alt="{{ $post->title }}"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-[#0a0a0b]">
                            <svg class="w-10 h-10 text-red-600/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </div>
                    @endif

                    {{-- Categoría badge --}}
                    @if(!empty($post->categories[0]))
                        <span class="absolute top-2 left-2 bg-red-600/90 text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                            {{ $post->categories[0] }}
                        </span>
                    @endif
                </div>

                {{-- Texto --}}
                <div class="p-3">
                    <h3 class="font-display text-sm font-semibold text-white leading-snug line-clamp-2 mb-1">
                        {{ $post->title }}
                    </h3>
                    <p class="text-[11px] text-gray-500">
                        {{ $post->published_at?->diffForHumans() ?? '—' }}
                    </p>
                </div>
            </div>
            @empty
                <p class="text-sm text-gray-500 py-4 px-2">No hay noticias disponibles.</p>
            @endforelse
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: ÚLTIMOS EPISODIOS DE PODCAST
    ═══════════════════════════════════════════════ --}}
    <section class="px-4 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="section-heading">Últimos Podcasts</h2>
            <a href="/app/podcasts"
               class="pwa-nav-link text-xs text-red-500 font-semibold uppercase tracking-wider hover:text-red-400 transition-colors"
               data-href="/app/podcasts">
                Ver todo →
            </a>
        </div>

        @if(!empty($episodes))
        <div class="space-y-2">
            @foreach(array_slice($episodes, 0, 5) as $ep)
            <div class="pwa-card flex items-center gap-3 p-3 cursor-pointer"
                 @click="playEpisode({{ json_encode([
                     'src'     => $ep['src'] ?? '',
                     'title'   => $ep['title'] ?? 'Episodio',
                     'program' => $ep['program'] ?? 'Podcast',
                     'cover'   => $ep['cover'] ?? asset('assets/lucille/podcats.webp'),
                 ]) }})">

                {{-- Carátula del episodio --}}
                <div class="w-14 h-14 rounded-lg overflow-hidden bg-[#0a0a0b] shrink-0 border border-[#2a2a2a]">
                    <img src="{{ $ep['cover'] ?? asset('assets/lucille/podcats.webp') }}"
                         alt="{{ $ep['title'] ?? 'Podcast' }}"
                         class="w-full h-full object-contain"
                         loading="lazy"
                         onerror="this.src='{{ asset('assets/lucille/podcats.webp') }}'">
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="font-display text-sm font-semibold text-white truncate leading-tight">
                        {{ $ep['title'] ?? 'Episodio' }}
                    </p>
                    <p class="text-xs text-gray-500 truncate mt-0.5">
                        {{ $ep['program'] ?? '' }}
                        @if(!empty($ep['date'])) · {{ $ep['date'] }} @endif
                    </p>
                </div>

                {{-- Icono play --}}
                <div class="w-9 h-9 rounded-full border border-red-600/50 flex items-center justify-center shrink-0 hover:bg-red-600/10 transition-colors">
                    <svg class="w-4 h-4 text-red-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="pwa-card p-6 text-center">
            <p class="text-sm text-gray-500">Cargando episodios...</p>
        </div>
        @endif
    </section>

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: NEW RELEASES (Carrusel horizontal)
    ═══════════════════════════════════════════════ --}}
    @if($newReleases->isNotEmpty())
    <section class="px-4 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="section-heading">Lo Último</h2>
            <a href="/new-releases" class="text-xs text-red-500 font-semibold uppercase tracking-wider hover:text-red-400 transition-colors">
                Ver todo →
            </a>
        </div>

        <div class="scroll-snap-x">
            @foreach($newReleases as $release)
            <div class="w-40 cursor-pointer group"
                 @click="playSong({{ json_encode([
                     'src'    => $release->audio_path ?? '',
                     'title'  => $release->title,
                     'artist' => $release->artist_name,
                     'cover'  => $release->cover_image ? asset($release->cover_image) : asset('assets/lucille/album3.jpg'),
                 ]) }})">
                <div class="relative aspect-square rounded-xl overflow-hidden bg-[#1e1e1e] mb-2">
                    @if($release->cover_image)
                        <img src="{{ asset($release->cover_image) }}"
                             alt="{{ $release->title }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-900/30 to-gray-900">
                            <svg class="w-10 h-10 text-red-600/50" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Overlay play al hover --}}
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <p class="font-display text-xs font-semibold text-white truncate">{{ $release->title }}</p>
                <p class="text-[11px] text-gray-500 truncate">{{ $release->artist_name }}</p>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════════
         SECCIÓN: ARTISTAS DESTACADOS (Círculos)
    ═══════════════════════════════════════════════ --}}
    @if($artists->isNotEmpty())
    <section class="px-4 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="section-heading">Artistas</h2>
            <a href="/app/library"
               class="pwa-nav-link text-xs text-red-500 font-semibold uppercase tracking-wider hover:text-red-400 transition-colors"
               data-href="/app/library">
                Ver todos →
            </a>
        </div>

        <div class="scroll-snap-x">
            @foreach($artists as $artist)
            <a href="{{ route('agency.public-profile', $artist->slug) }}"
               class="flex flex-col items-center gap-2 w-20 cursor-pointer group">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-[#1e1e1e] border-2 border-[#2a2a2a] group-hover:border-red-600/50 transition-colors shrink-0">
                    @if($artist->logo_path)
                        <img src="{{ \App\Support\PublicMediaUrl::normalizePublicUrl($artist->logo_path) ?? asset($artist->logo_path) }}"
                             alt="{{ $artist->name }}"
                             class="w-full h-full object-cover"
                             loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-900/20 to-gray-900">
                            <svg class="w-7 h-7 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                    @endif
                </div>
                <span class="text-[10px] text-gray-300 text-center truncate w-full leading-tight">{{ $artist->name }}</span>
            </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Espaciado final --}}
    <div class="h-4"></div>
</div>

</x-layouts.pwa>
