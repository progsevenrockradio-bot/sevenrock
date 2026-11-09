@props(['podcasts'])

@php
    use App\Models\MasterProgram;
    use App\Models\RadioProgram;
    use App\Support\PublicMediaUrl;
    use Illuminate\Support\Facades\Schema;

    $fallbackImage = data_get($themeAppearance ?? [], 'media.logo_url') ?: asset('assets/lucille/pedalboard-1511069_1920.jpg');

    $cleanEditorialText = static function (string $text): string {
        $cleaned = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $cleaned = (string) preg_replace('/\*{1,2}([^*]+)\*{1,2}/u', '$1', $cleaned);
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', $cleaned));

        if (
            $cleaned === '' ||
            strcasecmp($cleaned, 'Episodio listo para escuchar desde la portada.') === 0 ||
            str_starts_with(mb_strtolower($cleaned), 'no hay podcasts listos todavía')
        ) {
            return '';
        }

        return $cleaned;
    };

    $masterDescriptionsByName = [];
    $episodeSpecificDescriptions = [];

    try {
        if (Schema::hasTable('master_programs')) {
            $masterDescriptionsByName = MasterProgram::query()
                ->get(['id', 'nombre', 'descripcion', 'comentario_predeterminado'])
                ->mapWithKeys(function (MasterProgram $mp) use ($cleanEditorialText): array {
                    $key = mb_strtolower(trim((string) $mp->nombre));
                    $desc = $cleanEditorialText(implode(' ', array_filter([
                        trim((string) $mp->descripcion),
                        trim((string) $mp->comentario_predeterminado),
                    ])));
                    return $key !== '' ? [$key => $desc] : [];
                })
                ->all();
        }

        if (Schema::hasTable('radio_programs')) {
            $episodeRows = RadioProgram::query()
                ->latest('fecha_emision')
                ->latest('id')
                ->limit(40)
                ->get(['id', 'titulo_programa', 'live_title', 'live_description', 'resena', 'comentario_episodio']);

            foreach ($episodeRows as $row) {
                $epSpecific = $cleanEditorialText((string) (
                    $row->live_description
                    ?: $row->resena
                    ?: $row->comentario_episodio
                    ?: ''
                ));

                if ($epSpecific !== '') {
                    $episodeSpecificDescriptions['id:' . $row->id] = $epSpecific;
                    $titleKey = mb_strtolower(trim((string) ($row->live_title ?: $row->titulo_programa ?: '')));
                    if ($titleKey !== '' && ! isset($episodeSpecificDescriptions['title:' . $titleKey])) {
                        $episodeSpecificDescriptions['title:' . $titleKey] = $epSpecific;
                    }
                }
            }
        }
    } catch (\Throwable) {
        // Mantener resiliencia sin fallar si alguna tabla no está disponible
    }

    $normalizeEpisode = static function (array $episode) use (
        $fallbackImage,
        $cleanEditorialText,
        $masterDescriptionsByName,
        $episodeSpecificDescriptions
    ): array {
        $id = trim((string) data_get($episode, 'id', ''));
        $program = trim((string) data_get($episode, 'program', data_get($episode, 'title', 'Podcast')));
        $episodeTitle = trim((string) data_get($episode, 'episode_title', data_get($episode, 'title', $program)));
        $host = trim((string) data_get($episode, 'host', 'Seven Rock Radio'));
        $date = trim((string) data_get($episode, 'date', ''));

        $programKey = mb_strtolower($program);
        $epTitleKey = mb_strtolower($episodeTitle);

        // Prioridad 1: Descripción específica del episodio (si contiene texto útil)
        $specificFromDb = ($id !== '' ? ($episodeSpecificDescriptions['id:' . $id] ?? '') : '')
            ?: ($epTitleKey !== '' ? ($episodeSpecificDescriptions['title:' . $epTitleKey] ?? '') : '');
        $payloadSummary = $cleanEditorialText((string) data_get($episode, 'summary', data_get($episode, 'description', '')));

        // Prioridad 2: Si está vacía, descripción general de su programa
        $programGeneralDesc = $programKey !== '' ? ($masterDescriptionsByName[$programKey] ?? '') : '';

        // Prioridad 3: Si ambas faltan, conservar metadatos sin inventar contenido ('')
        $resolvedSummary = $specificFromDb !== ''
            ? $specificFromDb
            : ($payloadSummary !== '' ? $payloadSummary : $programGeneralDesc);

        $src = trim((string) data_get($episode, 'src', ''));
        $archiveUrl = trim((string) data_get($episode, 'archive_url', data_get($episode, 'url', '')));
        $image = PublicMediaUrl::normalizePublicUrl((string) data_get($episode, 'image', ''));
        $audioSources = collect(data_get($episode, 'audio_sources', []))
            ->push($src)
            ->filter(fn ($source): bool => trim((string) $source) !== '')
            ->map(fn ($source): string => trim((string) $source))
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $id,
            'program' => $program !== '' ? $program : 'Podcast',
            'title' => trim((string) data_get($episode, 'title', $program !== '' ? $program : 'Podcast')),
            'episode_title' => $episodeTitle !== '' ? $episodeTitle : ($program !== '' ? $program : 'Podcast'),
            'host' => $host !== '' ? $host : 'Seven Rock Radio',
            'date' => $date,
            'summary' => $resolvedSummary,
            'image' => $image !== '' ? $image : $fallbackImage,
            'src' => $src !== '' ? $src : (string) ($audioSources[0] ?? ''),
            'audio_sources' => $audioSources,
            'archive_url' => $archiveUrl,
            'url' => trim((string) ($archiveUrl !== '' ? $archiveUrl : $src)),
            'program_url' => route('programs.detail', ['identifier' => \Illuminate\Support\Str::slug($program !== '' ? $program : 'Podcast')]),
        ];
    };

    $featuredData = data_get($podcasts, 'featured');
    if (! is_array($featuredData) || empty($featuredData)) {
        $firstRaw = data_get($podcasts, 'episodes.0');
        $featured = is_array($firstRaw) && ! empty($firstRaw)
            ? $normalizeEpisode($firstRaw)
            : $normalizeEpisode([]);
    } else {
        $featured = $normalizeEpisode($featuredData);
    }
    $episodeIdentity = static function (array $episode): string {
        $parts = [
            trim((string) ($episode['src'] ?? '')),
            trim((string) ($episode['archive_url'] ?? '')),
            trim((string) ($episode['program'] ?? '')),
            trim((string) ($episode['title'] ?? '')),
            trim((string) ($episode['episode_title'] ?? '')),
            trim((string) ($episode['host'] ?? '')),
            trim((string) ($episode['date'] ?? '')),
        ];

        $normalized = array_map(static fn (string $value): string => strtolower(preg_replace('/\s+/', ' ', trim($value)) ?: ''), $parts);

        return implode('|', array_filter($normalized, static fn (string $value): bool => $value !== ''));
    };

    $episodes = collect(data_get($podcasts, 'episodes', []))
        ->map(fn (array $episode) => $normalizeEpisode($episode))
        ->unique($episodeIdentity)
        ->values()
        ->all();

    if (($featured['src'] ?? '') === '' && isset($episodes[0]['src']) && $episodes[0]['src'] !== '') {
        $featured['src'] = $episodes[0]['src'];
        $featured['archive_url'] = $episodes[0]['archive_url'] ?? '';
        $featured['url'] = $episodes[0]['url'] ?? $featured['url'];
    }

    $heroEpisode = $featured;
    $heroKey = $episodeIdentity($heroEpisode);
    $programIdentity = static function (array $episode) use ($episodeIdentity): string {
        $program = trim((string) ($episode['program'] ?? $episode['title'] ?? ''));
        $program = strtolower(preg_replace('/\s+/', ' ', $program) ?: '');

        return $program !== '' ? $program : $episodeIdentity($episode);
    };
    $heroProgramKey = $programIdentity($heroEpisode);

    $sidebarEpisodes = collect($episodes)
        ->reject(static fn (array $episode): bool => $episodeIdentity($episode) === $heroKey)
        ->reject(static fn (array $episode): bool => $programIdentity($episode) === $heroProgramKey)
        ->unique($programIdentity)
        ->values()
        ->all();

    if ($sidebarEpisodes === [] && $episodes !== []) {
        $sidebarEpisodes = collect($episodes)
            ->reject(static fn (array $episode): bool => $episodeIdentity($episode) === $heroKey)
            ->unique($programIdentity)
            ->values()
            ->all();
    }

    if (count($sidebarEpisodes) < 8 && $episodes !== []) {
        $currentKeys = collect($sidebarEpisodes)
            ->map($episodeIdentity)
            ->filter()
            ->flip();

        $extraEpisodes = collect($episodes)
            ->reject(static fn (array $episode): bool => $episodeIdentity($episode) === $heroKey)
            ->reject(static fn (array $episode): bool => $currentKeys->has($episodeIdentity($episode)))
            ->values()
            ->all();

        $sidebarEpisodes = array_merge($sidebarEpisodes, $extraEpisodes);
    }

    $sidebarEpisodes = array_slice($sidebarEpisodes, 0, 5);
    $allEpisodesList = collect([$heroEpisode])
        ->concat($sidebarEpisodes)
        ->unique($episodeIdentity)
        ->take(6)
        ->values()
        ->all();
@endphp

<div
    class="mx-auto mt-8 max-w-[1200px] px-6 md:mt-10"
    x-data="{
        activeEpisode: @js($heroEpisode),
        sidebarEpisodes: @js($allEpisodesList),
        descExpanded: false,
        formatearTituloJS(text) {
            if (!text) return '';
            let cleanText = text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            if (!cleanText) return '';
            let words = cleanText.split(' ').filter(Boolean);
            let totalWords = words.length;
            if (totalWords === 1) {
                let word = words[0];
                let len = word.length;
                if (len <= 1) return `<span class='text-white'>${word}</span>`;
                let half = Math.ceil(len / 2);
                return `<span class='text-white'>${word.substring(0, half)}</span><span class='text-lucille-accent'>${word.substring(half)}</span>`;
            }
            if (totalWords === 2) {
                return `<span class='text-white'>${words[0]}</span> <span class='text-lucille-accent'>${words[1]}</span>`;
            }
            let half = Math.ceil(totalWords / 2);
            return `<span class='text-white'>${words.slice(0, half).join(' ')}</span> <span class='text-lucille-accent'>${words.slice(half).join(' ')}</span>`;
        },
        playing: false,
        muted: false,
        volume: 85,
        currentTime: 0,
        fixedDuration: 0,
        progress: 0,
        failedAudioSources: [],
        progressInterval: null,
        init() {
            this.syncAudio(false);
            window.addEventListener('sr-live-playing', () => {
                if (this.playing) {
                    this.pause();
                }
            });

            this.progressInterval = setInterval(() => {
                if (this.playing && this.fixedDuration > 0) {
                    if (this.currentTime >= this.fixedDuration) {
                        this.currentTime = this.fixedDuration;
                    } else {
                        this.currentTime += 1;
                    }
                    this.progress = Math.min(100, (this.currentTime / this.fixedDuration) * 100);
                }
            }, 1000);
        },
        episodeKey(episode) {
            return [episode?.id || '', episode?.src || '', episode?.archive_url || '', episode?.program || '', episode?.episode_title || ''].join('|');
        },
        isActiveEpisode(episode) {
            return this.episodeKey(this.activeEpisode) === this.episodeKey(episode);
        },
        normalizeEpisode(episode) {
            const rawSummary = String(episode?.summary || '').trim();
            return {
                id: episode?.id || '',
                program: episode?.program || episode?.title || 'Podcast',
                title: episode?.title || episode?.program || 'Podcast',
                episode_title: episode?.episode_title || episode?.program || episode?.title || 'Podcast',
                host: episode?.host || 'Seven Rock Radio',
                date: episode?.date || '',
                summary: rawSummary === 'Episodio listo para escuchar desde la portada.' ? '' : rawSummary,
                image: episode?.image || '{{ $fallbackImage }}',
                src: episode?.src || '',
                audio_sources: Array.isArray(episode?.audio_sources) ? episode.audio_sources.filter(Boolean) : [],
                archive_url: episode?.archive_url || '',
                url: episode?.url || episode?.archive_url || episode?.src || '',
                program_url: episode?.program_url || '',
            };
        },
        hasLongSummary() {
            const text = String(this.activeEpisode?.summary || '').trim();
            return text.length > 180;
        },
        visibleSummary() {
            const text = String(this.activeEpisode?.summary || '').trim();
            if (!text) return '';
            if (this.descExpanded || text.length <= 180) return text;
            const sliced = text.slice(0, 180);
            const lastSpace = sliced.lastIndexOf(' ');
            return (lastSpace > 120 ? sliced.slice(0, lastSpace) : sliced) + '…';
        },
        selectEpisode(episode) {
            this.activeEpisode = this.normalizeEpisode(episode);
            this.descExpanded = false;
            this.syncAudio(false);
        },
        syncAudio(autoplay = false) {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            const nextEpisode = this.normalizeEpisode(this.activeEpisode);
            this.activeEpisode = nextEpisode;

            const source = this.firstAudioSource(nextEpisode);
            const currentSource = audio.getAttribute('src') || '';
            audio.volume = this.volume / 100;
            audio.muted = this.muted;

            if (!source) {
                audio.pause();
                audio.removeAttribute('src');
                audio.load();
                this.playing = false;
                this.currentTime = 0;
                this.fixedDuration = 0;
                this.progress = 0;
                return;
            }

            if (currentSource !== source) {
                audio.pause();
                audio.removeAttribute('src');
                audio.src = source;
                audio.load();
                this.failedAudioSources = [];
                this.playing = false;
                this.currentTime = 0;
                this.fixedDuration = 0;
                this.progress = 0;
            }

            if (autoplay) {
                this.play();
            }
        },
        async play() {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            await this.ensurePlayableSource();
            this.syncAudio(false);

            if (!audio.getAttribute('src')) {
                return;
            }

            try {
                if (!audio.currentSrc || audio.networkState === 0) {
                    audio.load();
                }

                document.querySelectorAll('audio').forEach((el) => {
                    if (el !== audio) {
                        try { el.pause(); } catch (_) {}
                    }
                });
                window.dispatchEvent(new CustomEvent('sr-podcast-playing'));

                await audio.play();

                if (this.activeEpisode) {
                    fetch(`/programas/track-play?program=${encodeURIComponent(this.activeEpisode.program || '')}&archive_url=${encodeURIComponent(this.activeEpisode.archive_url || '')}`).catch(() => {});
                }
            } catch (error) {
                this.playing = false;
                await this.tryNextAudioSource();
            }
        },
        firstAudioSource(episode) {
            const sources = Array.isArray(episode?.audio_sources) ? episode.audio_sources : [];
            return sources.find(Boolean) || episode?.src || '';
        },
        async ensurePlayableSource() {
            const episode = this.normalizeEpisode(this.activeEpisode);
            if (this.firstAudioSource(episode)) {
                this.activeEpisode = episode;
                return;
            }

            const resolved = await this.resolveArchiveAudioSource(episode);
            if (resolved) {
                episode.src = resolved;
                episode.audio_sources = [resolved];
                this.activeEpisode = episode;
            }
        },
        async tryNextAudioSource() {
            if (!this.playing) {
                return;
            }
            const audio = this.$refs.audio;
            const episode = this.normalizeEpisode(this.activeEpisode);
            const currentSource = audio?.getAttribute('src') || '';
            const sources = Array.isArray(episode.audio_sources) ? episode.audio_sources.filter(Boolean) : [];
            const failed = new Set(this.failedAudioSources || []);
            if (currentSource) {
                failed.add(currentSource);
            }
            this.failedAudioSources = Array.from(failed);

            const resolvedSource = await this.resolveArchiveAudioSource(episode);
            const nextSource = (!failed.has(resolvedSource) ? resolvedSource : '') || sources.find((source) => !failed.has(source)) || '';

            if (!audio || !nextSource || nextSource === currentSource) {
                return;
            }

            episode.src = nextSource;
            episode.audio_sources = [nextSource, ...sources.filter((source) => source !== nextSource)];
            this.activeEpisode = episode;
            audio.src = nextSource;
            audio.load();

            try {
                await audio.play();
            } catch (error) {
                this.playing = false;
            }
        },
        async resolveArchiveAudioSource(episode) {
            const archiveUrl = episode?.archive_url || '';
            if (!archiveUrl || !archiveUrl.includes('archive.org/details/')) {
                return '';
            }

            try {
                const response = await fetch(`${archiveUrl}?output=json`, { headers: { Accept: 'application/json' } });
                if (!response.ok) {
                    return '';
                }

                const payload = await response.json();
                const files = payload && typeof payload === 'object' && payload.files ? payload.files : {};
                const identifier = archiveUrl.split('/details/')[1]?.split(/[?#]/)[0] || '';
                const mp3Files = Object.entries(files)
                    .map(([name, file]) => ({
                        name: file?.name || name || '',
                        mtime: Number(file?.mtime || 0),
                        format: String(file?.format || '').toLowerCase(),
                    }))
                    .filter((file) => file.name && (file.name.toLowerCase().endsWith('.mp3') || file.format.includes('mp3')))
                    .sort((left, right) => right.mtime - left.mtime);

                if (!identifier || !mp3Files.length) {
                    return '';
                }

                return `https://archive.org/download/${encodeURIComponent(identifier)}/${mp3Files[0].name.replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/')}`;
            } catch (error) {
                return '';
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
        seekAudio(event) {
            const audio = this.$refs.audio;
            if (!audio || !this.fixedDuration) return;

            let percentage = 0;
            if (event && event.type === 'click') {
                const rect = event.currentTarget.getBoundingClientRect();
                const x = event.clientX - rect.left;
                percentage = Math.max(0, Math.min(100, (x / rect.width) * 100));
            } else {
                percentage = Math.max(0, Math.min(100, Number(event) || 0));
            }

            this.progress = percentage;
            const targetTime = (this.fixedDuration * percentage) / 100;
            this.currentTime = targetTime;

            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                audio.currentTime = targetTime;
            }
        },
        onLoadedMetadata() {
            const audio = this.$refs.audio;
            if (!audio) return;

            this.fixedDuration = Number.isFinite(audio.duration) && audio.duration > 0 ? Math.round(audio.duration) : 0;
            this.progress = this.fixedDuration > 0 ? Math.min(100, (this.currentTime / this.fixedDuration) * 100) : 0;
        },
        onTimeUpdate() {
            const audio = this.$refs.audio;
            if (!audio) return;

            const audioTime = Number.isFinite(audio.currentTime) && audio.currentTime > 0 ? Math.round(audio.currentTime) : 0;
            const diff = Math.abs(this.currentTime - audioTime);

            if (diff > 3) {
                this.currentTime = audioTime;
            }

            if (Number.isFinite(audio.duration) && audio.duration > 0) {
                this.fixedDuration = Math.round(audio.duration);
                this.progress = Math.min(100, (this.currentTime / this.fixedDuration) * 100);
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
            this.currentTime = 0;
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
            return `${this.formatTime(this.currentTime)} / ${this.formatTime(this.fixedDuration)}`;
        },
    }"
>
    <div class="flex flex-col gap-6 md:gap-8">
        {{-- Fila superior: Carátula del episodio seleccionado (izq) y Listado de episodios alineado en altura (der) --}}
        <div class="grid items-stretch gap-6 md:gap-8 lg:grid-cols-[minmax(300px,440px)_minmax(0,1fr)] lg:h-[380px]">
            {{-- Columna izquierda: Carátula del episodio seleccionado (fijada a la altura de la fila superior en escritorio) --}}
            <div class="lucille-flat-surface flex h-full items-center justify-center overflow-hidden bg-lucille-surface lg:h-[380px]">
                <img
                    :src="activeEpisode.image"
                    x-on:error="if (!$el.dataset.fallbackApplied) { $el.dataset.fallbackApplied = '1'; $el.src = '{{ $fallbackImage }}'; }"
                    :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                    width="1280"
                    height="720"
                    class="block h-full max-h-[360px] w-full object-contain sm:max-h-[380px] lg:h-full lg:max-h-[380px]"
                    loading="lazy"
                >
            </div>

            {{-- Columna derecha: Encabezado + Listado de episodios ampliado cuyo borde inferior coincide con el de la carátula --}}
            <div class="flex h-full min-w-0 flex-col lg:h-[380px]">
                {{-- Encabezado de la lista --}}
                <div class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-lucille-line/20 pb-2">
                    <span class="font-display text-xs uppercase tracking-[.22em] text-lucille-heading">Últimos episodios</span>
                    <span class="text-[11px] text-lucille-body">Selecciona un episodio para escuchar o ver su ficha</span>
                </div>

                {{-- Listado scrollable de episodios: en escritorio ocupa flex-1 min-h-0 para alinearse exactamente al borde inferior de la carátula --}}
                @if ($allEpisodesList !== [])
                    <div class="custom-scrollbar max-h-[260px] divide-y divide-white/8 overflow-y-auto border-t border-b border-lucille-line/20 lg:max-h-none lg:min-h-0 lg:flex-1" role="list" aria-label="Últimos episodios de podcast">
                        @foreach ($allEpisodesList as $episode)
                            <button
                                type="button"
                                role="listitem"
                                class="lucille-flat-row flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent transition-colors"
                                :class="isActiveEpisode(@js($episode)) ? 'lucille-flat-row--active' : ''"
                                :aria-pressed="isActiveEpisode(@js($episode)) ? 'true' : 'false'"
                                @click="selectEpisode(@js($episode))"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full transition-colors"
                                        :class="isActiveEpisode(@js($episode)) ? 'bg-lucille-accent' : 'bg-white/20'"
                                        aria-hidden="true"
                                    ></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5">
                                            <span class="font-display text-[13px] uppercase tracking-[.1em] text-[#e5e5e5] md:text-[14px]">
                                                {!! formatear_titulo($episode['program']) !!}
                                            </span>
                                            @if (!empty($episode['date']))
                                                <span class="text-[11px] text-lucille-body">{{ $episode['date'] }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 truncate text-[12px] text-[#b0b0b0]">
                                            {{ $episode['episode_title'] }}
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 font-display text-[10px] uppercase tracking-[.16em]"
                                    :class="isActiveEpisode(@js($episode)) ? 'text-[var(--lucille-accent-readable,#ff5b52)] font-bold' : 'text-lucille-body'"
                                    x-text="isActiveEpisode(@js($episode)) ? (playing ? 'Sonando' : 'Activo') : 'Seleccionar'"
                                ></span>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="lucille-flat-surface px-5 py-8 text-sm text-lucille-body">
                        No hay episodios listos todavía.
                    </div>
                @endif
            </div>
        </div>

        {{-- Fila inferior: Ficha completa del episodio, reproductor y descripción a todo el ancho --}}
        <article class="lucille-flat-surface w-full p-4 sm:p-5 md:p-6" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-lucille-line/15 pb-2.5">
                <span class="font-display text-[11px] uppercase tracking-[.2em] text-[var(--lucille-accent-readable,#ff5b52)]" x-text="activeEpisode.host || 'Seven Rock Radio'"></span>
                <span class="text-[11px] uppercase tracking-[.16em] text-lucille-body" x-text="activeEpisode.date || 'Archivo de podcast'"></span>
            </div>

            <div class="mt-3">
                <h3
                    class="font-display text-[22px] uppercase leading-tight tracking-[.1em] text-lucille-heading md:text-[28px]"
                    x-html="formatearTituloJS(activeEpisode.program || activeEpisode.title)"
                ></h3>

                <p
                    class="mt-1 text-[15px] sm:text-[16px] font-semibold leading-snug text-[#e2e2e2]"
                    x-text="activeEpisode.episode_title || 'Episodio destacado'"
                ></p>
            </div>

            {{-- Descripción editorial con espacio y toggle si es extensa --}}
            <template x-if="activeEpisode.summary">
                <div class="mt-3 border-t border-lucille-line/15 pt-3">
                    <p class="text-[13px] sm:text-[14px] leading-6 text-lucille-body" x-text="visibleSummary()"></p>
                    <template x-if="hasLongSummary()">
                        <button
                            type="button"
                            class="mt-1.5 inline-flex items-center gap-1 font-display text-[11px] uppercase tracking-[.14em] text-[var(--lucille-accent-readable,#ff5b52)] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                            :aria-expanded="descExpanded ? 'true' : 'false'"
                            @click="descExpanded = !descExpanded"
                        >
                            <span x-text="descExpanded ? 'Mostrar menos' : 'Leer más'"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Reproductor integrado aprovechando todo el ancho disponible --}}
            <div class="mt-4">
                <x-home.repro-seven />
            </div>

            {{-- Metadatos disponibles y enlace discreto «Ver episodio» --}}
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-lucille-line/15 pt-3">
                <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-[12px] text-lucille-body">
                    <span><strong class="font-semibold text-lucille-heading">Programa:</strong> <span x-text="activeEpisode.program"></span></span>
                    <span><strong class="font-semibold text-lucille-heading">Presenta:</strong> <span x-text="activeEpisode.host"></span></span>
                    <template x-if="activeEpisode.date">
                        <span><strong class="font-semibold text-lucille-heading">Fecha:</strong> <span x-text="activeEpisode.date"></span></span>
                    </template>
                </div>

                <template x-if="activeEpisode.program_url">
                    <a
                        :href="activeEpisode.program_url"
                        class="inline-flex min-h-[30px] items-center gap-1.5 font-display text-[11px] uppercase tracking-[.16em] text-lucille-heading transition-colors hover:text-[var(--lucille-accent-readable,#ff5b52)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                    >
                        <span>Ver episodio completo</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </template>
            </div>
        </article>
    </div>
</div>
