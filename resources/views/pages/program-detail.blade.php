<x-layouts.site title="Seven Rock Radio - {{ $program['title'] ?? 'Programa' }}">
    <x-sections.page-heading
        :title="$program['title'] ?? 'Programa'"
        :subtitle="$program['host'] ? 'Conduce: ' . $program['host'] : 'Programa de radio'"
        :image="$program['cover'] ?? null"
        overlay="rgba(19,19,19,.91)"
    />

    <section>
        <div class="lucille-content-box">
            @php $fallbackImage = asset('assets/lucille/logo.png'); @endphp

            {{-- Descripción del programa --}}
            @if (!empty($program['description']))
                <div class="mb-10 max-w-3xl">
                    <p class="text-sm leading-7 text-[#cbcbcb]">{{ $program['description'] }}</p>
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

                    play(episode, autoplay = true) {
                        this.activeEpisode = episode;
                        this.playerVisible = true;
                        if (autoplay) this.$nextTick(() => this.syncAudio(true));
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
                @if (!empty($episodes))
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="font-display text-2xl uppercase tracking-[.12em] text-[#dcdcdc]">
                            Episodios · <span class="text-lucille-accent">{{ count($episodes) }}</span>
                        </h2>
                    </div>

                    <div class="space-y-3">
                        @foreach ($episodes as $index => $episode)
                            @php
                                $epTitle = $episode['title'] ?? 'Episodio ' . ($index + 1);
                                $epSrc = $episode['src'] ?? '';
                                $epDuration = $episode['duration'] ?? '';
                                $epDate = isset($episode['published_at']) ? \Carbon\Carbon::createFromTimestamp((int)$episode['published_at'])->format('d/m/Y') : '';
                                $epSize = isset($episode['size']) ? round($episode['size'] / 1048576, 1) . ' MB' : '';
                                $epViews = $episode['views'] ?? '';
                            @endphp
                            <div class="group flex items-center gap-4 border border-[#2b2b2b] bg-[#111] p-4 transition-all duration-300 hover:border-lucille-accent/40 hover:shadow-[0_0_20px_rgba(195,39,32,.1)] {{ $activeEpisode ?? '' ? '' : '' }}"
                                :class="activeEpisode && activeEpisode.src === '{{ $epSrc }}' ? 'border-lucille-accent/60 bg-[#1a0a0a]' : ''">

                                {{-- Portada miniatura --}}
                                <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 overflow-hidden rounded border border-[#2b2b2b] bg-[#0a0a0a]">
                                    <img src="{{ $program['cover'] ?: $fallbackImage }}"
                                         alt="{{ $epTitle }}"
                                         class="h-full w-full object-cover" loading="lazy">
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
                                            <span class="text-lucille-accent/70">{{ $epDuration }}</span>
                                        @endif
                                        @if ($epSize)
                                            <span>{{ $epSize }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Botón Play --}}
                                @if ($epSrc)
                                    <button type="button"
                                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-full border border-white/20 bg-white/5 text-white transition-all hover:bg-white/10 hover:border-white/30 active:scale-90 shrink-0"
                                        @click="play({
                                            program: '{{ $program['title'] }}',
                                            title: '{{ $epTitle }}',
                                            src: '{{ $epSrc }}',
                                            image: '{{ $program['cover'] ?: $fallbackImage }}'
                                        })">
                                        <span class="text-sm sm:text-base leading-none ml-0.5">▶</span>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-16 text-center">
                        <p class="text-sm text-[#7b7b7b]">No hay episodios disponibles para este programa.</p>
                        <a href="{{ route('programs') }}" class="mt-4 inline-block text-[11px] uppercase tracking-[.18em] text-lucille-accent hover:underline">
                            ← Volver a programas
                        </a>
                    </div>
                @endif

                {{-- Reproductor flotante (igual que en programs) --}}
                <div x-show="playerVisible && activeEpisode" x-cloak
                    x-transition:enter="transition-all duration-400 ease-out"
                    x-transition:enter-start="translate-y-full opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition-all duration-300 ease-in"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-full opacity-0"
                    class="fixed bottom-0 left-0 right-0 z-[100] border-t border-[#2b2b2b] bg-gradient-to-t from-[#0a0a0a] via-[#0d0d0d] to-[#111] shadow-[0_-8px_40px_rgba(0,0,0,.6)]">

                    <div class="h-0.5 w-full bg-[#2b2b2b]">
                        <div class="h-full bg-lucille-accent transition-all duration-300 ease-linear"
                            :style="'width: ' + progressPct + '%'"></div>
                    </div>

                    <div class="mx-auto max-w-[1180px] px-3 py-2 sm:px-6 sm:py-4">
                        <template x-if="activeEpisode">
                            <div class="flex items-center gap-2 sm:gap-4">
                                <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-[2] sm:flex-1">
                                    <div class="h-9 w-9 sm:h-12 sm:w-12 shrink-0 overflow-hidden rounded border border-[#2b2b2b] bg-[#111] shadow-[0_4px_12px_rgba(0,0,0,.3)]">
                                        <img :src="activeEpisode.image || '{{ $fallbackImage }}'"
                                            :alt="activeEpisode.program" class="h-full w-full object-cover" loading="lazy">
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
                                        class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded border border-[#2b2b2b] bg-transparent text-[11px] sm:text-[14px] text-[#aaa] transition-colors hover:text-white hover:border-white/30"
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

            {{-- Link de vuelta --}}
            <div class="mt-10 text-center">
                <a href="{{ route('programs') }}"
                   class="inline-flex items-center gap-2 border border-[#2b2b2b] px-6 py-3 text-[10px] font-display uppercase tracking-[.18em] text-[#7b7b7b] transition-colors hover:text-lucille-accent hover:border-lucille-accent/40">
                    ← Todos los programas
                </a>
            </div>
        </div>
    </section>
</x-layouts.site>
