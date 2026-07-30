{{--
    Vista: PWA Podcasts
    Layout: layouts.pwa
    Datos: $programs (Collection), $episodes (array)
--}}
<x-layouts.pwa title="Podcasts — Seven Rock Radio">

<div class="min-h-screen pb-4" style="background: #121212;" x-data="{
    search: '',
    activeProgram: null,
    get filteredPrograms() {
        if (!this.search) return {{ Js::from($programs->values()->all()) }};
        const q = this.search.toLowerCase();
        return {{ Js::from($programs->values()->all()) }}.filter(p =>
            p.title.toLowerCase().includes(q) ||
            (p.host && p.host.toLowerCase().includes(q))
        );
    }
}">

    {{-- ═══════════════════════════════════════════════
         CABECERA FIJA DE SECCIÓN
    ═══════════════════════════════════════════════ --}}
    <div class="sticky top-0 z-20 pt-4 pb-3 px-4" style="background: rgba(18,18,18,0.95); backdrop-filter: blur(12px);">
        <h1 class="font-display text-2xl font-bold text-white tracking-wide mb-3">Podcasts</h1>

        {{-- Barra de búsqueda --}}
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="search"
                   x-model="search"
                   placeholder="Buscar programa..."
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl text-sm text-white placeholder-gray-600 outline-none border border-[#2a2a2a] focus:border-red-600/50 transition-colors"
                   style="background: #1e1e1e;">
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         FEATURED: ÚLTIMO EPISODIO
    ═══════════════════════════════════════════════ --}}
    @if(!empty($episodes[0]))
    @php $featured = $episodes[0]; @endphp
    <div class="px-4 mb-6">
        <div class="relative rounded-2xl overflow-hidden cursor-pointer group"
             style="background: linear-gradient(135deg, #1a0000 0%, #2d0505 100%);"
             @click="playEpisode({{ Js::from([
                 'src'     => $featured['src'] ?? '',
                 'title'   => $featured['title'] ?? 'Episodio destacado',
                 'program' => $featured['program'] ?? 'Podcast',
                 'cover'   => $featured['cover'] ?? asset('assets/lucille/podcats.webp'),
             ]) }})">

            {{-- Imagen de fondo difuminada --}}
            @if(!empty($featured['cover']))
            <div class="absolute inset-0 opacity-15"
                 style="background-image: url('{{ $featured['cover'] }}'); background-size: cover; background-position: center; filter: blur(20px);"></div>
            @endif

            <div class="relative z-10 flex gap-4 p-4">
                {{-- Portada --}}
                <div class="w-20 h-20 rounded-xl overflow-hidden bg-[#0a0a0b] shrink-0 shadow-lg border border-[#2a2a2a]">
                    <img src="{{ $featured['cover'] ?? asset('assets/lucille/podcats.webp') }}"
                         alt="{{ $featured['title'] ?? 'Podcast' }}"
                         class="w-full h-full object-contain"
                         loading="lazy"
                         onerror="this.src='{{ asset('assets/lucille/podcats.webp') }}'">
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <span class="text-[10px] text-red-400 font-bold uppercase tracking-widest">Destacado</span>
                    <h3 class="font-display text-base font-bold text-white leading-snug mt-0.5 line-clamp-2">
                        {{ $featured['title'] ?? 'Episodio destacado' }}
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">{{ $featured['program'] ?? '' }}</p>

                    {{-- Botón play --}}
                    <button class="btn-accent flex items-center gap-1.5 px-4 py-1.5 text-xs mt-3 font-bold uppercase tracking-wider">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        Reproducir
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         LISTA DE PROGRAMAS
    ═══════════════════════════════════════════════ --}}
    {{-- Banner de Instalación (Oculto en PWA instalada) --}}
    <div class="px-4 mb-6 hide-in-pwa">
        <div class="bg-gradient-to-r from-red-600/20 to-[#1e1e1e] border border-red-600/30 rounded-2xl p-4 flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-600/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-display font-bold text-white text-sm">Seven Rock en tu Móvil</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Instala la App oficial</p>
                </div>
            </div>
            <button @click="installPwa()" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
                Instalar
            </button>
        </div>
    </div>

    <div class="px-4">
        <h2 class="section-heading mb-3">Programas</h2>

        {{-- Lista filtrable --}}
        <div class="space-y-2">
            <template x-for="program in filteredPrograms" :key="program.id">
                <div>
                    {{-- Tarjeta de programa --}}
                    <div class="pwa-card cursor-pointer"
                         @click="activeProgram = activeProgram === program.id ? null : program.id">

                        <div class="flex items-center gap-3 p-3">
                            {{-- Portada --}}
                            <div class="w-14 h-14 rounded-xl overflow-hidden bg-[#0a0a0b] shrink-0 border border-[#2a2a2a]">
                                <img :src="program.cover || '{{ asset('assets/lucille/podcats.webp') }}'"
                                     :alt="program.title"
                                     class="w-full h-full object-contain"
                                     loading="lazy"
                                     x-on:error="$el.src='{{ asset('assets/lucille/podcats.webp') }}'">
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-display text-sm font-semibold text-white leading-tight" x-text="program.title"></p>
                                <p class="text-xs text-gray-400 truncate mt-0.5" x-text="program.host || program.schedule || ''"></p>
                                <p class="text-[10px] text-gray-500 mt-0.5" x-text="program.day ? `${program.day} · ${program.hour}` : ''"></p>
                            </div>

                            {{-- Flecha expand --}}
                            <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0"
                                 :class="activeProgram === program.id ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>

                        {{-- Descripción expandida --}}
                        <div x-show="activeProgram === program.id"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="px-3 pb-3 border-t border-[#1e1e1e]">

                            <p class="text-xs text-gray-400 mt-2 leading-relaxed" x-text="program.description || 'Sin descripción disponible.'"></p>

                            {{-- Botones de acción --}}
                            <div class="flex gap-2 mt-3">
                                {{-- Ver episodios --}}
                                <a :href="'/programas/' + program.archive_identifier"
                                   class="flex-1 text-center text-xs text-white border border-[#3a3a3a] rounded-lg py-2 hover:border-red-600/50 hover:bg-red-600/5 transition-colors font-semibold"
                                   x-show="program.archive_identifier">
                                    Ver episodios
                                </a>

                                {{-- Play del último episodio en la lista --}}
                                <button class="flex items-center gap-1 btn-accent px-4 py-2 text-xs"
                                        @click.stop="playEpisode({
                                            src: '',
                                            title: program.title + ' — Último episodio',
                                            program: program.title,
                                            cover: program.cover
                                        })">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    Play
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Sin resultados --}}
            <div x-show="filteredPrograms.length === 0" class="pwa-card p-8 text-center">
                <svg class="w-10 h-10 text-gray-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-gray-400">No se encontraron programas.</p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════
         TODOS LOS EPISODIOS RECIENTES
    ═══════════════════════════════════════════════ --}}
    @if(!empty($episodes) && count($episodes) > 1)
    <div class="px-4 mt-8">
        <h2 class="section-heading mb-3">Episodios Recientes</h2>

        <div class="space-y-2">
            @foreach(array_slice($episodes, 1, 10) as $ep)
            @php
                $epData = [
                    'id'      => $ep['id']      ?? md5(($ep['title'] ?? '') . ($ep['program'] ?? '')),
                    'src'     => $ep['src']     ?? '',
                    'title'   => $ep['title']   ?? 'Episodio',
                    'program' => $ep['program'] ?? 'Podcast',
                    'artist'  => $ep['program'] ?? 'Podcast',
                    'cover'   => $ep['cover']   ?? asset('assets/lucille/podcats.webp'),
                ];
            @endphp
            <div class="pwa-card flex items-center gap-3 p-3 cursor-pointer"
                 @click="playEpisode({{ Js::from($epData) }})">

                <div class="w-12 h-12 rounded-lg overflow-hidden bg-[#0a0a0b] shrink-0 border border-[#2a2a2a]">
                    <img src="{{ $ep['cover'] ?? asset('assets/lucille/podcats.webp') }}"
                         alt="{{ $ep['title'] ?? '' }}"
                         class="w-full h-full object-contain"
                         loading="lazy"
                         onerror="this.src='{{ asset('assets/lucille/podcats.webp') }}'">
                </div>

                <div class="flex-1 min-w-0">
                    <p class="font-display text-sm font-semibold text-white truncate leading-tight">
                        {{ $ep['title'] ?? 'Episodio' }}
                    </p>
                    <p class="text-xs text-gray-400 truncate mt-0.5">
                        {{ $ep['program'] ?? '' }}
                        @if(!empty($ep['date'])) · {{ $ep['date'] }} @endif
                    </p>
                </div>

                {{-- Favorito + Play --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    {{-- Botón corazón favorito --}}
                    <button @click.stop="$store.favorites.toggle({{ Js::from($epData) }})"
                            class="w-7 h-7 flex items-center justify-center transition-colors"
                            :class="$store.favorites.has('{{ $epData['id'] }}') ? 'text-red-500' : 'text-gray-500 hover:text-red-400'">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </button>

                    {{-- Botón play --}}
                    <div class="w-8 h-8 rounded-full border border-red-600/40 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-red-500 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif


</div>

</x-layouts.pwa>
