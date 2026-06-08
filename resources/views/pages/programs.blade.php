<x-layouts.site title="Seven Rock Radio - Programas" description="Parrilla completa de programas de Seven Rock Radio. Metal, rock, entrevistas y mas. Escucha todos nuestros podcasts y programas en vivo.">
    <x-sections.page-heading
        title="Programas"
        subtitle="Todos nuestros programas de radio. ¡Escúchalos cuando quieras!"
        image="assets/lucille/microphone-1206364_1920.jpg"
        overlay="rgba(19,19,19,.91)"
    />

    <section class="lucille-content-box">
        @php
            $currentDayName = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'][date('w')];
            $hasProgramsToday = collect($programsByDay)->contains('day', $currentDayName);
            $initialDay = $hasProgramsToday ? $currentDayName : ($programsByDay[0]['day'] ?? 'LUNES');
            $fallbackImage = asset('assets/lucille/logo.png');
        @endphp

        @if (empty($programsByDay) && empty($latestEpisodes))
            <div class="py-16 text-center text-sm text-[#7b7b7b]">
                No hay programas disponibles todavía.
            </div>
        @else
            {{-- Build program name → slug/identifier map --}}
            @php
                $programSlugMap = [];
                foreach ($programsByDay as $dayGroup) {
                    foreach ($dayGroup['programs'] as $prog) {
                        $programSlugMap[$prog['title']] = $prog['id'] ?? $prog['archive_identifier'] ?? Str::slug($prog['title']);
                    }
                }
            @endphp

            <div
                x-data="{
                    activeDay: (() => {
                        const days = ['DOMINGO', 'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
                        const today = days[new Date().getDay()];
                        const availableDays = [ @foreach(collect($programsByDay)->pluck('day') as $d) '{{ $d }}', @endforeach ];
                        return availableDays.includes(today) ? today : '{{ $initialDay }}';
                    })(),
                    activeEpisode: null,
                    playing: false,
                    muted: false,
                    volume: 85,
                    elapsed: 0,
                    duration: 0,
                    progress: 0,
                    playerVisible: false,

                    play(episode) {
                        // Toggle play/pause if same episode
                        if (this.activeEpisode && this.activeEpisode.src === episode.src) {
                            this.togglePlayback();
                            return;
                        }
                        this.activeEpisode = episode;
                        this.playerVisible = true;
                        this.$nextTick(() => this.syncAudio(true));
                    },
                    syncAudio(autoplay) {
                        const audio = this.$refs.audio;
                        if (!audio || !this.activeEpisode) return;
                        audio.volume = this.volume / 100;
                        audio.muted = this.muted;
                        const source = this.activeEpisode.src || '';
                        if (!source) { this.playing = false; return; }
                        if ((audio.getAttribute('src')||'') !== source) {
                            audio.pause();
                            audio.src = source;
                            audio.load();
                            this.elapsed = 0; this.duration = 0; this.progress = 0;
                        }
                        if (autoplay) this.$nextTick(() => audio.play().catch(() => this.playing = false));
                    },
                    togglePlayback() {
                        if (this.playing) this.$refs.audio?.pause();
                        else if (this.activeEpisode) this.play(this.activeEpisode);
                    },
                    setVolume(v) {
                        const n = Math.max(0, Math.min(100, Number(v)||0));
                        this.volume = n;
                        const a = this.$refs.audio; if (a) a.volume = n/100;
                        if (n>0 && this.muted) { this.muted = false; if (a) a.muted = false; }
                    },
                    seekAudio(v) {
                        const n = Math.max(0, Math.min(100, Number(v)||0));
                        this.progress = n;
                        const a = this.$refs.audio;
                        if (a && Number.isFinite(a.duration) && a.duration>0)
                            a.currentTime = (a.duration * n) / 100;
                    },
                    onLoadedMetadata() {
                        const a = this.$refs.audio;
                        if (!a) return;
                        this.duration = Number.isFinite(a.duration) && a.duration>0 ? Math.round(a.duration) : 0;
                    },
                    onTimeUpdate() {
                        const a = this.$refs.audio;
                        if (!a) return;
                        this.elapsed = Number.isFinite(a.currentTime) && a.currentTime>0 ? Math.round(a.currentTime) : 0;
                        if (Number.isFinite(a.duration) && a.duration>0) this.duration = Math.round(a.duration);
                    },
                    onPlay() { this.playing = true; },
                    onPause() { this.playing = false; },
                    onEnded() { this.playing = false; this.elapsed = 0; this.progress = 0; },
                    formatTime(s) {
                        const t = Number.isFinite(s) && s>0 ? Math.floor(s) : 0;
                        return String(Math.floor(t/60)).padStart(2,'0')+':'+String(t%60).padStart(2,'0');
                    },
                    get progressPct() {
                        return this.duration > 0 ? Math.min(100, (this.elapsed/this.duration)*100) : 0;
                    },
                    get timeLabel() {
                        return `${this.formatTime(this.elapsed)} / ${this.formatTime(this.duration)}`;
                    }
                }"
            >
                {{-- ========== PARRILLA DE PROGRAMAS (INTERACTIVA) ========== --}}
                @if (!empty($programsByDay))
                    <div class="mb-16">
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-6 flex items-center gap-3">
                            <span class="w-1 h-6 bg-lucille-accent rounded-full inline-block"></span>
                            Parrilla de Programas
                        </h2>

                        {{-- Day selector tabs --}}
                        <div class="flex flex-wrap gap-2 mb-8 border-b border-[#242424] pb-5">
                            @foreach ($programsByDay as $dayGroup)
                                <button 
                                    type="button"
                                    @click="activeDay = '{{ $dayGroup['day'] }}'"
                                    :class="activeDay === '{{ $dayGroup['day'] }}' ? 'border-lucille-accent text-white bg-lucille-accent/10 shadow-[0_0_15px_rgba(195,39,32,0.15)]' : 'border-[#242424] text-[#888] hover:text-[#dcdcdc] hover:border-[#444]'"
                                    class="px-5 py-2.5 rounded-lg border font-display text-xs uppercase tracking-[.14em] transition-all duration-300"
                                >
                                    {{ $dayGroup['label'] }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Day content panels --}}
                        @foreach ($programsByDay as $dayGroup)
                            <div 
                                x-show="activeDay === '{{ $dayGroup['day'] }}'"
                                x-transition:enter="transition-all ease-out duration-300"
                                x-transition:enter-start="opacity-0 translate-y-4"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="grid gap-6 md:gap-8 grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
                            >
                                @foreach ($dayGroup['programs'] as $program)
                                    @php
                                        $progName = $program['title'] ?? 'Programa';
                                        $progCover = !empty($program['cover']) ? $program['cover'] : $fallbackImage;
                                        $progHost = $program['host'] ?? $program['conductor'] ?? '';
                                        $progTime = $program['hora'] ?? $program['hora_transmision'] ?? '';
                                        $displayTime = !empty($progTime) ? substr($progTime, 0, 5) : '';
                                    @endphp
                                    <a href="{{ route('programs.detail', ['identifier' => $program['archive_identifier'] ?? $program['slug']]) }}"
                                       class="group flex flex-col sm:flex-row items-center gap-4 bg-[#0d0d0d] border border-[#242424] p-5 rounded-xl transition-all duration-300 hover:border-lucille-accent/30 hover:shadow-[0_0_30px_rgba(195,39,32,.08)]">
                                        {{-- Cover --}}
                                        <div class="w-24 h-24 sm:w-20 sm:h-20 shrink-0 rounded-lg overflow-hidden border border-[#2a2a2a] shadow-[0_2px_10px_rgba(0,0,0,.4)] transition-transform duration-300 group-hover:scale-105">
                                            <img src="{{ $progCover }}" alt="{{ $progName }}" width="256" height="256" class="w-full h-full object-cover" loading="lazy" decoding="async">
                                        </div>
                                        {{-- Info --}}
                                        <div class="min-w-0 flex-1 text-center sm:text-left">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                <h4 class="font-display text-sm uppercase tracking-[.06em] text-[#dcdcdc] group-hover:text-lucille-accent transition-colors leading-tight truncate">
                                                    {{ $progName }}
                                                </h4>
                                                @if ($displayTime)
                                                    <span class="inline-block self-center sm:self-start shrink-0 text-[10px] font-mono tracking-[.08em] text-[#888] bg-[#161616] px-2 py-0.5 rounded border border-[#242424]">
                                                        {{ $displayTime }} HS
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($progHost)
                                                <p class="mt-1 text-[11px] text-[#888] font-medium">{{ $progHost }}</p>
                                            @endif
                                            @if (!empty($program['description']))
                                                <p class="mt-2.5 text-[11px] text-[#666] line-clamp-2 leading-relaxed">{{ $program['description'] }}</p>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- ========== PODCASTS / TARJETAS CON IMAGEN A ANCHO COMPLETO ========== --}}
                @if (!empty($groupedEpisodes))
                    <div class="mt-16">
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-2 flex items-center gap-3">
                            <span class="w-1 h-6 bg-lucille-accent rounded-full inline-block"></span>
                            Podcasts
                        </h2>
                        <p class="text-sm text-[#7b7b7b] mb-8 ml-4">Toca la portada para ver o reproducir los episodios</p>

                        {{-- Banner decorativo --}}
                        <div class="mb-10 mt-4 w-full overflow-hidden rounded-xl border border-[#242424]">
                            <img src="{{ asset('assets/lucille/podcats.webp') }}"
                                alt="Podcasts"
                                width="1200"
                                height="400"
                                class="w-full h-auto object-contain transition-transform duration-700 hover:scale-101"
                                loading="lazy"
                                decoding="async">
                        </div>

                        <div class="grid gap-6 md:gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                             x-data="{ expandedProgram: null }">

                            @foreach ($groupedEpisodes as $programName => $eps)
                                @php
                                    $firstEp = $eps[0] ?? [];
                                    $progImage = $firstEp['image'] ?? $fallbackImage;
                                    $progCount = count($eps);
                                    $progId = 'prog-' . Str::slug($programName);
                                    $latestDate = $firstEp['date'] ?? '';
                                    $visibleEpisodes = array_slice($eps, 0, 3);
                                    $progIdentifier = $programSlugMap[$programName] ?? Str::slug($programName);
                                    $progUrl = route('programs.detail', ['identifier' => $progIdentifier]);
                                    $hasMore = $progCount > 3;
                                @endphp
                                <div class="podcast-card-premium">
                                    {{-- Cover image — full width with Hover Controls --}}
                                    <div class="relative group/cover aspect-square overflow-hidden bg-[#080808]">
                                        <img src="{{ $progImage }}" alt="{{ $programName }}"
                                            width="640"
                                            height="480"
                                            class="w-full h-full object-contain p-3 transition duration-500 ease-out group-hover/cover:scale-105"
                                            loading="lazy"
                                            decoding="async">
                                            
                                        {{-- Hover Overlay Controls --}}
                                        <div class="absolute inset-0 bg-black/70 opacity-0 group-hover/cover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-4">
                                            {{-- Play Latest Episode --}}
                                            @if (!empty($firstEp['src']))
                                                <button type="button"
                                                    class="w-12 h-12 rounded-full bg-lucille-accent text-white flex items-center justify-center shadow-[0_0_20px_rgba(195,39,32,.4)] hover:bg-lucille-accent/90 hover:scale-110 active:scale-95 transition-all duration-200"
                                                    @click.prevent="play({
                                                        program: '{{ str_replace("'", "\'", $programName) }}',
                                                        title: '{{ str_replace("'", "\'", $firstEp['episode_title'] ?? $firstEp['title'] ?? '') }}',
                                                        src: '{{ $firstEp['src'] ?? '' }}',
                                                        archive_url: '{{ $firstEp['archive_url'] ?? '' }}',
                                                        image: '{{ $progImage }}'
                                                    })"
                                                    title="Reproducir último episodio"
                                                >
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5"><polygon points="6,3 20,12 6,21"/></svg>
                                                </button>
                                            @endif
                                            
                                            {{-- Expand list --}}
                                            <button type="button"
                                                class="w-12 h-12 rounded-full bg-[#222] border border-[#444] text-[#ccc] flex items-center justify-center hover:bg-[#333] hover:text-white hover:scale-110 active:scale-95 transition-all duration-200"
                                                @click.prevent="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'"
                                                :title="(expandedProgram === '{{ $progId }}' ? 'Ocultar' : 'Ver') + ' episodios'"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Program info below image --}}
                                    <div class="px-4 py-3.5 border-b border-[#242424] flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="font-display text-[13px] uppercase tracking-[.06em] text-[#dcdcdc] leading-tight truncate">
                                                {{ $programName }}
                                            </h3>
                                            <p class="mt-0.5 text-[10px] text-[#777]">
                                                {{ $progCount }} {{ $progCount === 1 ? 'episodio' : 'episodios' }}
                                                @if ($latestDate)
                                                    · {{ $latestDate }}
                                                @endif
                                            </p>
                                        </div>
                                        {{-- Interactive Chevron --}}
                                        <button type="button" 
                                            class="shrink-0 text-[#555] hover:text-[#aaa] transition-colors"
                                            @click="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'"
                                            aria-label="Expandir episodios"
                                        >
                                            <svg class="w-4 h-4 transition-transform duration-300" :class="expandedProgram === '{{ $progId }}' ? 'rotate-180 text-lucille-accent' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- Episodes panel (max 3) --}}
                                    <div x-show="expandedProgram === '{{ $progId }}'"
                                        x-transition:enter="transition-all duration-300 ease-out"
                                        x-transition:enter-start="opacity-0 max-h-0"
                                        x-transition:enter-end="opacity-100 max-h-[900px]"
                                        x-transition:leave="transition-all duration-200 ease-in"
                                        x-transition:leave-start="opacity-100 max-h-[900px]"
                                        x-transition:leave-end="opacity-0 max-h-0"
                                        class="border-t border-[#1f1f1f] divide-y divide-[#1f1f1f] overflow-hidden bg-[#080808]">
                                        @foreach ($visibleEpisodes as $episode)
                                            @php
                                                $epTitle = $episode['episode_title'] ?? $episode['title'] ?? '';
                                                $epSrc = $episode['src'] ?? '';
                                                $epDate = $episode['date'] ?? '';
                                                $epSummary = $episode['summary'] ?? '';
                                                $epArchiveUrl = $episode['archive_url'] ?? '';
                                            @endphp
                                            <button type="button"
                                                class="flex items-start gap-3 w-full px-4 py-3 text-left transition-all duration-200 hover:bg-[#121212] hover:pl-5 active:bg-[#151515] group/ep"
                                                @click="play({
                                                    program: '{{ str_replace("'", "\'", $programName) }}',
                                                    title: '{{ str_replace("'", "\'", $epTitle) }}',
                                                    src: '{{ $epSrc }}',
                                                    archive_url: '{{ $epArchiveUrl }}',
                                                    image: '{{ $progImage }}'
                                                })">
                                                {{-- Play icon --}}
                                                <div class="mt-0.5 shrink-0 w-7 h-7 rounded-full border border-[#333] bg-[#151515] flex items-center justify-center text-[#888] transition-all duration-200 group-hover/ep:bg-lucille-accent group-hover/ep:border-lucille-accent group-hover/ep:text-white">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="6,3 20,12 6,21"/></svg>
                                                </div>
                                                {{-- Info --}}
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="text-[12px] text-[#ccc] group-hover/ep:text-lucille-accent transition-colors line-clamp-1 leading-snug font-medium">
                                                            {{ $epTitle ?: 'Episodio' }}
                                                        </span>
                                                        @if ($epDate)
                                                            <span class="shrink-0 text-[9px] text-[#666] whitespace-nowrap font-mono">{{ $epDate }}</span>
                                                        @endif
                                                    </div>
                                                    @if ($epSummary)
                                                        <p class="mt-1 text-[10px] text-[#666] line-clamp-1 leading-relaxed">{{ $epSummary }}</p>
                                                    @endif
                                                </div>
                                            </button>
                                        @endforeach

                                        {{-- "Ver más" link --}}
                                        @if ($hasMore)
                                            <a href="{{ $progUrl }}"
                                                class="flex items-center justify-center gap-1.5 w-full px-4 py-3 text-[10px] uppercase tracking-[.08em] text-[#777] bg-[#101010] border-t border-[#1f1f1f] transition-all duration-200 hover:bg-[#181818] hover:text-lucille-accent group/vm">
                                                Ver más ({{ $progCount - 3 }} {{ $progCount - 3 === 1 ? 'episodio' : 'episodios' }})
                                                <svg class="transition-transform duration-200 group-hover/vm:translate-x-0.5" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ========== REPRODUCTOR FLOTANTE GLASSMORPHIC ========== --}}
                <div x-show="playerVisible && activeEpisode" x-cloak
                    class="fixed bottom-0 left-0 right-0 z-[100] flex justify-center p-4 sm:p-6 pointer-events-none"
                >
                    <div 
                        x-transition:enter="transition-all duration-400 ease-out"
                        x-transition:enter-start="translate-y-20 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition-all duration-300 ease-in"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="translate-y-20 opacity-0"
                        class="w-full max-w-5xl pointer-events-auto relative bg-[#0b0b0c]/85 backdrop-blur-md border border-white/8 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.75)] overflow-hidden p-3.5 sm:p-5"
                    >
                        {{-- Top slim progress indicator --}}
                        <div class="absolute top-0 left-0 right-0 h-1 bg-white/5">
                            <div class="h-full bg-lucille-accent transition-all duration-100" :style="'width: ' + progressPct + '%'"></div>
                        </div>

                        <template x-if="activeEpisode">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                {{-- Left: Info --}}
                                <div class="flex items-center gap-3 min-w-0 w-full sm:w-auto flex-[2] sm:flex-1">
                                    <div class="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-[#2b2b2b] bg-[#111] shadow-[0_2px_8px_rgba(0,0,0,.3)]">
                                        <img :src="activeEpisode.image || '{{ $fallbackImage }}'"
                                            :alt="activeEpisode.program" width="256" height="256" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-display text-[12px] uppercase tracking-[.08em] text-[#dcdcdc] leading-tight"
                                            x-text="activeEpisode.program"></div>
                                        <div class="truncate text-[10px] text-[#888] mt-1"
                                            x-text="activeEpisode.title"></div>
                                        <div class="sm:hidden text-[9px] text-[#555] font-display tracking-[.12em] mt-1"
                                            x-text="timeLabel"></div>
                                    </div>
                                </div>

                                {{-- Center: Play controls & Seek bar --}}
                                <div class="flex items-center gap-4 w-full sm:w-auto flex-[3] max-w-lg mx-auto">
                                    {{-- Play / Pause button --}}
                                    <button type="button"
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-lucille-accent text-white shadow-[0_0_15px_rgba(195,39,32,0.4)] transition-all hover:bg-lucille-accent/90 hover:scale-105 active:scale-95"
                                        @click="togglePlayback()"
                                        aria-label="Reproducir o pausar">
                                        <span class="text-base leading-none ml-0.5" x-show="!playing">▶</span>
                                        <span class="text-base leading-none" x-show="playing">⏸</span>
                                    </button>

                                    {{-- Seek slider --}}
                                    <div class="hidden sm:flex items-center gap-2.5 w-full">
                                        <span class="text-[9px] font-mono tracking-wider text-[#666] min-w-[36px] text-right" x-text="formatTime(elapsed)"></span>
                                        <div class="flex-1 py-2">
                                            <input type="range" min="0" max="100" step="0.1"
                                                :value="progressPct"
                                                @input="seekAudio($event.target.value)"
                                                class="lucille-range-slider"
                                                aria-label="Barra de progreso de audio">
                                        </div>
                                        <span class="text-[9px] font-mono tracking-wider text-[#666] min-w-[36px] text-left" x-text="formatTime(duration)"></span>
                                    </div>
                                </div>

                                {{-- Right: Volume & Close controls --}}
                                <div class="flex items-center justify-end gap-3 w-full sm:w-auto flex-1 shrink-0">
                                    {{-- Mute / Unmute --}}
                                    <button type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded border border-[#242424] bg-transparent text-[#aaa] transition-colors hover:text-white hover:border-[#444]"
                                        @click="muted = !muted; const a = $refs.audio; if (a) a.muted = muted"
                                        aria-label="Silenciar">
                                        <span x-show="!muted">🔊</span>
                                        <span x-show="muted">🔇</span>
                                    </button>

                                    {{-- Volume slider --}}
                                    <div class="w-16 sm:w-20">
                                        <input type="range" min="0" max="100" step="1"
                                            :value="volume"
                                            @input="setVolume($event.target.value)"
                                            class="lucille-range-slider"
                                            aria-label="Volumen">
                                    </div>

                                    {{-- Close --}}
                                    <button type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded border border-[#242424] bg-transparent text-[#666] transition-colors hover:text-white hover:border-[#444]"
                                        @click="playerVisible = false; $refs.audio?.pause()"
                                        aria-label="Cerrar reproductor">
                                        <span class="text-sm leading-none">✕</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <audio x-ref="audio" preload="metadata" playsinline
                    @loadedmetadata="onLoadedMetadata()"
                    @timeupdate="onTimeUpdate()"
                    @play="onPlay()" @pause="onPause()" @ended="onEnded()">
                </audio>
            </div>
        @endif
    </section>
</x-layouts.site>
