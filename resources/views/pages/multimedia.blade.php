{{-- ======================================================================
     MULTIMEDIA HUB - Seven Rock Radio
     /multimedia · SiteController@multimedia
     ====================================================================== --}}
<x-layouts.site
    title="Seven Rock Radio – Multimedia Hub"
    description="Todo el universo multimedia de Seven Rock Radio en un solo lugar: radio en vivo, podcasts, música, videos y galería de fotos."
>
@php
    $fallbackImage = asset('assets/lucille/logo.png');
    $lightboxImages = collect($galleryImages)
        ->map(fn ($img) => ['src' => data_get($img, 'url', ''), 'caption' => (data_get($img, 'title') ?? data_get($img, 'filename', '')) . ' — ' . data_get($img, 'talent.band_name', 'Talento')])
        ->values();
@endphp

<x-sections.page-heading
    title="Multimedia Hub"
    subtitle="Todo en un solo lugar: radio, podcasts, música, videos y galería"
    image="assets/lucille/microphone-1206364_1920.jpg"
    overlay="rgba(10,10,11,.88)"
/>

<section class="lucille-content-box pb-36"
    x-data="{
        activeTab: 'radio',
        lightboxOpen: false,
        lightboxIndex: 0,
        lightboxImages: {{ Js::from($lightboxImages) }},
        get lightboxCurrent() { return this.lightboxImages[this.lightboxIndex] ?? null; },
        songSearch: '',

        playTrack(track) {
            window.dispatchEvent(new CustomEvent('play-multimedia-track', { detail: track }));
        },
        showImage(idx) { this.lightboxIndex = idx; this.lightboxOpen = true; },
        nextImage() { this.lightboxIndex = (this.lightboxIndex+1)%this.lightboxImages.length; },
        prevImage() { this.lightboxIndex = (this.lightboxIndex-1+this.lightboxImages.length)%this.lightboxImages.length; },
        closeGallery() { this.lightboxOpen = false; },
    }"
    @keydown.escape.window="drawerOpen = false; lightboxOpen && closeGallery()"
    @keydown.arrow-right.window="lightboxOpen && nextImage()"
    @keydown.arrow-left.window="lightboxOpen && prevImage()"
>

    {{-- ─────────── TAB NAVIGATION ─────────── --}}
    <div class="sticky top-[var(--nav-height,64px)] z-30 bg-[#0a0a0b]/90 backdrop-blur-md border-b border-[#1a1a1a] -mx-6 px-6 mb-10">
        <div class="flex gap-1 overflow-x-auto py-3">
            @foreach ([
                ['key'=>'radio',    'icon'=>'📻', 'label'=>'Radio En Vivo'],
                ['key'=>'podcasts', 'icon'=>'🎙️', 'label'=>'Podcasts'],
                ['key'=>'musica',   'icon'=>'🎵', 'label'=>'Música'],
                ['key'=>'videos',   'icon'=>'🎬', 'label'=>'Videos'],
                ['key'=>'galeria',  'icon'=>'📷', 'label'=>'Galería'],
            ] as $tab)
                <button type="button"
                    @click="activeTab = '{{ $tab['key'] }}'"
                    :class="activeTab === '{{ $tab['key'] }}' ? 'border-lucille-accent text-white bg-lucille-accent/10' : 'border-transparent text-[#666] hover:text-[#aaa] hover:border-[#333]'"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-lg border font-display text-xs uppercase tracking-[.1em] transition-all duration-200 shrink-0"
                    id="tab-{{ $tab['key'] }}"
                ><span>{{ $tab['icon'] }}</span><span>{{ $tab['label'] }}</span></button>
            @endforeach
        </div>
    </div>

    {{-- ═══════════ TAB 1: RADIO EN VIVO ═══════════ --}}
    <div id="radio" x-show="activeTab === 'radio'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-8 flex items-center gap-3">
            <span class="w-1 h-6 bg-lucille-accent rounded-full"></span>Radio En Vivo
        </h2>
        <div class="grid md:grid-cols-2 gap-6">
            {{-- Station card --}}
            <div class="bg-[#101012] border border-[#242424] rounded-xl p-6 flex flex-col gap-5">
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 rounded-xl border-2 border-lucille-accent/40 overflow-hidden shadow-[0_0_20px_rgba(195,39,32,.2)] shrink-0">
                        <img src="https://c30.radioboss.fm/stationlogo/569.jpg" alt="Seven Rock Radio" class="w-full h-full object-cover">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-2 h-2 rounded-full bg-lucille-accent animate-pulse"></span>
                            <span class="text-[10px] text-lucille-accent uppercase tracking-[.15em] font-display">En Vivo Ahora</span>
                        </div>
                        <h3 class="font-display text-xl uppercase tracking-wider text-white">Seven Rock Radio</h3>
                        <p class="text-sm text-[#7b7b7b] mt-0.5">Todas las épocas del rock, están aquí</p>
                    </div>
                </div>
                {{-- Now Playing --}}
                <div class="bg-[#0a0a0b] border border-[#1f1f1f] rounded-lg p-4 flex items-center gap-3">
                    <img id="rbcloud_np_cover_mm" src="https://c30.radioboss.fm/w/artwork/569.jpg" width="56" height="56" alt="Carátula" class="rounded border border-[#333] w-14 h-14 object-cover shrink-0">
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] uppercase tracking-[.1em] text-[#555] mb-1 font-display">Suena ahora</div>
                        <div id="rbcloud_np_artist_mm" class="font-display text-[13px] text-white truncate">Cargando...</div>
                        <div id="rbcloud_np_title_mm" class="text-[11px] text-[#777] truncate mt-0.5"></div>
                    </div>
                </div>
                <div id="rbcloud_np_a16176" class="hidden"></div>
                <div id="rbcloud_np_t16176" class="hidden"></div>
                <img id="rbcloud_np_c16176" src="https://c30.radioboss.fm/w/artwork/569.jpg" class="hidden" alt="">
                <script src="https://c30.radioboss.fm/w/nowplaying2.js?u=569&wid=16176&tf=0" defer></script>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    setInterval(function () {
                        var a=document.getElementById('rbcloud_np_a16176'),t=document.getElementById('rbcloud_np_t16176'),c=document.getElementById('rbcloud_np_c16176');
                        var ma=document.getElementById('rbcloud_np_artist_mm'),mt=document.getElementById('rbcloud_np_title_mm'),mc=document.getElementById('rbcloud_np_cover_mm');
                        if(a&&ma) ma.textContent=a.textContent;
                        if(t&&mt) mt.textContent=t.textContent;
                        if(c&&mc&&c.src!==mc.src) mc.src=c.src;
                    }, 3000);
                });
                </script>
                <button type="button"
                    class="w-full py-3 rounded-xl bg-lucille-accent font-display text-sm uppercase tracking-[.1em] text-white shadow-[0_4px_20px_rgba(195,39,32,.3)] hover:bg-lucille-accent/90 active:scale-98 transition-all duration-200"
                    @click="playTrack({ src: 'live', type: 'live', title: 'Seven Rock Radio', subtitle: 'En Vivo', image: 'https://c30.radioboss.fm/stationlogo/569.jpg' })">
                    <span x-show="!isLive || !playing">▶ Escuchar en Vivo</span>
                    <span x-show="isLive && playing">⏸ Pausar Radio</span>
                </button>
            </div>

            {{-- Programas de hoy --}}
            @php
                $todayKey = ['DOMINGO','LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'][date('w')];
                $todayGroup = collect($programsByDay)->firstWhere('day', $todayKey);
            @endphp
            <div class="bg-[#101012] border border-[#242424] rounded-xl p-6">
                <h4 class="font-display text-sm uppercase tracking-[.1em] text-[#dcdcdc] mb-4 flex items-center gap-2">
                    <span class="w-1 h-4 bg-lucille-accent rounded-full"></span>Programas de Hoy
                </h4>
                @if ($todayGroup && !empty($todayGroup['programs']))
                    <div class="divide-y divide-[#1a1a1a]">
                        @foreach ($todayGroup['programs'] as $prog)
                            <a href="{{ route('programs.detail', ['identifier' => $prog['archive_identifier'] ?? $prog['slug']]) }}"
                               class="flex items-center gap-3 py-3 group/p hover:pl-1 transition-all duration-200">
                                <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 border border-[#2a2a2a]">
                                    <img src="{{ $prog['cover'] ?: $fallbackImage }}" alt="{{ $prog['name'] }}" class="w-full h-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-display text-[12px] uppercase tracking-[.06em] text-[#dcdcdc] group-hover/p:text-lucille-accent transition-colors truncate">{{ $prog['name'] }}</div>
                                    @if ($prog['host'] ?? $prog['conductor'] ?? '')
                                        <div class="text-[10px] text-[#666] mt-0.5">{{ $prog['host'] ?? $prog['conductor'] }}</div>
                                    @endif
                                </div>
                                @if ($prog['hora'] ?? '')
                                    <span class="text-[10px] font-mono text-[#555] shrink-0">{{ substr($prog['hora'], 0, 5) }} hs</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[#555] py-4">No hay programas registrados para hoy.</p>
                @endif
                <a href="{{ route('programs') }}" class="mt-4 inline-block text-[11px] uppercase tracking-[.15em] text-lucille-accent hover:underline font-display">Ver parrilla completa →</a>
            </div>
        </div>
    </div>

    {{-- ═══════════ TAB 2: PODCASTS ═══════════ --}}
    <div id="podcasts" x-show="activeTab === 'podcasts'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" x-data="{ expandedProgram: null }">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-2 flex items-center gap-3">
            <span class="w-1 h-6 bg-lucille-accent rounded-full"></span>Podcasts
        </h2>
        <p class="text-sm text-[#7b7b7b] mb-8 ml-4">Toca la portada para ver los episodios · Haz clic en ▶ para reproducir</p>

        @if (empty($groupedEpisodes))
            <div class="py-16 text-center text-sm text-[#7b7b7b]">No hay episodios disponibles todavía.</div>
        @else
            <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($groupedEpisodes as $programName => $eps)
                    @php
                        $firstEp        = $eps[0] ?? [];
                        $progImage      = $firstEp['image'] ?? $fallbackImage;
                        $progCount      = count($eps);
                        $progId         = 'prog-' . Str::slug($programName);
                        $latestDate     = $firstEp['date'] ?? '';
                        $visibleEps     = array_slice($eps, 0, 3);
                        $progIdentifier = $programSlugMap[$programName] ?? Str::slug($programName);
                        $progUrl        = route('programs.detail', ['identifier' => $progIdentifier]);
                        $hasMore        = $progCount > 3;
                        // Enriquecer con datos del MasterProgram asociado
                        $matchedProg    = collect($programsByDay)->flatMap(fn($d) => $d['programs'])->firstWhere('name', $programName);
                        $progDesc       = $matchedProg['description'] ?? '';
                        $progHost       = $matchedProg['host'] ?? $matchedProg['conductor'] ?? '';
                        $progGenre      = $matchedProg['genre'] ?? '';
                    @endphp
                    <div class="podcast-card-premium">
                        <div class="relative group/cover aspect-square overflow-hidden bg-[#080808]">
                            <img src="{{ $progImage }}" alt="{{ $programName }}" width="640" height="480"
                                class="w-full h-full object-contain p-3 transition duration-500 ease-out group-hover/cover:scale-105"
                                loading="lazy" decoding="async">
                            <div class="absolute inset-0 bg-black/70 opacity-0 group-hover/cover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-4">
                                @if (!empty($firstEp['src']))
                                    <button type="button"
                                        class="w-12 h-12 rounded-full bg-lucille-accent text-white flex items-center justify-center shadow-[0_0_20px_rgba(195,39,32,.4)] hover:scale-110 active:scale-95 transition-all duration-200"
                                        @click.prevent="playTrack({ type:'podcast', src:'{{ addslashes($firstEp['src']) }}', title:'{{ addslashes($firstEp['episode_title'] ?? $firstEp['title'] ?? '') }}', subtitle:'{{ addslashes($programName) }}', image:'{{ $progImage }}', archive_url:'{{ $firstEp['archive_url'] ?? '' }}' })" title="Reproducir último episodio">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,3 20,12 6,21"/></svg>
                                    </button>
                                @endif
                                <button type="button"
                                    class="w-12 h-12 rounded-full bg-[#222] border border-[#444] text-[#ccc] flex items-center justify-center hover:bg-[#333] hover:text-white hover:scale-110 active:scale-95 transition-all duration-200"
                                    @click.prevent="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Program info --}}
                        <div class="px-4 py-3.5 border-b border-[#242424]">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-display text-[13px] uppercase tracking-[.06em] text-[#dcdcdc] leading-tight truncate">{{ $programName }}</h3>
                                    @if ($progHost) <p class="text-[10px] text-[#888] mt-0.5">{{ $progHost }}</p> @endif
                                    @if ($progGenre)
                                        <span class="inline-block mt-1 text-[9px] uppercase tracking-[.08em] text-lucille-accent/70 border border-lucille-accent/20 px-1.5 py-0.5 rounded">{{ $progGenre }}</span>
                                    @endif
                                    @if ($progDesc) <p class="mt-1.5 text-[10px] text-[#666] line-clamp-2 leading-relaxed">{{ $progDesc }}</p> @endif
                                    <p class="mt-1.5 text-[10px] text-[#555]">{{ $progCount }} {{ $progCount===1 ? 'episodio' : 'episodios' }}@if($latestDate) · {{ $latestDate }}@endif</p>
                                </div>
                                <button type="button" class="shrink-0 text-[#555] hover:text-[#aaa] transition-colors mt-0.5"
                                    @click="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'">
                                    <svg class="w-4 h-4 transition-transform duration-300" :class="expandedProgram === '{{ $progId }}' ? 'rotate-180 text-lucille-accent' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                            </div>
                        </div>

                        {{-- Episodes panel --}}
                        <div x-show="expandedProgram === '{{ $progId }}'"
                            x-transition:enter="transition-all duration-300 ease-out" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-[900px]"
                            x-transition:leave="transition-all duration-200 ease-in"  x-transition:leave-start="opacity-100 max-h-[900px]" x-transition:leave-end="opacity-0 max-h-0"
                            class="border-t border-[#1f1f1f] divide-y divide-[#1f1f1f] overflow-hidden bg-[#080808]">
                            @foreach ($visibleEps as $episode)
                                @php
                                    $epTitle   = $episode['episode_title'] ?? $episode['title'] ?? '';
                                    $epSrc     = $episode['src'] ?? '';
                                    $epDate    = $episode['date'] ?? '';
                                    $epSummary = $episode['summary'] ?? '';
                                @endphp
                                <button type="button" class="flex items-start gap-3 w-full px-4 py-3 text-left transition-all duration-200 hover:bg-[#121212] group/ep"
                                    @click="playTrack({ type:'podcast', src:'{{ addslashes($epSrc) }}', title:'{{ addslashes($epTitle) }}', subtitle:'{{ addslashes($programName) }}', image:'{{ $progImage }}', archive_url:'{{ $episode['archive_url'] ?? '' }}' })">
                                    <div class="mt-0.5 shrink-0 w-7 h-7 rounded-full border border-[#333] bg-[#151515] flex items-center justify-center text-[#888] transition-all duration-200 group-hover/ep:bg-lucille-accent group-hover/ep:border-lucille-accent group-hover/ep:text-white">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,3 20,12 6,21"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-[12px] text-[#ccc] group-hover/ep:text-lucille-accent transition-colors line-clamp-1 leading-snug font-medium">{{ $epTitle ?: 'Episodio' }}</span>
                                            @if ($epDate) <span class="shrink-0 text-[9px] text-[#666] whitespace-nowrap font-mono">{{ $epDate }}</span> @endif
                                        </div>
                                        @if ($epSummary) <p class="mt-1 text-[10px] text-[#666] line-clamp-1">{{ $epSummary }}</p> @endif
                                    </div>
                                </button>
                            @endforeach
                            @if ($hasMore)
                                <a href="{{ $progUrl }}" class="flex items-center justify-center gap-1.5 w-full px-4 py-3 text-[10px] uppercase tracking-[.08em] text-[#777] bg-[#101010] border-t border-[#1f1f1f] hover:bg-[#181818] hover:text-lucille-accent transition-all duration-200">
                                    Ver más ({{ $progCount - 3 }} {{ $progCount-3===1?'episodio':'episodios' }}) →
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 3: CATÁLOGO DE MÚSICA ═══════════ --}}
    <div id="musica" x-show="activeTab === 'musica'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] flex items-center gap-3">
                <span class="w-1 h-6 bg-lucille-accent rounded-full"></span>Catálogo de Música
            </h2>
            <div class="relative max-w-xs w-full">
                <input type="text" x-model="songSearch" placeholder="Buscar canción, artista..."
                    class="w-full bg-[#101012] border border-[#242424] rounded-lg px-4 py-2 text-sm text-[#dcdcdc] placeholder-[#555] focus:outline-none focus:border-lucille-accent/50 transition-colors">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#444]">🔍</span>
            </div>
        </div>
        @if ($songs->isEmpty())
            <div class="py-16 text-center text-sm text-[#7b7b7b]">No hay canciones publicadas todavía.</div>
        @else
            <div class="divide-y divide-[#1a1a1a]">
                @foreach ($songs as $index => $song)
                    @php
                        $coverUrl  = $song->cover_url ?: $fallbackImage;
                        $audioUrl  = \App\Support\PublicMediaUrl::normalizePublicUrl($song->audio_url) ?: ($song->audio_url ?? '');
                        $bandName  = $song->bandProfile?->name ?? '';
                        $hasLyrics = !empty($song->lyrics);
                        $hasBio    = !empty($song->band_info) || !empty($bandName);
                        $searchStr = strtolower($song->title . ' ' . $song->artist . ' ' . ($song->album ?? ''));
                    @endphp
                    <div class="group/song flex items-center gap-4 py-3 px-2 rounded-lg hover:bg-[#101012] transition-all duration-200 cursor-pointer"
                        x-show="songSearch === '' || '{{ addslashes($searchStr) }}'.includes(songSearch.toLowerCase())"
                        @click="playTrack({
                            type: 'song',
                            src: '{{ addslashes($audioUrl) }}',
                            title: '{{ addslashes($song->title) }}',
                            subtitle: '{{ addslashes($song->artist) }}',
                            image: '{{ $coverUrl }}',
                            album: '{{ addslashes($song->album ?? '') }}',
                            lyrics: {{ $hasLyrics ? Js::from($song->lyrics) : 'null' }},
                            bandInfo: {{ $hasBio ? Js::from($song->band_info ?? '') : 'null' }},
                            bandName: '{{ addslashes($bandName) }}',
                            bandMembers: {{ Js::from($song->band_members ?? []) }},
                            socialLinks: {{ Js::from(is_array($song->social_links ?? null) ? $song->social_links : []) }}
                        })">
                        <span class="text-[12px] font-mono text-[#444] w-6 text-center shrink-0 group-hover/song:hidden">{{ $index + 1 }}</span>
                        <span class="hidden group-hover/song:flex text-lucille-accent w-6 justify-center shrink-0">▶</span>
                        <div class="w-10 h-10 rounded overflow-hidden border border-[#2a2a2a] shrink-0">
                            <img src="{{ $coverUrl }}" alt="{{ $song->title }}" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-display text-[13px] text-[#dcdcdc] truncate group-hover/song:text-lucille-accent transition-colors">{{ $song->title }}</div>
                            <div class="text-[11px] text-[#777] truncate">{{ $song->artist }}@if($song->album) · <em>{{ $song->album }}</em>@endif</div>
                        </div>
                        <div class="hidden sm:flex items-center gap-2 shrink-0">
                            @if ($hasLyrics) <span class="text-[9px] uppercase tracking-[.08em] text-[#555] border border-[#222] px-1.5 py-0.5 rounded">Letras</span> @endif
                            @if ($hasBio)    <span class="text-[9px] uppercase tracking-[.08em] text-[#555] border border-[#222] px-1.5 py-0.5 rounded">Bio</span>    @endif
                        </div>
                        @if ($song->duration_seconds)
                            <span class="text-[11px] font-mono text-[#555] shrink-0 w-10 text-right">{{ sprintf('%d:%02d', floor($song->duration_seconds/60), $song->duration_seconds%60) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 4: VIDEOS ═══════════ --}}
    <div id="videos" x-show="activeTab === 'videos'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-8 flex items-center gap-3">
            <span class="w-1 h-6 bg-lucille-accent rounded-full"></span>Videos
        </h2>
        @if ($videos->isEmpty())
            <div class="py-16 text-center text-sm text-[#7b7b7b]">No hay videos publicados todavía.</div>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    @php
                        $embedUrl = '';
                        if (!empty($video->youtube_url)) {
                            preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video->youtube_url, $m);
                            if ($m[1] ?? '') $embedUrl = 'https://www.youtube.com/embed/'.$m[1].'?rel=0';
                        }
                    @endphp
                    <div class="group">
                        @if ($embedUrl)
                            <div class="relative aspect-video bg-[#080808] overflow-hidden rounded-t-lg border border-[#242424]">
                                <iframe src="{{ $embedUrl }}" title="{{ $video->title }}" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                            </div>
                        @else
                            <div class="relative aspect-video bg-cover bg-center overflow-hidden rounded-t-lg border border-[#242424]" style="background-image:url('{{ $video->image_url }}')">
                                <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                                    <a href="{{ $video->youtube_url }}" target="_blank" class="w-14 h-14 rounded-full bg-lucille-accent/90 flex items-center justify-center hover:scale-110 transition-all duration-200">
                                        <span class="text-white text-xl ml-0.5">▶</span>
                                    </a>
                                </div>
                            </div>
                        @endif
                        <div class="bg-[#101012] border border-t-0 border-[#242424] rounded-b-lg px-4 py-3">
                            <h3 class="font-display text-sm uppercase tracking-[.08em] text-[#dcdcdc] truncate group-hover:text-lucille-accent transition-colors">{{ $video->title }}</h3>
                            @if ($video->summary) <p class="text-[11px] text-[#666] mt-1 line-clamp-1">{{ $video->summary }}</p> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 5: GALERÍA ═══════════ --}}
    <div id="galeria" x-show="activeTab === 'galeria'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-8 flex items-center gap-3">
            <span class="w-1 h-6 bg-lucille-accent rounded-full"></span>Galería de Fotos
        </h2>
        @if (empty($galleryImages))
            <div class="py-16 text-center text-sm text-[#7b7b7b]">No hay imágenes publicadas todavía.</div>
        @else
            <div class="columns-1 gap-[5px] md:columns-2 lg:columns-3">
                @foreach ($galleryImages as $idx => $image)
                    @php $talent = data_get($image, 'talent'); @endphp
                    <a href="{{ data_get($image,'url') }}" class="lucille-gallery-tile group relative mb-[5px] block overflow-hidden bg-[#1d1d1d]" @click.prevent="showImage({{ $idx }})">
                        <img src="{{ data_get($image,'url') }}" alt="{{ data_get($image,'title', data_get($image,'filename','')) }}" loading="lazy" class="w-full opacity-50 transition duration-500 ease-out group-hover:scale-[1.03] group-hover:opacity-100">
                        <span class="absolute inset-0 bg-[rgba(7,16,33,.4)] opacity-0 transition duration-300 group-hover:opacity-100"></span>
                        <span class="absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 whitespace-nowrap font-display text-xs font-bold uppercase tracking-[5px] text-white opacity-0 transition duration-300 group-hover:opacity-100">
                            {{ data_get($image,'title',data_get($image,'filename','')) }}@if($talent) — {{ data_get($talent,'band_name') }}@endif
                        </span>
                    </a>
                @endforeach
            </div>
            <template x-teleport="body">
                <div x-cloak x-show="lightboxOpen">
                    <div class="lucille-lightbox-overlay" x-transition.opacity @click="closeGallery()"></div>
                    <div class="lucille-lightbox" x-transition.opacity role="dialog" aria-modal="true"
                        @touchstart.passive="touchStartX=$event.touches[0].clientX"
                        @touchend.passive="if(($event.changedTouches[0].clientX-touchStartX)<-50)nextImage();else if(($event.changedTouches[0].clientX-touchStartX)>50)prevImage()">
                        <button type="button" class="lucille-lightbox-close" @click="closeGallery()" aria-label="Cerrar"></button>
                        <div class="lucille-lightbox-frame" @click.stop>
                            <img :src="lightboxCurrent?.src" :alt="lightboxCurrent?.caption" class="lucille-lightbox-image" x-transition.opacity.duration.300ms loading="lazy">
                            <button type="button" class="lucille-lightbox-nav lucille-lightbox-prev" @click="prevImage()" aria-label="Anterior"><span class="lucille-lightbox-arrow"></span></button>
                            <button type="button" class="lucille-lightbox-nav lucille-lightbox-next" @click="nextImage()" aria-label="Siguiente"><span class="lucille-lightbox-arrow"></span></button>
                            <div class="lucille-lightbox-data">
                                <span class="lucille-lightbox-caption" x-text="lightboxCurrent?.caption"></span>
                                <span class="lucille-lightbox-number" x-text="`Imagen ${lightboxIndex+1} de ${lightboxImages.length}`"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SLIDE-UP DRAWER: Letras + Info Banda
         ════════════════════════════════════════════════════════════ --}}
    <template x-teleport="body">
        <div x-cloak x-show="drawerOpen && playerVisible && activeTrack"
            x-transition:enter="transition-all ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition-all ease-in duration-200"  x-transition:leave-start="translate-y-0 opacity-100"  x-transition:leave-end="translate-y-full opacity-0"
            class="fixed bottom-[88px] left-0 right-0 z-[110] flex justify-center px-4 pointer-events-none">
            <div class="w-full max-w-3xl pointer-events-auto bg-[#0b0b0c]/96 backdrop-blur-xl border border-white/8 rounded-2xl shadow-[0_-10px_60px_rgba(0,0,0,0.8)] overflow-hidden">
                <div class="flex border-b border-white/5">
                    <button type="button" @click="drawerTab='letras'" :class="drawerTab==='letras'?'text-white border-lucille-accent':'text-[#555] border-transparent'" class="flex-1 py-3 font-display text-xs uppercase tracking-[.1em] border-b-2 transition-all duration-200">🎵 Letras</button>
                    <button type="button" @click="drawerTab='banda'"  :class="drawerTab==='banda' ?'text-white border-lucille-accent':'text-[#555] border-transparent'" class="flex-1 py-3 font-display text-xs uppercase tracking-[.1em] border-b-2 transition-all duration-200">🎸 Info Banda</button>
                    <button type="button" @click="drawerOpen=false" class="px-4 text-[#444] hover:text-[#888] transition-colors text-lg" aria-label="Cerrar">✕</button>
                </div>
                <div x-show="drawerTab==='letras'" class="max-h-64 overflow-y-auto p-5">
                    <template x-if="activeTrack?.lyrics">
                        <pre class="text-sm text-[#ccc] leading-relaxed whitespace-pre-wrap font-sans" x-text="activeTrack.lyrics"></pre>
                    </template>
                    <template x-if="!activeTrack?.lyrics">
                        <p class="text-sm text-[#555] text-center py-8">No hay letra disponible para esta canción.</p>
                    </template>
                </div>
                <div x-show="drawerTab==='banda'" class="max-h-64 overflow-y-auto p-5 space-y-3">
                    <template x-if="activeTrack?.bandName">
                        <h4 class="font-display text-base uppercase tracking-[.1em] text-white" x-text="activeTrack.bandName"></h4>
                    </template>
                    <template x-if="activeTrack?.bandInfo">
                        <p class="text-sm text-[#aaa] leading-relaxed" x-text="activeTrack.bandInfo"></p>
                    </template>
                    <template x-if="activeTrack?.bandMembers && activeTrack.bandMembers.length">
                        <div>
                            <p class="text-[10px] uppercase tracking-[.1em] text-[#555] mb-2">Integrantes</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="m in activeTrack.bandMembers" :key="m">
                                    <span class="text-[11px] text-[#ccc] border border-[#2a2a2a] rounded px-2 py-1" x-text="m"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="activeTrack?.socialLinks && Object.keys(activeTrack.socialLinks??{}).length">
                        <div class="flex gap-3 flex-wrap">
                            <template x-for="[key,url] in Object.entries(activeTrack.socialLinks??{})" :key="key">
                                <a :href="url" target="_blank" rel="noopener" class="text-[10px] uppercase tracking-[.08em] text-lucille-accent border border-lucille-accent/30 hover:bg-lucille-accent/10 rounded px-2 py-1 transition-colors" x-text="key"></a>
                            </template>
                        </div>
                    </template>
                    <template x-if="!activeTrack?.bandInfo && (!activeTrack?.bandMembers || !activeTrack.bandMembers.length)">
                        <p class="text-sm text-[#555] text-center py-8">No hay información de banda disponible.</p>
                    </template>
                </div>
            </div>
        </div>
    </template>



</section>

@push('styles')
<style>
.mm-eq-bar {
    display: inline-block;
    width: 2px;
    background: #c32720;
    border-radius: 1px;
    animation: mm-eq 0.6s ease-in-out infinite alternate;
    min-height: 2px;
}
@keyframes mm-eq { from { height: 2px; } to { height: 12px; } }
</style>
@endpush

</x-layouts.site>
