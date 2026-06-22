<x-layouts.site 
    title="Seven Rock Radio - {{ $program['title'] ?? 'Programa' }}"
    :og-image="$program['cover'] ?? null"
    :description="\Illuminate\Support\Str::limit(strip_tags($program['description'] ?? 'Programa de Seven Rock Radio'), 150)"
>
    <x-sections.page-heading
        :title="$program['title'] ?? 'Programa'"
        :subtitle="$program['host'] ? 'Conduce: ' . $program['host'] : 'Programa de radio'"
        :image="$program['cover'] ?? null"
        overlay="rgba(19,19,19,.91)"
    />

    <section>
        <div class="lucille-content-box mx-auto max-w-[1180px] px-5 py-12">
            @php $fallbackImage = asset('assets/lucille/logo.png'); @endphp

            {{-- Descripción del programa --}}
            @if (!empty($program['description']))
                <div class="mb-12 max-w-3xl border-l-2 border-lucille-accent pl-5 py-1">
                    <p class="text-sm leading-relaxed text-[#cbcbcb]">{{ $program['description'] }}</p>
                    @if (!empty($program['schedule']))
                        <p class="mt-3 text-[11px] uppercase tracking-[.18em] text-[#7b7b7b]">
                            Horario: {{ $program['schedule'] }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Episodios --}}
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
                    fallbackModalVisible: false,

                    play(episode, autoplay = true) {
                        this.activeEpisode = episode;
                        this.playerVisible = true;
                        if (autoplay) this.$nextTick(() => this.syncAudio(true));

                        if (episode) {
                            fetch(`/programas/track-play?program=${encodeURIComponent(episode.program || '') || encodeURIComponent('{{ addslashes($program['title']) }}')}&archive_url=${encodeURIComponent(episode.archive_url || '') || encodeURIComponent('https://archive.org/details/{{ $program['id'] }}')}`).catch(() => {});
                        }
                    },
                    syncAudio(autoplay) {
                        const a = this.$refs.audio;
                        if (!a || !this.activeEpisode) return;
                        a.volume = this.volume / 100;
                        a.muted = this.muted;
                        const src = this.activeEpisode.src || '';
                        if (!src) { this.playing = false; return; }
                        if ((a.getAttribute('src')||'') !== src) {
                            a.pause(); a.src = src; a.load();
                            this.elapsed = 0; this.duration = 0; this.progress = 0;
                        }
                        if (autoplay) this.$nextTick(() => a.play().catch(() => this.playing = false));
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
                @if ($episodes->isNotEmpty())
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc] flex items-center gap-3">
                            <span class="w-1 h-6 bg-lucille-accent rounded-full inline-block"></span>
                            Episodios · <span class="text-lucille-accent">{{ $episodes->total() }}</span>
                        </h2>
                    </div>

                    <div class="grid gap-4">
                        @foreach ($episodes as $index => $episode)
                            @php
                                $epTitle = $episode['title'] ?? 'Episodio ' . ($index + 1 + ($episodes->currentPage() - 1) * $episodes->perPage());
                                $epSrc = $episode['src'] ?? '';
                                $epDuration = $episode['duration'] ?? '';
                                $epDate = isset($episode['published_at']) ? \Carbon\Carbon::createFromTimestamp((int)$episode['published_at'])->format('d/m/Y') : '';
                                $epSize = isset($episode['size']) ? round($episode['size'] / 1048576, 1) . ' MB' : '';
                                $epViews = $episode['views'] ?? '';
                            @endphp
                            <div class="group flex items-center justify-between gap-4 border border-white/10 bg-white/[0.02] backdrop-blur-md rounded-[16px] p-4 sm:p-5 transition-all duration-300 hover:border-white/20 hover:bg-white/[0.04] hover:-translate-y-0.5 shadow-lg"
                                :class="activeEpisode && activeEpisode.src === '{{ $epSrc }}' ? 'border-lucille-accent/50 bg-lucille-accent/5 shadow-[0_0_20px_rgba(195,39,32,.08)]' : ''">

                                <div class="flex items-center gap-4 min-w-0 flex-1">
                                    {{-- Portada miniatura --}}
                                    <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 overflow-hidden rounded-[8px] border border-white/10 bg-[#0a0a0a]">
                                        <img src="{{ $program['cover'] ?: $fallbackImage }}"
                                             alt="{{ $epTitle }}"
                                             width="256"
                                             height="256"
                                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-102"
                                             loading="lazy"
                                             decoding="async">
                                    </div>

                                    {{-- Info del episodio --}}
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-display text-[14px] sm:text-[16px] uppercase tracking-[.08em] text-[#dcdcdc] group-hover:text-lucille-accent transition-colors truncate">
                                            {{ $epTitle }}
                                        </h3>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] sm:text-[11px] text-[#7b7b7b]">
                                            @if ($epDate)
                                                <span>{{ $epDate }}</span>
                                            @endif
                                            @if ($epDuration)
                                                <span class="text-lucille-accent/70 font-mono">{{ $epDuration }}</span>
                                            @endif
                                            @if ($epSize)
                                                <span class="font-mono">{{ $epSize }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Botón Play --}}
                                @if ($epSrc)
                                    <button type="button"
                                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-white/10 bg-white/5 text-white transition-all duration-300 hover:bg-lucille-accent hover:border-lucille-accent hover:shadow-[0_0_15px_rgba(195,39,32,0.4)] hover:scale-105 active:scale-95 shrink-0"
                                        @click="play({
                                            program: '{{ addslashes($program['title']) }}',
                                            title: '{{ addslashes($epTitle) }}',
                                            src: '{{ $epSrc }}',
                                            image: '{{ $program['cover'] ?: $fallbackImage }}'
                                        })">
                                        <span class="text-sm sm:text-base leading-none ml-0.5">▶</span>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Glassmorphic Pagination Controls --}}
                    @if ($episodes->hasPages())
                        <div class="mt-8 flex justify-center items-center gap-2 font-mono text-sm">
                            {{-- Previous Page Link --}}
                            @if ($episodes->onFirstPage())
                                <span class="px-4 py-2 border border-white/5 bg-white/[0.01] text-white/30 rounded-lg cursor-not-allowed select-none">
                                    &larr; Anterior
                                </span>
                            @else
                                <a href="{{ $episodes->previousPageUrl() }}" class="px-4 py-2 border border-white/10 bg-white/5 text-white/70 hover:text-white hover:border-lucille-accent/50 hover:bg-lucille-accent/10 rounded-lg transition-all duration-200">
                                    &larr; Anterior
                                </a>
                            @endif

                            {{-- Page Links --}}
                            @foreach ($episodes->getUrlRange(max(1, $episodes->currentPage() - 2), min($episodes->lastPage(), $episodes->currentPage() + 2)) as $page => $url)
                                @if ($page == $episodes->currentPage())
                                    <span class="px-4 py-2 border border-lucille-accent bg-lucille-accent/10 text-lucille-accent rounded-lg font-bold select-none">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="px-4 py-2 border border-white/10 bg-white/5 text-white/70 hover:text-white hover:border-lucille-accent/50 hover:bg-lucille-accent/10 rounded-lg transition-all duration-200">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach

                            {{-- Next Page Link --}}
                            @if ($episodes->hasMorePages())
                                <a href="{{ $episodes->nextPageUrl() }}" class="px-4 py-2 border border-white/10 bg-white/5 text-white/70 hover:text-white hover:border-lucille-accent/50 hover:bg-lucille-accent/10 rounded-lg transition-all duration-200">
                                    Siguiente &rarr;
                                </a>
                            @else
                                <span class="px-4 py-2 border border-white/5 bg-white/[0.01] text-white/30 rounded-lg cursor-not-allowed select-none">
                                    Siguiente &rarr;
                                </span>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="py-16 text-center border border-dashed border-white/10 bg-white/[0.01] rounded-[16px] p-8">
                        <p class="text-sm text-[#7b7b7b]">No hay episodios disponibles para este programa.</p>
                        <a href="{{ route('programs') }}" class="mt-4 inline-block text-[11px] uppercase tracking-[.18em] text-lucille-accent hover:underline">
                            ← Volver a programas
                        </a>
                    </div>
                @endif

                {{-- Reproductor flotante glassmorphic premium --}}
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
                    x-on:error="playerVisible = false; fallbackModalVisible = true;"
                    @loadedmetadata="onLoadedMetadata()"
                    @timeupdate="onTimeUpdate()"
                    @play="onPlay()" @pause="onPause()" @ended="onEnded()">
                </audio>
            </div>

            {{-- Link de vuelta --}}
            <div class="mt-12 text-center">
                <a href="{{ route('programs') }}"
                   class="inline-flex items-center gap-2 border border-[#2b2b2b] px-6 py-3 text-[10px] font-display uppercase tracking-[.18em] text-[#7b7b7b] transition-all duration-300 hover:text-lucille-accent hover:border-lucille-accent/40 rounded-[8px]">
                    ⬅ Todos los programas
                </a>
            </div>

            {{-- Modal de Respaldo Mixcloud --}}
            <div x-show="fallbackModalVisible" 
                 x-transition.opacity
                 style="display: none;" 
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
                
                <div class="relative w-full max-w-2xl rounded-[16px] border border-white/10 bg-[#111] p-6 shadow-2xl" @click.away="fallbackModalVisible = false">
                    {{-- Botón de cerrar --}}
                    <button @click="fallbackModalVisible = false" class="absolute top-4 right-4 text-[#7b7b7b] hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <h3 class="font-display text-xl uppercase tracking-[.12em] text-[#dcdcdc] mb-4">Emisión de Respaldo</h3>
                    
                    @if(!empty($program['mixcloud']))
                        <div class="aspect-video w-full overflow-hidden rounded-[8px] bg-black">
                            <iframe width="100%" height="100%" src="{{ $program['mixcloud'] }}" frameborder="0" allow="autoplay"></iframe>
                        </div>
                        <p class="mt-4 text-xs text-[#7b7b7b]">Escuchando a través de Mixcloud debido a inestabilidad en el servidor principal.</p>
                    @else
                        <div class="flex h-40 flex-col items-center justify-center rounded-[8px] border border-white/5 bg-white/5 text-center p-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[#c32720] mb-3 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <p class="text-sm font-medium text-[#dcdcdc]">El servicio principal de audio no está disponible en este momento.</p>
                            <p class="mt-1 text-xs text-[#7b7b7b]">Tampoco encontramos un respaldo en Mixcloud asignado a este programa.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
