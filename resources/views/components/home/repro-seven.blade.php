<div class="mt-6 border border-[#2b2b2b] bg-[#101010] p-4 shadow-[0_18px_48px_rgba(0,0,0,.35)]">
    <div class="mejs-container wp-audio-shortcode mejs-audio flex flex-col gap-4" tabindex="0" role="application" aria-label="Audio Player" style="width:100%;">
        <div class="mejs-inner">
            <div class="flex items-start gap-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#0f0f0f] shadow-[0_10px_30px_rgba(0,0,0,.35)]">
                    <img
                        :src="activeEpisode.image"
                        :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                        class="h-full w-full object-cover"
                    >
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-[10px] uppercase tracking-[.28em] text-[#7b7b7b]">
                        ReproSeven
                    </div>
                    <h4 class="mt-1 truncate font-display text-[14px] uppercase tracking-[.12em] text-[#e8e8e8]" x-text="activeEpisode.program || activeEpisode.title || 'Podcast'"></h4>
                    <p class="mt-1 truncate text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeEpisode.host || ''"></p>
                </div>
            </div>
        </div>

        <div class="mejs-controls flex flex-wrap items-center gap-3 border-t border-[#2b2b2b] pt-4">
            <div class="mejs-button mejs-playpause-button">
                <button
                    type="button"
                    class="inline-flex h-10 min-w-[64px] items-center justify-center border border-[#dcdcdc] bg-transparent px-3 py-0 text-[10px] font-display uppercase tracking-[.16em] text-[#f5f5f5] transition-colors hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="togglePlayback()"
                    :disabled="!activeEpisode.src"
                >
                    <span x-show="!playing">Play</span>
                    <span x-show="playing">Pause</span>
                </button>
            </div>

            <div class="mejs-time mejs-currenttime-container flex items-center gap-2 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc]">
                <span x-text="formatTime(elapsed)"></span>
            </div>

            <div class="mejs-time-rail min-w-[180px] flex-1">
                <input
                    type="range"
                    min="0"
                    max="100"
                    step="1"
                    :value="progress"
                    @input="seekAudio(Number($event.target.value) || 0)"
                    class="mejs-time-slider h-2 w-full cursor-pointer appearance-none rounded-full bg-[#595959] accent-white"
                    aria-label="Time Slider"
                >
            </div>

            <div class="mejs-time mejs-duration-container flex items-center gap-2 text-[10px] font-display uppercase tracking-[.18em] text-[#dcdcdc]">
                <span x-text="formatTime(duration)"></span>
            </div>

            <div class="mejs-button mejs-volume-button mejs-mute">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-[#2b2b2b] bg-transparent text-[#f5f5f5] transition-colors hover:bg-white/5"
                    @click="muted = !muted; const audio = $refs.audio; if (audio) { audio.muted = muted; }"
                    :aria-pressed="muted ? 'true' : 'false'"
                    aria-label="Mute"
                    title="Mute"
                >
                    <span x-show="!muted">🔊</span>
                    <span x-show="muted">🔇</span>
                </button>
            </div>

            <div class="mejs-horizontal-volume-slider flex min-w-[120px] flex-1 items-center gap-3">
                <input
                    type="range"
                    min="0"
                    max="100"
                    step="1"
                    :value="volume"
                    @input="setVolume($event.target.value)"
                    class="h-2 w-full cursor-pointer appearance-none rounded-full bg-[#595959] accent-[#f5f5f5]"
                    aria-label="Volume Slider"
                >
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
        @volumechange="volume = Math.round(($event.target.volume || 0) * 100); muted = $event.target.muted"
    ></audio>
</div>
