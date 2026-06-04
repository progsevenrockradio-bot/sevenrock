<x-layouts.site title="Seven Rock Radio - Programas" description="Parrilla completa de programas de Seven Rock Radio. Metal, rock, entrevistas y mas. Escucha todos nuestros podcasts y programas en vivo.">
    <x-sections.page-heading
        title="Programas"
        subtitle="Todos nuestros programas de radio. ¡Escúchalos cuando quieras!"
        image="assets/lucille/microphone-1206364_1920.jpg"
        overlay="rgba(19,19,19,.91)"
    />

    <section class="lucille-content-box">
        @php $fallbackImage = asset('assets/lucille/logo.png'); @endphp

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
                {{-- ========== PARRILLA DE PROGRAMAS (INTACTA) ========== --}}
                @if (!empty($programsByDay))
                    <div class="mb-16">
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-8 flex items-center gap-3">
                            <span class="w-1 h-6 bg-lucille-accent rounded-full inline-block"></span>
                            Parrilla de Programas
                        </h2>

                        <div class="grid gap-6 md:gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($programsByDay as $dayGroup)
                                <div class="bg-[#0d0d0d] border border-[#242424] rounded-xl overflow-hidden transition-all duration-300 hover:border-lucille-accent/20 hover:shadow-[0_0_25px_rgba(195,39,32,.06)]">
                                    {{-- Day header --}}
                                    <div class="px-4 py-3 border-b border-[#242424] bg-[#111]">
                                        <h3 class="font-display text-sm uppercase tracking-[.18em] text-lucille-accent font-semibold">
                                            {{ $dayGroup['label'] }}
                                        </h3>
                                    </div>

                                    {{-- Programs in this day --}}
                                    <div class="divide-y divide-[#1f1f1f]">
                                        @foreach ($dayGroup['programs'] as $program)
                                            @php
                                                $progName = $program['title'] ?? 'Programa';
                                                $progCover = !empty($program['cover']) ? $program['cover'] : $fallbackImage;
                                                $progHost = $program['host'] ?? $program['conductor'] ?? '';
                                                $progTime = $program['hora'] ?? $program['hora_transmision'] ?? '';
                                                $displayTime = !empty($progTime) ? substr($progTime, 0, 5) : '';
                                            @endphp
                                            <a href="{{ route('programs.detail', ['identifier' => $program['archive_identifier'] ?? $program['slug']]) }}"
                                               class="group flex items-start gap-3 px-4 py-3.5 transition-all duration-200 hover:bg-[#181818] hover:pl-5 active:bg-[#1a1a1a]">
                                                {{-- Mini cover --}}
                                                <div class="w-12 h-12 shrink-0 rounded-lg overflow-hidden border border-[#2a2a2a] shadow-[0_2px_8px_rgba(0,0,0,.3)] transition-transform duration-300 group-hover:scale-105">
                                                    <img src="{{ $progCover }}" alt="{{ $progName }}" width="256" height="256" class="w-full h-full object-cover" loading="lazy" decoding="async">
                                                </div>
                                                {{-- Info --}}
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <h4 class="font-display text-[13px] uppercase tracking-[.06em] text-[#dcdcdc] group-hover:text-lucille-accent transition-colors leading-tight truncate">
                                                            {{ $progName }}
                                                        </h4>
                                                        @if ($displayTime)
                                                            <span class="shrink-0 text-[10px] font-mono tracking-[.08em] text-[#666] bg-[#1a1a1a] px-2 py-0.5 rounded border border-[#2a2a2a]">
                                                                {{ $displayTime }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if ($progHost)
                                                        <p class="mt-0.5 text-[11px] text-[#888] truncate">{{ $progHost }}</p>
                                                    @endif
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ========== PODCASTS / TARJETAS CON IMAGEN A ANCHO COMPLETO ========== --}}
                @if (!empty($groupedEpisodes))
                    <div>
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] mb-2 flex items-center gap-3">
                            <span class="w-1 h-6 bg-lucille-accent rounded-full inline-block"></span>
                            Podcasts
                        </h2>
                        <p class="text-sm text-[#7b7b7b] mb-8 ml-4">Toca la imagen del programa para ver sus últimos episodios</p>

                        {{-- Banner decorativo --}}
                        <div class="mb-10 mt-4 w-full overflow-hidden rounded-xl">
                            <img src="{{ asset('assets/lucille/podcats.webp') }}"
                                alt="Podcasts"
                                width="1200"
                                height="400"
                                class="w-full h-auto object-contain"
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
                                <div class="bg-[#0d0d0d] border border-[#242424] rounded-xl overflow-hidden transition-all duration-300 hover:border-lucille-accent/20 hover:shadow-[0_0_25px_rgba(195,39,32,.06)]">
                                    {{-- Cover image — full width, same as day cards --}}
                                    <button type="button"
                                        class="block w-full aspect-[4/3] overflow-hidden bg-[#080808] transition-all duration-200 hover:opacity-90 active:opacity-80"
                                        @click="expandedProgram = expandedProgram === '{{ $progId }}' ? null : '{{ $progId }}'"
                                        :title="(expandedProgram === '{{ $progId }}' ? 'Ocultar' : 'Mostrar') + ' episodios'">
                                        <img src="{{ $progImage }}" alt="{{ $programName }}"
                                            width="640"
                                            height="480"
                                            class="w-full h-full object-contain p-3 transition duration-500 ease-out hover:scale-105"
                                            loading="lazy"
                                            decoding="async">
                                    </button>

                                    {{-- Program info below image --}}
                                    <div class="px-4 py-3.5 border-b border-[#242424]">
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

                                    {{-- Episodes panel (max 3) --}}
                                    <div x-show="expandedProgram === '{{ $progId }}'"
                                        x-transition:enter="transition-all duration-300 ease-out"
                                        x-transition:enter-start="opacity-0 max-h-0"
                                        x-transition:enter-end="opacity-100 max-h-[900px]"
                                        x-transition:leave="transition-all duration-200 ease-in"
                                        x-transition:leave-start="opacity-100 max-h-[900px]"
                                        x-transition:leave-end="opacity-0 max-h-0"
                                        class="border-t border-[#1f1f1f] divide-y divide-[#1f1f1f] overflow-hidden">
                                        @foreach ($visibleEpisodes as $episode)
                                            @php
                                                $epTitle = $episode['episode_title'] ?? $episode['title'] ?? '';
                                                $epSrc = $episode['src'] ?? '';
                                                $epDate = $episode['date'] ?? '';
                                                $epSummary = $episode['summary'] ?? '';
                                                $epArchiveUrl = $episode['archive_url'] ?? '';
                                            @endphp
                                            <button type="button"
                                                class="flex items-start gap-3 w-full px-4 py-3 text-left transition-all duration-200 hover:bg-[#181818] hover:pl-5 active:bg-[#1a1a1a] group/ep"
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
                                                        <span class="text-[12px] text-[#ccc] group-hover/ep:text-lucille-accent transition-colors line-clamp-1 leading-snug">
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
                                                class="flex items-center justify-center gap-1.5 w-full px-4 py-3 text-[10px] uppercase tracking-[.08em] text-[#777] bg-[#111] transition-all duration-200 hover:bg-[#181818] hover:text-lucille-accent group/vm">
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

                {{-- ========== REPRODUCTOR INFERIOR ========== --}}
                <div x-show="playerVisible && activeEpisode" x-cloak
                    x-transition:enter="transition-all duration-400 ease-out"
                    x-transition:enter-start="translate-y-full opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition-all duration-300 ease-in"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-full opacity-0"
                    class="fixed bottom-0 left-0 right-0 z-[100] border-t border-[#242424] bg-gradient-to-t from-[#0a0a0a] via-[#0d0d0d] to-[#111] shadow-[0_-8px_40px_rgba(0,0,0,.6)]">

                    <div class="h-0.5 w-full bg-[#2b2b2b]">
                        <progress class="program-progress-meter" :value="progressPct" max="100" aria-label="Progreso de reproducción"></progress>
                    </div>

                    <div class="mx-auto max-w-[1180px] px-3 py-2 sm:px-6 sm:py-4">
                        <template x-if="activeEpisode">
                            <div class="flex items-center gap-2 sm:gap-4">
                                {{-- Info --}}
                                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-[2] sm:flex-1">
                                    <div class="h-9 w-9 sm:h-12 sm:w-12 shrink-0 overflow-hidden rounded-lg border border-[#2b2b2b] bg-[#111] shadow-[0_4px_12px_rgba(0,0,0,.3)]">
                                                <img :src="activeEpisode.image || '{{ $fallbackImage }}'"
                                            :alt="activeEpisode.program" width="640" height="480" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-display text-[11px] sm:text-[14px] uppercase tracking-[.08em] text-[#dcdcdc] leading-tight"
                                            x-text="activeEpisode.program"></div>
                                        <div class="truncate text-[9px] sm:text-[11px] text-[#888] mt-0.5"
                                            x-text="activeEpisode.title"></div>
                                        <div class="hidden sm:block text-[9px] text-[#555] font-display tracking-[.12em] mt-0.5"
                                            x-text="timeLabel"></div>
                                    </div>
                                </div>

                                {{-- Controls --}}
                                <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                                    <button type="button"
                                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-white/20 bg-white/5 text-white transition-all hover:bg-white/10 hover:border-white/30 active:scale-90"
                                        @click="togglePlayback()">
                                        <span class="text-sm sm:text-base leading-none ml-0.5" x-show="!playing">▶</span>
                                        <span class="text-sm sm:text-base leading-none" x-show="playing">⏸</span>
                                    </button>

                                    <div class="hidden md:flex items-center gap-2">
                                        <div class="w-20 lg:w-28">
                                            <input type="range" min="0" max="100" step="1"
                                                :value="progressPct"
                                                @input="seekAudio($event.target.value)"
                                                class="h-1 w-full cursor-pointer appearance-none rounded-full bg-[#3a3a3a] accent-lucille-accent"
                                                aria-label="Progreso">
                                        </div>
                                        <span class="text-[10px] font-display tracking-[.1em] text-[#888] min-w-[60px] text-right"
                                            x-text="timeLabel"></span>
                                    </div>

                                    <button type="button"
                                        class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded border border-[#2b2b2b] bg-transparent text-[#aaa] transition-colors hover:text-white hover:border-white/30"
                                        @click="muted = !muted; const a = $refs.audio; if (a) a.muted = muted">
                                        <span x-show="!muted">🔊</span>
                                        <span x-show="muted">🔇</span>
                                    </button>

                                    <div class="hidden md:block w-14 lg:w-20">
                                        <input type="range" min="0" max="100" step="1"
                                            :value="volume"
                                            @input="setVolume($event.target.value)"
                                            class="h-1 w-full cursor-pointer appearance-none rounded-full bg-[#3a3a3a] accent-[#aaa]"
                                            aria-label="Volumen">
                                    </div>

                                    <button type="button"
                                        class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded border border-[#2b2b2b] bg-transparent text-[#666] transition-colors hover:text-white hover:border-white/30"
                                        @click="playerVisible = false; $refs.audio?.pause()">
                                        <span class="text-sm sm:text-base">✕</span>
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
