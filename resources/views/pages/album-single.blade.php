<x-layouts.site title="Seven Rock Radio - {{ $album['title'] }}"
    :description="'Escucha ' . $album['title'] . ' de ' . $album['artist'] . ' en Seven Rock Radio. Preview de 20 segundos.'"
    :og-image="$album['cover']">
    <x-sections.page-heading
        :title="$album['title']"
        :subtitle="$album['artist']"
        image="assets/lucille/album1.jpg"
        overlay="rgba(21,21,21,.86)"
        :categories="$album['categories']"
    />

    <section>
        <div class="lucille-content-box">
            <div
                x-data="{
                    activeTrack: null,
                    playing: false,
                    currentTime: 0,
                    duration: 0,
                    previewDuration: 20,

                    playTrack(track) {
                        if (!track.audio) {
                            return;
                        }
                        if (this.activeTrack && this.activeTrack.title === track.title) {
                            this.togglePlayback();
                            return;
                        }
                        this.activeTrack = track;
                        this.currentTime = 0;
                        this.duration = 0;
                        this.$nextTick(() => {
                            const audio = this.$refs.player;
                            if (!audio || !track.audio) return;
                            audio.src = track.audio;
                            audio.load();
                            this.$nextTick(() => {
                                audio.play().catch(() => this.playing = false);
                                this.playing = true;
                            });
                        });
                    },
                    togglePlayback() {
                        const audio = this.$refs.player;
                        if (!audio) return;
                        if (this.playing) {
                            audio.pause();
                            this.playing = false;
                        } else if (this.activeTrack) {
                            audio.play().catch(() => this.playing = false);
                            this.playing = true;
                        }
                    },
                    onTimeUpdate() {
                        const audio = this.$refs.player;
                        if (!audio) return;
                        this.currentTime = audio.currentTime || 0;
                        this.duration = audio.duration || 0;
                        // Auto-stop at 20 seconds (preview)
                        if (audio.currentTime >= this.previewDuration) {
                            audio.pause();
                            this.playing = false;
                            this.currentTime = this.previewDuration;
                        }
                    },
                    onEnded() {
                        this.playing = false;
                        this.currentTime = 0;
                    },
                    formatTime(s) {
                        const t = Number.isFinite(s) && s > 0 ? Math.floor(s) : 0;
                        return String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
                    },
                    get progressPct() {
                        const max = this.previewDuration;
                        return max > 0 ? Math.min(100, (this.currentTime / max) * 100) : 0;
                    },
                    get timeLabel() {
                        return this.formatTime(this.currentTime) + ' / ' + this.formatTime(this.previewDuration);
                    },
                    get isCurrentTrack() {
                        return (trackTitle) => this.activeTrack && this.activeTrack.title === trackTitle;
                    }
                }"
            >
                <div class="grid gap-8 lg:grid-cols-[40%_60%]">
                    {{-- Left column: cover + info --}}
                    <aside class="lg:pr-[15px]">
                        <img src="{{ $album['cover'] }}" alt="{{ $album['title'] }}" class="w-full max-w-[500px]" loading="lazy">

                        <div class="mt-[15px] space-y-[10px] text-[#7b7b7b]">
                            <p><span class="mr-2 text-[#dcdcdc]">Calendar:</span>{{ $album['date'] }}</p>
                            <p><span class="mr-2 text-[#dcdcdc]">Artist:</span>{{ $album['artist'] }}</p>
                            <p><span class="mr-2 text-[#dcdcdc]">Label:</span>{{ $album['label'] }}</p>
                            <p><span class="mr-2 text-[#dcdcdc]">Producer:</span>{{ $album['producer'] }}</p>
                            <p><span class="mr-2 text-[#dcdcdc]">Number of discs:</span>{{ $album['discs'] }}</p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-2">
                            @foreach ($album['buttons'] as $button)
                                <a href="{{ data_get($button, 'url', '#') }}" target="_blank" rel="noreferrer" class="lucille-button min-h-[32px] px-[13px] text-[10px] tracking-[2px]">{{ data_get($button, 'label', '') }}</a>
                            @endforeach
                        </div>
                    </aside>

                    {{-- Right column: tracks with functional player --}}
                    <div class="lg:pl-[15px]">
                        <div class="space-y-[2px]">
                            @foreach ($album['tracks'] as $track)
                                <div class="bg-[#222] px-5 py-3">
                                    <div class="mb-[3px] pl-[7px] text-[#dcdcdc]">
                                        <span class="mr-[5px]">{{ $loop->iteration }}.</span>{{ data_get($track, 'title', '') }}
                                    </div>
                                    @if (!empty(data_get($track, 'audio')))
                                        <button type="button"
                                            class="h-10 w-full rounded-sm border border-[#2b2b2b] bg-[#191919] transition-all duration-200 hover:border-lucille-accent/40 hover:bg-[#222] active:bg-[#252525] group"
                                            @click="playTrack({
                                                title: '{{ str_replace("'", "\'", data_get($track, 'title', '')) }}',
                                                audio: '{{ data_get($track, 'audio', '') }}'
                                            })"
                                            :class="{ 'border-lucille-accent/60 bg-[#222]': isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}') }">
                                            <div class="flex h-full items-center gap-3 px-3 text-xs text-[#7b7b7b]">
                                                <template x-if="isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}') && playing">
                                                    <span class="text-lg text-lucille-accent">⏸</span>
                                                </template>
                                                <template x-if="!(isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}') && playing)">
                                                    <span class="text-lg text-[#dcdcdc] transition-colors group-hover:text-lucille-accent">▶</span>
                                                </template>
                                                <span class="h-px flex-1 overflow-hidden rounded-full bg-[#3a3a3a]">
                                                    <template x-if="isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}')">
                                                        <span class="block h-full bg-lucille-accent transition-all duration-300"
                                                            :style="'width: ' + progressPct + '%'"></span>
                                                    </template>
                                                    <template x-if="!isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}')">
                                                        <span class="block h-full w-0 bg-lucille-accent"></span>
                                                    </template>
                                                </span>
                                                <template x-if="isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}')">
                                                    <span class="w-16 text-right tabular-nums" x-text="timeLabel"></span>
                                                </template>
                                                <template x-if="!isCurrentTrack('{{ str_replace("'", "\'", data_get($track, 'title', '')) }}')">
                                                    <span class="w-16 text-right">00:00 / 00:20</span>
                                                </template>
                                            </div>
                                        </button>
                                    @else
                                        <div class="flex h-10 items-center gap-3 rounded-sm border border-dashed border-[#2b2b2b] bg-[#191919] px-3 text-xs text-[#7b7b7b]">
                                            <span class="text-lg text-[#7b7b7b]">•</span>
                                            <span class="flex-1">Audio no disponible para este tema</span>
                                            @if (!empty(data_get($track, 'duration')))
                                                <span class="w-16 text-right tabular-nums">{{ data_get($track, 'duration') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8 space-y-5 text-[14px] leading-[26px] text-[#7b7b7b]">
                            @foreach ($album['content'] as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Hidden audio element --}}
                <audio x-ref="player" preload="none"
                    @timeupdate="onTimeUpdate()"
                    @ended="onEnded()"
                    @play="playing = true"
                    @pause="playing = false">
                </audio>
            </div>
        </div>
    </section>
</x-layouts.site>
