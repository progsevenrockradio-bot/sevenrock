<div class="mt-6 border border-[#2b2b2b] bg-[#101010] p-4 shadow-[0_18px_48px_rgba(0,0,0,.35)]">
    <div class="mejs-container wp-audio-shortcode mejs-audio flex w-full flex-col gap-4" tabindex="0" role="application" aria-label="Audio Player">
        <div class="mejs-inner">
            <div class="flex items-start gap-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#0f0f0f] shadow-[0_10px_30px_rgba(0,0,0,.35)]">
                    <img
                        :src="activeEpisode.image"
                        :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                        width="1280"
                        height="720"
                        fetchpriority="high"
                        class="h-full w-full object-cover"
                    >
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[.28em] text-[#7b7b7b]">
                        ReproSeven
                    </div>
                    <p class="mt-1 truncate font-display text-[12px] uppercase tracking-[.16em] text-lucille-accent" x-text="(activeEpisode.episode_title || 'Último episodio').split(' ').slice(0, 2).join(' ')"></p>
                </div>
            </div>
        </div>

        <div class="mejs-controls flex flex-col gap-3 border-t border-[#2b2b2b] pt-4">
            <div class="flex items-center gap-3 w-full">
                <div class="mejs-button mejs-playpause-button shrink-0">
                    <button
                        type="button"
                        class="inline-flex h-10 min-w-[64px] items-center justify-center border border-lucille-accent bg-transparent px-3 py-0 text-[10px] font-display uppercase tracking-[.16em] text-lucille-accent transition-colors hover:bg-lucille-accent/10 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="togglePlayback()"
                        :disabled="!activeEpisode.src && !activeEpisode.archive_url"
                    >
                        <span x-show="!playing">Play</span>
                        <span x-show="playing">Pause</span>
                    </button>
                </div>

                <div class="mejs-time mejs-currenttime-container flex shrink-0 items-center gap-1 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc]">
                    <span x-text="formatTime(elapsed)"></span>
                    <span class="mx-0.5 text-[#595959]">/</span>
                    <span x-text="formatTime(duration)"></span>
                </div>

                <div class="mejs-time-rail flex-1">
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        :value="progress"
                        @input="seekAudio(Number($event.target.value) || 0)"
                        class="lucille-range-slider"
                        aria-label="Time Slider"
                    >
                </div>
            </div>

            <div class="flex items-center gap-3 w-full">
                <div class="mejs-button mejs-volume-button mejs-mute shrink-0">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center border border-lucille-accent bg-transparent text-lucille-accent transition-colors hover:bg-lucille-accent/10"
                        @click="muted = !muted; const audio = $refs.audio; if (audio) { audio.muted = muted; }"
                        :aria-pressed="muted ? 'true' : 'false'"
                        aria-label="Mute"
                        title="Mute"
                    >
                        <svg x-show="!muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M5.889 6H4a1 1 0 00-1 1v6a1 1 0 001 1h1.889l4.265 4.437A.75.75 0 0011.5 17.89V2.11a.75.75 0 00-1.346-.546L5.89 6zM14.05 4.95a.75.75 0 011.06 0 7.5 7.5 0 010 10.1.75.75 0 11-1.06-1.06 6 6 0 000-8.08.75.75 0 010-1.06z" />
                        </svg>
                        <svg x-show="muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M5.889 6H4a1 1 0 00-1 1v6a1 1 0 001 1h1.889l4.265 4.437A.75.75 0 0011.5 17.89V2.11a.75.75 0 00-1.346-.546L5.89 6zM17.06 5.06a.75.75 0 10-1.06-1.06L14 6.06l-2-2a.75.75 0 10-1.06 1.06l2 2-2 2a.75.75 0 101.06 1.06l2-2 2 2a.75.75 0 101.06-1.06l-2-2 2-2z" />
                        </svg>
                    </button>
                </div>

                <div class="mejs-horizontal-volume-slider flex flex-1 items-center gap-3 w-full sm:max-w-[150px]">
                    <span class="text-[9px] font-display uppercase tracking-[.16em] text-[#7b7b7b] whitespace-nowrap sm:hidden">Vol</span>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        :value="volume"
                        @input="setVolume($event.target.value)"
                        class="lucille-range-slider"
                        aria-label="Volume Slider"
                    >
                    <span class="text-[9px] font-display text-[#7b7b7b] w-6 text-right sm:hidden" x-text="volume + '%'"></span>
                </div>
            </div>
        </div>
    </div>

    <audio
        x-ref="audio"
        preload="metadata"
        playsinline
        @loadedmetadata="onLoadedMetadata()"
        @timeupdate="onTimeUpdate()"
        @play="onPlay()"
        @pause="onPause()"
        @ended="onEnded()"
        x-on:error="tryNextAudioSource()"
        @volumechange="volume = Math.round(($event.target.volume || 0) * 100); muted = $event.target.muted"
    ></audio>
</div>
