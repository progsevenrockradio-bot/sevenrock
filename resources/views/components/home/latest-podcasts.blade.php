@props(['podcasts'])

@php
    use App\Support\PublicMediaUrl;

    $fallbackImage = asset('assets/lucille/logo.png');

    $normalizeEpisode = static function (array $episode) use ($fallbackImage): array {
        $program = trim((string) data_get($episode, 'program', data_get($episode, 'title', 'Podcast')));
        $episodeTitle = trim((string) data_get($episode, 'episode_title', data_get($episode, 'title', $program)));
        $host = trim((string) data_get($episode, 'host', 'Seven Rock Radio'));
        $date = trim((string) data_get($episode, 'date', ''));
        $summary = trim((string) data_get($episode, 'summary', ''));
        $src = trim((string) data_get($episode, 'src', ''));
        $archiveUrl = trim((string) data_get($episode, 'archive_url', data_get($episode, 'url', '')));
        $image = PublicMediaUrl::normalizePublicUrl((string) data_get($episode, 'image', ''));

        return [
            'id' => trim((string) data_get($episode, 'id', '')),
            'program' => $program !== '' ? $program : 'Podcast',
            'title' => trim((string) data_get($episode, 'title', $program !== '' ? $program : 'Podcast')),
            'episode_title' => $episodeTitle !== '' ? $episodeTitle : ($program !== '' ? $program : 'Podcast'),
            'host' => $host !== '' ? $host : 'Seven Rock Radio',
            'date' => $date,
            'summary' => $summary !== '' ? $summary : 'Episodio listo para escuchar desde la portada.',
            'image' => $image !== '' ? $image : $fallbackImage,
            'src' => $src,
            'archive_url' => $archiveUrl,
            'url' => trim((string) ($archiveUrl !== '' ? $archiveUrl : $src)),
        ];
    };

    $featured = $normalizeEpisode(data_get($podcasts, 'featured', []));
    $episodes = collect(data_get($podcasts, 'episodes', []))
        ->take(7)
        ->map(fn (array $episode) => $normalizeEpisode($episode))
        ->values()
        ->all();

    if (($featured['src'] ?? '') === '' && isset($episodes[0]['src']) && $episodes[0]['src'] !== '') {
        $featured['src'] = $episodes[0]['src'];
        $featured['archive_url'] = $episodes[0]['archive_url'] ?? '';
        $featured['url'] = $episodes[0]['url'] ?? $featured['url'];
    }

    $heroEpisode = $featured;
    $sidebarEpisodes = array_slice($episodes, 1);

    if ($sidebarEpisodes === [] && $episodes !== []) {
        $sidebarEpisodes = $episodes;
    }
@endphp

<div
    class="mt-[60px] grid gap-6 lg:grid-cols-[1.15fr_.85fr]"
    x-data="{
        activeEpisode: @js($heroEpisode),
        sidebarEpisodes: @js($sidebarEpisodes),
        infoModalOpen: false,
        playing: false,
        muted: false,
        volume: 85,
        elapsed: 0,
        duration: 0,
        progress: 0,
        init() {
            this.syncAudio(false);
        },
        episodeKey(episode) {
            return [episode?.id || '', episode?.src || '', episode?.archive_url || '', episode?.program || '', episode?.episode_title || ''].join('|');
        },
        isActiveEpisode(episode) {
            return this.episodeKey(this.activeEpisode) === this.episodeKey(episode);
        },
        normalizeEpisode(episode) {
            return {
                id: episode?.id || '',
                program: episode?.program || episode?.title || 'Podcast',
                title: episode?.title || episode?.program || 'Podcast',
                episode_title: episode?.episode_title || episode?.program || episode?.title || 'Podcast',
                host: episode?.host || 'Seven Rock Radio',
                date: episode?.date || '',
                summary: episode?.summary || 'Episodio listo para escuchar desde la portada.',
                image: episode?.image || '{{ $fallbackImage }}',
                src: episode?.src || '',
                archive_url: episode?.archive_url || '',
                url: episode?.url || episode?.archive_url || episode?.src || '',
            };
        },
        selectEpisode(episode) {
            this.activeEpisode = this.normalizeEpisode(episode);
            this.infoModalOpen = false;
            this.syncAudio(false);
            this.play();
        },
        openInfoModal() {
            this.infoModalOpen = true;
        },
        closeInfoModal() {
            this.infoModalOpen = false;
        },
        syncAudio(autoplay = false) {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            const nextEpisode = this.normalizeEpisode(this.activeEpisode);
            this.activeEpisode = nextEpisode;

            const source = nextEpisode.src || '';
            const currentSource = audio.getAttribute('src') || '';
            audio.volume = this.volume / 100;
            audio.muted = this.muted;

            if (!source) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
                this.playing = false;
                this.elapsed = 0;
                this.duration = 0;
                this.progress = 0;
                return;
            }

            if (currentSource !== source) {
                audio.pause();
                audio.removeAttribute('src');
                audio.src = source;
                audio.load();
                this.elapsed = 0;
                this.duration = 0;
                this.progress = 0;
            }

            if (autoplay) {
                this.play();
            }
        },
        async play() {
            const audio = this.$refs.audio;
            if (!audio || !this.activeEpisode?.src) {
                return;
            }

            this.syncAudio(false);

            try {
                await audio.play();
            } catch (error) {
                this.playing = false;
            }
        },
        pause() {
            const audio = this.$refs.audio;
            if (audio) {
                audio.pause();
            }
        },
        togglePlayback() {
            if (this.playing) {
                this.pause();
                return;
            }

            this.play();
        },
        setVolume(value) {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            const nextVolume = Math.max(0, Math.min(100, Number(value) || 0));
            this.volume = nextVolume;
            audio.volume = nextVolume / 100;

            if (nextVolume > 0 && audio.muted) {
                audio.muted = false;
            }

            this.muted = audio.muted;
        },
        onLoadedMetadata() {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            this.duration = Number.isFinite(audio.duration) && audio.duration > 0 ? Math.round(audio.duration) : 0;
            this.progress = this.duration > 0 ? Math.min(100, (this.elapsed / this.duration) * 100) : 0;
        },
        onTimeUpdate() {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            this.elapsed = Number.isFinite(audio.currentTime) && audio.currentTime > 0 ? Math.round(audio.currentTime) : 0;
            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                this.duration = Math.round(audio.duration);
                this.progress = Math.min(100, (audio.currentTime / audio.duration) * 100);
            }
        },
        onPlay() {
            this.playing = true;
        },
        onPause() {
            this.playing = false;
        },
        onEnded() {
            this.playing = false;
            this.elapsed = 0;
            this.progress = 0;
        },
        formatTime(seconds) {
            const total = Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : 0;
            const minutes = Math.floor(total / 60);
            const remainder = total % 60;
            return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
        },
        get progressWidth() {
            return `${Math.max(0, Math.min(100, this.progress))}%`;
        },
        get timeLabel() {
            return `${this.formatTime(this.elapsed)} / ${this.formatTime(this.duration)}`;
        },
    }"
>
    <article class="home-panel overflow-hidden">
        <div class="p-4 md:p-6 lg:p-7">
            <div class="overflow-hidden border border-[#2b2b2b] bg-[#111]">
                <img
                    :src="activeEpisode.image"
                    :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                    class="block h-[220px] w-full bg-[#111] object-contain p-3 sm:h-[250px] md:h-[300px]"
                >
            </div>

            <div class="mt-4 flex items-center gap-3">
                <span class="h-px w-16 bg-lucille-accent/90"></span>
                <span class="h-px w-12 bg-lucille-accent/90"></span>
            </div>

            <div class="mt-4 max-w-[620px]">
                <span class="home-badge" x-text="activeEpisode.episode_title || 'Nuevo episodio'"></span>

                <div class="mt-3">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between md:gap-6">
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display text-[24px] uppercase leading-[.95] tracking-[.12em] md:text-[34px]" x-text="activeEpisode.program || activeEpisode.title"></h3>
                            <p class="mt-3 text-[12px] uppercase tracking-[.24em] text-[#dcdcdc]" x-text="activeEpisode.date || 'Archive.org'"></p>
                            <p class="mt-2 font-display text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeEpisode.host || ''"></p>
                        </div>

                        <div class="flex flex-wrap gap-2 md:shrink-0 md:pt-1">
                            <button
                                type="button"
                                class="inline-flex h-7 items-center justify-center border border-[#dcdcdc] bg-transparent px-2.5 py-0 text-[9px] font-display uppercase tracking-[.14em] text-[#dcdcdc] transition-colors hover:bg-white/5"
                                @click="openInfoModal()"
                            >
                                Info
                            </button>
                        </div>
                    </div>

                    <x-home.repro-seven />
                </div>
            </div>
        </div>
    </article>

    <aside class="home-panel p-0">
        <div class="border-b border-[#2b2b2b] px-6 py-5">
            <div class="font-display text-sm uppercase tracking-[.22em] text-[#dcdcdc]">Últimos episodios</div>
            <div class="mt-2 text-sm text-[#7b7b7b]">Archive.org</div>
        </div>

        @if ($sidebarEpisodes !== [])
            <div class="grid gap-0 divide-y divide-[#2b2b2b]">
                @foreach ($sidebarEpisodes as $episode)
                    <button
                        type="button"
                        class="flex items-center gap-3 px-4 py-3 text-left transition-colors duration-300 hover:bg-[rgba(255,255,255,.03)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent/80"
                        :class="isActiveEpisode(@js($episode)) ? 'bg-[rgba(195,39,32,.08)] ring-1 ring-lucille-accent/50' : ''"
                        @click="selectEpisode(@js($episode))"
                    >
                        <div class="h-16 w-16 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#111] md:h-18 md:w-18">
                            <img src="{{ $episode['image'] }}" alt="{{ $episode['program'] }}" class="h-full w-full object-cover transition duration-500 ease-out hover:scale-[1.02]">
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] uppercase tracking-[.22em] text-[#7b7b7b]">
                                {{ $episode['date'] ?: 'Archive.org' }}
                            </div>
                            <div class="mt-1 font-display text-[14px] uppercase tracking-[.12em] text-[#dcdcdc] md:text-[15px]">
                                {{ $episode['program'] }}
                            </div>
                            <div class="mt-1 truncate text-[12px] text-[#7b7b7b]">
                                {{ $episode['host'] }}
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @else
            <div class="px-6 py-10 text-sm text-[#7b7b7b]">
                No hay episodios listos todavía.
            </div>
        @endif
    </aside>

    <div
        x-show="infoModalOpen"
        x-cloak
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 px-4 py-8"
        @keydown.escape.window="closeInfoModal()"
        @click.self="closeInfoModal()"
    >
        <div class="w-full border border-[#2b2b2b] bg-[#111] p-5 shadow-[0_24px_80px_rgba(0,0,0,.65)]" style="width:min(560px, calc(100vw - 32px)); max-width:none;">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="home-badge" x-text="activeEpisode.episode_title || 'Nuevo episodio'"></div>
                    <h4 class="mt-3 font-display text-[22px] uppercase leading-none tracking-[.12em]" x-text="activeEpisode.program || activeEpisode.title || 'Podcast'"></h4>
                    <p class="mt-2 text-xs uppercase tracking-[.24em] text-[#bfbfbf]" x-text="activeEpisode.date || 'Archive.org'"></p>
                    <p class="mt-1 font-display text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeEpisode.host || ''"></p>
                </div>

                <button
                    type="button"
                    class="h-10 w-10 border border-[#2b2b2b] text-[#dcdcdc] transition-colors hover:bg-white/5"
                    @click="closeInfoModal()"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>

            <div class="mt-4 space-y-3 border-t border-[#2b2b2b] pt-4">
                <p class="text-sm leading-7 text-[#d8d8d8]" x-text="activeEpisode.summary || 'Episodio listo para escuchar desde la portada.'"></p>
                <div class="flex flex-wrap gap-3">
                    <a
                        class="lucille-button-solid"
                        :href="activeEpisode.archive_url || activeEpisode.url || '#'"
                        target="_blank"
                        rel="noopener"
                    >
                        Abrir en Archive.org
                    </a>
                    <button
                        type="button"
                        class="lucille-button-solid"
                        @click="closeInfoModal()"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
