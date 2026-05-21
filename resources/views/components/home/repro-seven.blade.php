<div class="mt-6 rounded-[18px] border border-[#2b2b2b] bg-[#0f0f0f] p-4 shadow-[0_18px_48px_rgba(0,0,0,.35)]">
    <div class="flex items-center gap-3">
        <div class="h-16 w-16 shrink-0 overflow-hidden border border-[#2b2b2b] bg-[#111] shadow-[0_10px_30px_rgba(0,0,0,.35)]">
            <img
                :src="activeEpisode.image"
                :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                class="h-full w-full object-cover"
            >
        </div>

        <div class="min-w-0 flex-1">
            <div class="font-display text-[10px] uppercase tracking-[.22em] text-[#7b7b7b]">
                ReproSeven
            </div>
            <h4 class="mt-1 truncate font-display text-[14px] uppercase tracking-[.12em] text-[#e8e8e8]" x-text="activeEpisode.program || activeEpisode.title || 'Podcast'"></h4>
            <p class="mt-1 truncate text-[11px] uppercase tracking-[.18em] text-lucille-accent" x-text="activeEpisode.host || ''"></p>
        </div>
    </div>

    <div class="mt-4 flex items-center gap-3">
        <button
            type="button"
            class="inline-flex h-7 items-center justify-center border border-[#d42426] bg-[#d42426] px-2.5 py-0 text-[9px] font-display uppercase tracking-[.14em] text-white transition-colors hover:bg-[#ba1f22] disabled:cursor-not-allowed disabled:opacity-40"
            @click="togglePlayback()"
            :disabled="!activeEpisode.src"
        >
            <span x-show="!playing">Play</span>
            <span x-show="playing">Pause</span>
        </button>

        <div class="min-w-0 flex-1">
            <div class="h-[10px] overflow-hidden border border-[#2b2b2b] bg-[#171717]">
                <div class="h-full bg-lucille-accent transition-[width] duration-300" :style="`width: ${progressWidth}`"></div>
            </div>
        </div>
    </div>

    <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
        <div class="flex items-center gap-3">
            <span class="text-[10px] uppercase tracking-[.22em] text-[#7b7b7b]">Volumen</span>
            <input
                type="range"
                min="0"
                max="100"
                step="1"
                :value="volume"
                @input="setVolume($event.target.value)"
                class="h-1 flex-1 cursor-pointer appearance-none rounded-full bg-[#2b2b2b] accent-lucille-accent"
            >
        </div>

        <div class="font-display text-[11px] uppercase tracking-[.22em] text-[#cfcfcf]" x-text="timeLabel"></div>
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
