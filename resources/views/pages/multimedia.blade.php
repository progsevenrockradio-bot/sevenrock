{{-- ======================================================================
     MULTIMEDIA - Seven Rock Radio
     /multimedia · SiteController@multimedia
     ====================================================================== --}}
<x-layouts.site
    title="Seven Rock Radio – Multimedia"
    description="Todo el universo multimedia de Seven Rock Radio en un solo lugar: radio en vivo, podcasts, música, videos y galería de fotos."
>
@php
    $fallbackImage = data_get($themeAppearance ?? [], 'media.logo_url') ?: asset('assets/lucille/pedalboard-1511069_1920.jpg');
    $multimediaTitle = data_get($themeAppearance ?? [], 'ui_texts.multimedia_title') ?: 'Multimedia';
    $multimediaSubtitle = data_get($themeAppearance ?? [], 'ui_texts.multimedia_subtitle') ?: 'Radio en vivo, podcasts, música, videos y galería';
    $lightboxImages = collect($galleryImages)
        ->map(fn ($img) => ['src' => data_get($img, 'url', ''), 'caption' => (data_get($img, 'title') ?? data_get($img, 'filename', '')) . ' — ' . data_get($img, 'talent.band_name', 'Talento')])
        ->values();
@endphp

<x-sections.page-heading
    :title="$multimediaTitle"
    :subtitle="$multimediaSubtitle"
    image="assets/lucille/microphone-1206364_1920.jpg"
    overlay="rgba(16,16,18,.82)"
    :compact="true"
/>

<section class="mx-auto max-w-[1180px] px-5 pt-8 pb-36"
    x-data="{
        activeTab: 'radio',
        lightboxOpen: false,
        lightboxIndex: 0,
        lightboxImages: {{ Js::from($lightboxImages) }},
        get lightboxCurrent() { return this.lightboxImages[this.lightboxIndex] ?? null; },
        songSearch: '',
        currentlyPlayingUrl: '',
        drawerOpen: false,
        drawerTab: 'letras',
        playerVisible: false,
        activeTrack: null,

        init() {
            const validTabs = ['radio', 'podcasts', 'musica', 'videos', 'galeria'];
            const syncFromHash = (e) => {
                let h = '';
                if (e && e.state && e.state.tab && validTabs.includes(e.state.tab)) {
                    h = e.state.tab;
                } else {
                    h = (window.location.hash || '').replace('#', '').toLowerCase();
                }
                if (validTabs.includes(h)) {
                    this.activeTab = h;
                } else if (!h) {
                    this.activeTab = 'radio';
                }
            };
            syncFromHash();
            if (!history.state || !history.state.tab) {
                history.replaceState({ tab: this.activeTab }, '', window.location.href);
            }
            window.addEventListener('popstate', syncFromHash);
            window.addEventListener('hashchange', syncFromHash);
        },

        switchTab(tabKey) {
            if (this.activeTab === tabKey) return;
            this.activeTab = tabKey;
            const currentHash = (window.location.hash || '').replace('#', '').toLowerCase();
            if (currentHash !== tabKey) {
                history.pushState({ tab: tabKey }, '', '#' + tabKey);
            }
        },

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
    @sr-catalog-playing.window="currentlyPlayingUrl = $event.detail.audioUrl"
    @sr-catalog-stopped.window="currentlyPlayingUrl = ''"
>

    {{-- ─────────── NAVEGACIÓN DE PESTAÑAS ─────────── --}}
    <div class="sticky top-[var(--nav-height,64px)] z-30 -mx-5 mb-8 border-b border-white/10 bg-[#141416]/95 px-5 backdrop-blur-md">
        <div class="flex gap-1.5 overflow-x-auto py-2.5" role="tablist" aria-label="Secciones de multimedia">
            @foreach ([
                [
                    'key' => 'radio',
                    'label' => 'Radio en vivo',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />',
                ],
                [
                    'key' => 'podcasts',
                    'label' => 'Podcasts',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />',
                ],
                [
                    'key' => 'musica',
                    'label' => 'Música',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z" />',
                ],
                [
                    'key' => 'videos',
                    'label' => 'Videos',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />',
                ],
                [
                    'key' => 'galeria',
                    'label' => 'Galería',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />',
                ],
            ] as $tab)
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === '{{ $tab['key'] }}' ? 'true' : 'false'"
                    aria-controls="{{ $tab['key'] }}"
                    @click="switchTab('{{ $tab['key'] }}')"
                    :class="activeTab === '{{ $tab['key'] }}'
                        ? 'border-lucille-accent bg-lucille-accent/15 text-white'
                        : 'border-transparent text-[#b8b8b8] hover:border-white/15 hover:bg-white/[0.03] hover:text-white'"
                    class="flex shrink-0 items-center gap-2 rounded-sm border px-4 py-2 font-display text-xs uppercase tracking-[.1em] transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                    id="tab-{{ $tab['key'] }}"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        {!! $tab['svg'] !!}
                    </svg>
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ═══════════ TAB 1: RADIO EN VIVO ═══════════ --}}
    <div id="radio" role="tabpanel" aria-labelledby="tab-radio" x-show="activeTab === 'radio'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="mb-6 flex items-center gap-3 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
            <span class="h-5 w-1 bg-lucille-accent"></span>Radio en vivo
        </h2>
        <div class="grid gap-6 md:grid-cols-2">
            {{-- Tarjeta de estación --}}
            <div class="lucille-flat-surface flex flex-col justify-between gap-5 p-6">
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-4">
                        <div class="h-20 w-20 shrink-0 overflow-hidden bg-[#111]">
                            <img src="https://c30.radioboss.fm/stationlogo/569.jpg" alt="Seven Rock Radio" class="h-full w-full object-contain">
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="h-2 w-2 animate-pulse rounded-full bg-lucille-accent"></span>
                                <span class="font-display text-[10px] uppercase tracking-[.16em] text-[var(--lucille-accent-readable,#ff5b52)]">Señal en directo</span>
                            </div>
                            <h3 class="font-display text-xl uppercase tracking-wider text-white">Seven Rock Radio</h3>
                            <p class="mt-0.5 text-sm text-[#a3a3a3]">Todas las épocas del rock, están aquí</p>
                        </div>
                    </div>

                    {{-- Suena ahora --}}
                    <div class="flex items-center gap-3.5 border border-white/8 bg-[#121214] p-3.5">
                        <img id="rbcloud_np_cover_mm" src="https://c30.radioboss.fm/w/artwork/569.jpg" width="56" height="56" alt="Carátula actual" class="h-14 w-14 shrink-0 bg-[#0d0d0e] object-contain">
                        <div class="min-w-0 flex-1">
                            <div class="mb-0.5 font-display text-[10px] uppercase tracking-[.14em] text-[#9a9a9a]">Suena ahora</div>
                            <div id="rbcloud_np_artist_mm" class="line-clamp-1 font-display text-[13px] text-white">Cargando...</div>
                            <div id="rbcloud_np_title_mm" class="mt-0.5 line-clamp-2 text-[12px] text-[#b5b5b5]"></div>
                        </div>
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
                <button
                    type="button"
                    class="inline-flex min-h-[42px] w-full items-center justify-center gap-2 rounded-sm bg-lucille-accent px-5 font-display text-xs uppercase tracking-[.14em] text-white transition-colors hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    @click="playTrack({ src: 'live', type: 'live', title: 'Seven Rock Radio', subtitle: 'En Vivo', image: 'https://c30.radioboss.fm/stationlogo/569.jpg' })"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="6,3 20,12 6,21"/></svg>
                    <span>Escuchar en vivo</span>
                </button>
            </div>

            {{-- Programas de hoy --}}
            @php
                $todayKey = ['DOMINGO','LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'][date('w')];
                $todayGroup = collect($programsByDay)->firstWhere('day', $todayKey);
            @endphp
            <div class="lucille-flat-surface flex flex-col justify-between p-6">
                <div>
                    <h3 class="mb-4 flex items-center gap-2 font-display text-sm uppercase tracking-[.12em] text-[#dcdcdc]">
                        <span class="h-4 w-1 bg-lucille-accent"></span>Programas de hoy
                    </h3>
                    @if ($todayGroup && !empty($todayGroup['programs']))
                        <div class="divide-y divide-white/8 border-t border-b border-white/8">
                            @foreach ($todayGroup['programs'] as $prog)
                                <a href="{{ route('programs.detail', ['identifier' => $prog['archive_identifier'] ?? $prog['slug']]) }}"
                                   class="group/p flex items-center gap-3 py-2.5 transition-colors hover:bg-white/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent">
                                    <div class="h-11 w-11 shrink-0 overflow-hidden bg-[#111]">
                                        <img src="{{ $prog['cover'] ?: $fallbackImage }}" alt="{{ $prog['name'] }}" class="h-full w-full object-contain">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="line-clamp-2 font-display text-[12px] uppercase leading-snug tracking-[.06em] text-[#e0e0e0] transition-colors group-hover/p:text-[var(--lucille-accent-readable,#ff5b52)]">{{ $prog['name'] }}</div>
                                        @if ($prog['host'] ?? $prog['conductor'] ?? '')
                                            <div class="mt-0.5 text-[11px] text-[#9e9e9e]">{{ $prog['host'] ?? $prog['conductor'] }}</div>
                                        @endif
                                    </div>
                                    @if ($prog['hora'] ?? '')
                                        <span class="shrink-0 font-mono text-[11px] text-[#b0b0b0]">{{ substr($prog['hora'], 0, 5) }} hs</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="py-4 text-sm text-[#9a9a9a]">No hay programas registrados para hoy.</p>
                    @endif
                </div>
                <a href="{{ route('programs') }}" class="mt-4 inline-flex items-center gap-1.5 font-display text-[11px] uppercase tracking-[.15em] text-[var(--lucille-accent-readable,#ff5b52)] hover:underline">
                    <span>Ver parrilla completa</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════ TAB 2: PODCASTS ═══════════ --}}
    <div id="podcasts" role="tabpanel" aria-labelledby="tab-podcasts" x-show="activeTab === 'podcasts'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-data="{ expandedProgram: null }">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-white/5 pb-4">
            <div>
                <h2 class="flex items-center gap-3 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
                    <span class="h-5 w-1 bg-lucille-accent"></span>Podcasts
                </h2>
                <p class="text-xs text-[#a3a3a3] mt-0.5">Despliega los episodios de cada programa o pulsa reproducir para escuchar la última emisión</p>
            </div>
            <a href="{{ route('audience.daily') }}" class="inline-flex items-center gap-2 self-start sm:self-auto rounded-sm border border-white/10 bg-[#16161a] px-3.5 py-1.5 text-xs font-display uppercase tracking-[.12em] text-[#b0b0b5] hover:text-white hover:border-[var(--lucille-accent)] hover:bg-white/5 transition-all shadow-sm">
                <span class="text-sm leading-none text-[var(--lucille-accent-readable,#ff5b52)]">📊</span>
                <span>Consulta la audiencia de la radio</span>
            </a>
        </div>

        @if (empty($groupedEpisodes))
            <div class="lucille-flat-surface py-14 text-center text-sm text-[#a3a3a3]">No hay episodios disponibles todavía.</div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($groupedEpisodes as $programName => $eps)
                    @php
                        $firstEp        = $eps[0] ?? [];
                        $rawProgImg     = (string) ($firstEp['image'] ?? '');
                        $progImage      = \App\Support\PublicMediaUrl::normalizePublicUrl($rawProgImg) ?: $fallbackImage;
                        $progCount      = count($eps);
                        $progId         = 'prog-' . Str::slug($programName);
                        $latestDate     = $firstEp['date'] ?? '';
                        $visibleEps     = array_slice($eps, 0, 3);
                        $progIdentifier = $programSlugMap[$programName] ?? Str::slug($programName);
                        $progUrl        = route('programs.detail', ['identifier' => $progIdentifier]);
                        $hasMore        = $progCount > 3;
                        $matchedProg    = collect($programsByDay)->flatMap(fn($d) => $d['programs'])->firstWhere('name', $programName);
                        $progDesc       = trim((string) preg_replace('/\*{1,2}([^*]+)\*{1,2}/u', '$1', (string) ($matchedProg['description'] ?? '')));
                        $progHost       = $matchedProg['host'] ?? $matchedProg['conductor'] ?? '';
                        $progGenre      = $matchedProg['genre'] ?? '';
                    @endphp
                    <div class="podcast-card-premium flex flex-col justify-between">
                        <div>
                            {{-- Portada completa sin recorte ni marcos decorativos añadidos --}}
                            <div class="group/cover relative aspect-square overflow-hidden bg-[#101012]">
                                <img src="{{ $progImage }}" alt="{{ $programName }}" width="640" height="480"
                                    onerror="if (!this.dataset.fallbackApplied) { this.dataset.fallbackApplied = '1'; this.src = '{{ $fallbackImage }}'; }"
                                    class="h-full w-full object-contain transition duration-300 ease-out"
                                    loading="lazy" decoding="async">
                                <div class="absolute inset-0 flex items-center justify-center gap-3 bg-black/70 opacity-0 transition-opacity duration-200 group-hover/cover:opacity-100 focus-within:opacity-100">
                                    @if (!empty($firstEp['src']))
                                        <button type="button"
                                            class="flex h-11 w-11 items-center justify-center rounded-sm bg-lucille-accent text-white transition-colors hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                            @click.prevent="playTrack({ type:'podcast', src:'{{ addslashes($firstEp['src']) }}', title:'{{ addslashes($firstEp['episode_title'] ?? $firstEp['title'] ?? '') }}', subtitle:'{{ addslashes($programName) }}', image:'{{ $progImage }}', archive_url:'{{ $firstEp['archive_url'] ?? '' }}' })"
                                            title="Reproducir último episodio"
                                            aria-label="Reproducir último episodio de {{ $programName }}"
                                        >
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="6,3 20,12 6,21"/></svg>
                                        </button>
                                    @endif
                                    <button type="button"
                                        class="flex h-11 w-11 items-center justify-center rounded-sm border border-white/25 bg-[#1b1b1e] text-[#e0e0e0] transition-colors hover:border-white hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                                        @click.prevent="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'"
                                        aria-label="Ver episodios de {{ $programName }}"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Información del programa (títulos de hasta dos líneas) --}}
                            <div class="px-4 py-3.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h3 class="line-clamp-2 font-display text-[14px] uppercase leading-snug tracking-[.06em] text-[#e5e5e5]">{{ $programName }}</h3>
                                        @if ($progHost)
                                            <p class="mt-0.5 text-[11px] text-[#a3a3a3]">{{ $progHost }}</p>
                                        @endif
                                        @if ($progGenre)
                                            <span class="mt-1.5 inline-block border border-white/15 bg-white/[0.03] px-1.5 py-0.5 text-[9px] uppercase tracking-[.08em] text-[#cfcfcf]">{{ $progGenre }}</span>
                                        @endif
                                        @if ($progDesc)
                                            <p class="mt-1.5 line-clamp-2 text-[11px] leading-relaxed text-[#9a9a9a]">{{ $progDesc }}</p>
                                        @endif
                                        <p class="mt-2 text-[11px] text-[#8f8f8f]">{{ $progCount }} {{ $progCount===1 ? 'episodio' : 'episodios' }}@if($latestDate) · {{ $latestDate }}@endif</p>
                                    </div>
                                    <button type="button"
                                        class="mt-0.5 shrink-0 p-1 text-[#a3a3a3] transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                                        :aria-expanded="expandedProgram === '{{ $progId }}' ? 'true' : 'false'"
                                        aria-label="Mostrar episodios de {{ $programName }}"
                                        @click="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'">
                                        <svg class="h-4 w-4 transition-transform duration-200" :class="expandedProgram === '{{ $progId }}' ? 'rotate-180 text-lucille-accent' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Panel desplegable de episodios --}}
                        <div x-show="expandedProgram === '{{ $progId }}'"
                            x-transition:enter="transition-all duration-200 ease-out" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-[900px]"
                            x-transition:leave="transition-all duration-150 ease-in"  x-transition:leave-start="opacity-100 max-h-[900px]" x-transition:leave-end="opacity-0 max-h-0"
                            class="divide-y divide-white/8 overflow-hidden border-t border-white/10 bg-[#121215]">
                            @foreach ($visibleEps as $episode)
                                @php
                                    $epTitle   = $episode['episode_title'] ?? $episode['title'] ?? '';
                                    $epSrc     = $episode['src'] ?? '';
                                    $epDate    = $episode['date'] ?? '';
                                    $epSummary = $episode['summary'] ?? '';
                                @endphp
                                <button type="button" class="group/ep flex w-full items-start gap-3 px-4 py-3 text-left transition-colors duration-150 hover:bg-white/[0.04] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-lucille-accent"
                                    @click="playTrack({ type:'podcast', src:'{{ addslashes($epSrc) }}', title:'{{ addslashes($epTitle) }}', subtitle:'{{ addslashes($programName) }}', image:'{{ $progImage }}', archive_url:'{{ $episode['archive_url'] ?? '' }}' })">
                                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-sm border border-white/15 bg-[#1b1b1f] text-[#b5b5b5] transition-colors group-hover/ep:border-lucille-accent group-hover/ep:bg-lucille-accent group-hover/ep:text-white">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="6,3 20,12 6,21"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="line-clamp-2 text-[12px] font-medium leading-snug text-[#dedede] transition-colors group-hover/ep:text-white">{{ $epTitle ?: 'Episodio' }}</span>
                                            @if ($epDate) <span class="shrink-0 whitespace-nowrap font-mono text-[10px] text-[#9a9a9a]">{{ $epDate }}</span> @endif
                                        </div>
                                        @if ($epSummary) <p class="mt-1 line-clamp-2 text-[11px] text-[#9a9a9a]">{{ $epSummary }}</p> @endif
                                    </div>
                                </button>
                            @endforeach
                            @if ($hasMore)
                                <a href="{{ $progUrl }}" class="flex w-full items-center justify-center gap-1.5 border-t border-white/10 bg-[#16161a] px-4 py-2.5 font-display text-[10px] uppercase tracking-[.12em] text-[#c8c8c8] transition-colors hover:bg-white/[0.05] hover:text-white">
                                    Ver todos ({{ $progCount }} episodios) &rarr;
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 3: CATÁLOGO DE MÚSICA ═══════════ --}}
    <div id="musica" role="tabpanel" aria-labelledby="tab-musica" x-show="activeTab === 'musica'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <h2 class="flex items-center gap-3 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
                <span class="h-5 w-1 bg-lucille-accent"></span>Catálogo de música
            </h2>
            <div class="relative w-full max-w-xs">
                <label for="multimedia-song-search" class="sr-only">Buscar canción o artista</label>
                <input id="multimedia-song-search" type="text" x-model="songSearch" placeholder="Buscar canción o artista..."
                    class="w-full rounded-sm border border-white/15 bg-[#161619] py-2 pr-9 pl-3.5 text-sm text-[#e0e0e0] placeholder-[#8f8f8f] transition-colors focus:border-lucille-accent focus:outline-none">
                <svg class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-[#8f8f8f]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/>
                </svg>
            </div>
        </div>
        @if ($songs->isEmpty())
            <div class="lucille-flat-surface py-14 text-center text-sm text-[#a3a3a3]">No hay canciones publicadas todavía.</div>
        @else
            <div class="divide-y divide-white/8 border-t border-b border-white/10">
                @php $visibleIndex = 1; @endphp
                @foreach ($songs as $song)
                    @php
                        $audioUrl  = $song->audio_url;
                        if (empty($audioUrl)) {
                            continue;
                        }
                        $coverUrl  = $song->cover_image_url ?: $fallbackImage;
                        $bandName  = $song->artist_name;
                        $hasBio    = !empty($song->description);
                        $searchStr = strtolower($song->title . ' ' . $song->artist_name);
                        $socialLinks = [];
                        if (!empty($song->spotify_url)) {
                            $socialLinks['spotify'] = $song->spotify_url;
                        }
                        if (!empty($song->youtube_url)) {
                            $socialLinks['youtube'] = $song->youtube_url;
                        }
                    @endphp
                    <div class="group/song lucille-flat-row flex cursor-pointer items-center gap-4 px-3 py-2.5"
                        x-show="songSearch === '' || '{{ addslashes($searchStr) }}'.includes(songSearch.toLowerCase())"
                        tabindex="0"
                        role="button"
                        @keydown.enter.prevent="playTrack({
                            type: 'song',
                            src: '{{ addslashes($audioUrl) }}',
                            title: '{{ addslashes($song->title) }}',
                            subtitle: '{{ addslashes($song->artist_name) }}',
                            image: '{{ $coverUrl }}',
                            album: '',
                            lyrics: null,
                            bandInfo: {{ !empty($song->description) ? Js::from($song->description) : ($song->radioArtist && !empty($song->radioArtist->biography) ? Js::from($song->radioArtist->biography) : 'null') }},
                            bandName: '{{ addslashes($song->artist_name) }}',
                            bandMembers: [],
                            socialLinks: {{ Js::from($socialLinks) }}
                        })"
                        @click="playTrack({
                            type: 'song',
                            src: '{{ addslashes($audioUrl) }}',
                            title: '{{ addslashes($song->title) }}',
                            subtitle: '{{ addslashes($song->artist_name) }}',
                            image: '{{ $coverUrl }}',
                            album: '',
                            lyrics: null,
                            bandInfo: {{ !empty($song->description) ? Js::from($song->description) : ($song->radioArtist && !empty($song->radioArtist->biography) ? Js::from($song->radioArtist->biography) : 'null') }},
                            bandName: '{{ addslashes($song->artist_name) }}',
                            bandMembers: [],
                            socialLinks: {{ Js::from($socialLinks) }}
                        })">
                        <span class="w-6 shrink-0 text-center font-mono text-[12px] text-[#8f8f8f]"
                              x-show="currentlyPlayingUrl !== '{{ addslashes($audioUrl) }}'"
                              :class="currentlyPlayingUrl !== '{{ addslashes($audioUrl) }}' ? 'group-hover/song:hidden' : 'hidden'">{{ $visibleIndex++ }}</span>
                        {{-- Equalizer animado activo --}}
                        <span class="flex w-6 shrink-0 items-end justify-center gap-px"
                              x-show="currentlyPlayingUrl === '{{ addslashes($audioUrl) }}'"
                              x-cloak
                              style="height:24px">
                            <span class="sr-eq-bar" style="--d:0ms;  --h:60%"></span>
                            <span class="sr-eq-bar" style="--d:120ms; --h:100%"></span>
                            <span class="sr-eq-bar" style="--d:60ms;  --h:45%"></span>
                            <span class="sr-eq-bar" style="--d:200ms; --h:80%"></span>
                            <span class="sr-eq-bar" style="--d:40ms;  --h:55%"></span>
                        </span>
                        <span class="hidden w-6 shrink-0 justify-center text-lucille-accent group-hover/song:flex"
                              x-show="currentlyPlayingUrl !== '{{ addslashes($audioUrl) }}'" aria-hidden="true">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,3 20,12 6,21"/></svg>
                        </span>
                        <div class="h-11 w-11 shrink-0 overflow-hidden bg-[#111]">
                            <img src="{{ $coverUrl }}" alt="{{ $song->title }}" class="h-full w-full object-contain" width="44" height="44" loading="lazy" decoding="async">
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="line-clamp-2 font-display text-[13px] leading-snug text-[#e2e2e2] transition-colors group-hover/song:text-white"
                                 :class="{ 'text-[var(--lucille-accent-readable,#ff5b52)]': currentlyPlayingUrl === '{{ addslashes($audioUrl) }}' }">{{ $song->title }}</div>
                            <div class="mt-0.5 line-clamp-1 text-[12px] text-[#9e9e9e]">{{ $song->artist_name }}</div>
                        </div>
                    </div>
                @endforeach

                <div x-show="songSearch !== '' && !$el.parentElement.querySelector('.group\\/song:not([style*=\'display: none\'])')"
                     x-cloak
                     class="py-14 text-center text-sm text-[#a3a3a3]">
                    No se encontraron canciones que coincidan con «<span x-text="songSearch" class="text-white"></span>».
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 4: VIDEOS ═══════════ --}}
    <div id="videos" role="tabpanel" aria-labelledby="tab-videos" x-show="activeTab === 'videos'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="mb-6 flex items-center gap-3 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
            <span class="h-5 w-1 bg-lucille-accent"></span>Videos
        </h2>
        @if ($videos->isEmpty())
            <div class="lucille-flat-surface py-14 text-center text-sm text-[#a3a3a3]">No hay videos publicados todavía.</div>
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
                    <article class="lucille-flat-surface group flex flex-col overflow-hidden">
                        @if ($embedUrl)
                            <div class="relative aspect-video overflow-hidden bg-[#0a0a0b]">
                                <iframe src="{{ $embedUrl }}" title="{{ $video->title }}" class="h-full w-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                            </div>
                        @else
                            <div class="relative aspect-video overflow-hidden bg-[#0a0a0b] bg-cover bg-center" style="background-image:url('{{ $video->image_url }}')">
                                <div class="absolute inset-0 flex items-center justify-center bg-black/55">
                                    <a href="{{ $video->youtube_url }}" target="_blank" rel="noopener noreferrer" class="flex h-12 w-12 items-center justify-center rounded-sm bg-lucille-accent text-white transition-colors hover:brightness-110" aria-label="Reproducir video {{ $video->title }}">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="6,3 20,12 6,21"/></svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                        <div class="flex flex-1 flex-col justify-between p-4">
                            <h3 class="line-clamp-2 font-display text-sm uppercase leading-snug tracking-[.06em] text-[#e2e2e2] transition-colors group-hover:text-white">{{ $video->title }}</h3>
                            @if ($video->summary)
                                <p class="mt-1.5 line-clamp-2 text-[12px] leading-relaxed text-[#9e9e9e]">{{ $video->summary }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ═══════════ TAB 5: GALERÍA ═══════════ --}}
    <div id="galeria" role="tabpanel" aria-labelledby="tab-galeria" x-show="activeTab === 'galeria'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
        <h2 class="mb-6 flex items-center gap-3 font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc]">
            <span class="h-5 w-1 bg-lucille-accent"></span>Galería de fotos
        </h2>
        @if (empty($galleryImages))
            {{-- Estado vacío sobrio sin prometer aportes no verificados de fotos por bandas --}}
            <div class="lucille-flat-surface py-16 text-center px-6">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-sm border border-white/10 bg-[#141417]">
                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#c32720" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                        <circle cx="12" cy="13" r="4"/>
                    </svg>
                </div>
                <p class="font-display text-sm uppercase tracking-[.16em] text-[#dcdcdc]">Archivo fotográfico</p>
                <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-[#9e9e9e]">
                    Aún no hay fotografías publicadas en la galería editorial.
                </p>
            </div>
        @else
            <div class="columns-1 gap-2 md:columns-2 lg:columns-3">
                @foreach ($galleryImages as $idx => $image)
                    @php $talent = data_get($image, 'talent'); @endphp
                    <a href="{{ data_get($image,'url') }}" class="lucille-gallery-tile group relative mb-2 block overflow-hidden bg-[#18181b]" @click.prevent="showImage({{ $idx }})">
                        <img src="{{ data_get($image,'url') }}" alt="{{ data_get($image,'title', data_get($image,'filename','')) }}" loading="lazy" decoding="async" class="h-auto w-full object-contain transition duration-300 ease-out group-hover:opacity-95">
                        <span class="absolute inset-0 bg-black/45 opacity-0 transition duration-200 group-hover:opacity-100"></span>
                        <span class=" line-clamp-2 absolute right-3 bottom-3 left-3 z-10 font-display text-xs uppercase tracking-[.12em] text-white opacity-0 transition duration-200 group-hover:opacity-100">
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
            class="pointer-events-none fixed right-0 bottom-[88px] left-0 z-[110] flex justify-center px-4">
            <div class="pointer-events-auto w-full max-w-3xl overflow-hidden rounded-sm border border-white/12 bg-[#121215]/96 shadow-[0_-10px_60px_rgba(0,0,0,0.8)] backdrop-blur-xl">
                <div class="flex border-b border-white/10">
                    <button type="button" @click="drawerTab='letras'" :class="drawerTab==='letras'?'text-white border-lucille-accent':'text-[#9e9e9e] border-transparent'" class="flex-1 border-b-2 py-3 font-display text-xs uppercase tracking-[.1em] transition-all duration-200">Letras</button>
                    <button type="button" @click="drawerTab='banda'"  :class="drawerTab==='banda' ?'text-white border-lucille-accent':'text-[#9e9e9e] border-transparent'" class="flex-1 border-b-2 py-3 font-display text-xs uppercase tracking-[.1em] transition-all duration-200">Información de banda</button>
                    <button type="button" @click="drawerOpen=false" class="px-4 text-lg text-[#9e9e9e] transition-colors hover:text-white" aria-label="Cerrar">✕</button>
                </div>
                <div x-show="drawerTab==='letras'" class="max-h-64 overflow-y-auto p-5">
                    <template x-if="activeTrack?.lyrics">
                        <pre class="whitespace-pre-wrap font-sans text-sm leading-relaxed text-[#d4d4d4]" x-text="activeTrack.lyrics"></pre>
                    </template>
                    <template x-if="!activeTrack?.lyrics">
                        <p class="py-8 text-center text-sm text-[#9e9e9e]">No hay letra disponible para esta canción.</p>
                    </template>
                </div>
                <div x-show="drawerTab==='banda'" class="max-h-64 space-y-3 overflow-y-auto p-5">
                    <template x-if="activeTrack?.bandName">
                        <h4 class="font-display text-base uppercase tracking-[.1em] text-white" x-text="activeTrack.bandName"></h4>
                    </template>
                    <template x-if="activeTrack?.bandInfo">
                        <p class="text-sm leading-relaxed text-[#bdbdbd]" x-text="activeTrack.bandInfo"></p>
                    </template>
                    <template x-if="activeTrack?.bandMembers && activeTrack.bandMembers.length">
                        <div>
                            <p class="mb-2 text-[10px] uppercase tracking-[.1em] text-[#9a9a9a]">Integrantes</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="m in activeTrack.bandMembers" :key="m">
                                    <span class="rounded-sm border border-white/15 px-2 py-1 text-[11px] text-[#dcdcdc]" x-text="m"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="activeTrack?.socialLinks && Object.keys(activeTrack.socialLinks??{}).length">
                        <div class="flex flex-wrap gap-3">
                            <template x-for="[key,url] in Object.entries(activeTrack.socialLinks??{})" :key="key">
                                <a :href="url" target="_blank" rel="noopener" class="rounded-sm border border-lucille-accent/40 px-2.5 py-1 text-[10px] uppercase tracking-[.08em] text-[var(--lucille-accent-readable,#ff5b52)] transition-colors hover:bg-lucille-accent/15" x-text="key"></a>
                            </template>
                        </div>
                    </template>
                    <template x-if="!activeTrack?.bandInfo && (!activeTrack?.bandMembers || !activeTrack.bandMembers.length)">
                        <p class="py-8 text-center text-sm text-[#9e9e9e]">No hay información de banda disponible.</p>
                    </template>
                </div>
            </div>
        </div>
    </template>

</section>

@push('styles')
<style>
/* ── Equalizer animado de fila activa ── */
.sr-eq-bar {
    display: inline-block;
    width: 3px;
    min-height: 3px;
    background: #c32720;
    border-radius: 2px;
    transform-origin: bottom;
    animation: sr-eq-bounce var(--spd, 0.55s) ease-in-out infinite alternate;
    animation-delay: var(--d, 0ms);
    height: var(--h, 50%);
    box-shadow: 0 0 4px rgba(195,39,32,.7);
}
.sr-eq-bar:nth-child(1) { --spd: 0.52s; }
.sr-eq-bar:nth-child(2) { --spd: 0.38s; }
.sr-eq-bar:nth-child(3) { --spd: 0.65s; }
.sr-eq-bar:nth-child(4) { --spd: 0.44s; }
.sr-eq-bar:nth-child(5) { --spd: 0.58s; }

@keyframes sr-eq-bounce {
    0%   { transform: scaleY(0.15); }
    100% { transform: scaleY(1); }
}
</style>
@endpush

</x-layouts.site>
