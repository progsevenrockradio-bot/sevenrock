<style>
    .repro-marquee-snake {
        animation: repro-marquee-snake-anim 15s linear infinite;
    }
    .repro-marquee-snake:hover {
        animation-play-state: paused;
    }
    @keyframes repro-marquee-snake-anim {
        0%, 15% { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }
</style>
<div class="mt-4 rounded-sm border border-white/10 bg-[#121215] p-3 sm:p-3.5 shadow-[0_6px_20px_rgba(0,0,0,0.3)] transition-colors duration-200" :class="{ 'border-lucille-accent/40 ring-1 ring-lucille-accent/20': playing }">
    <div class="mejs-container wp-audio-shortcode mejs-audio flex w-full flex-col gap-3" tabindex="0" role="application" aria-label="Reproductor del episodio seleccionado">
        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <!-- Botón Reproducir / Pausar -->
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-lucille-accent text-white shadow-[0_0_12px_rgba(195,39,32,0.35)] transition-all hover:brightness-110 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent focus-visible:ring-offset-2 focus-visible:ring-offset-[#121215] disabled:cursor-not-allowed disabled:opacity-40"
                    @click="togglePlayback()"
                    :disabled="!activeEpisode.src && !activeEpisode.archive_url"
                    :aria-label="playing ? 'Pausar episodio' : 'Reproducir episodio'"
                >
                    <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5 h-5 w-5" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.5 5.653c0-1.426 1.529-2.33 2.779-1.643l11.54 6.348c1.295.712 1.295 2.573 0 3.285L7.28 19.991c-1.25.687-2.779-.217-2.779-1.643V5.653z" clip-rule="evenodd" />
                    </svg>
                    <svg x-show="playing" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                        <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7.5 0a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75h-1.5a.75.75 0 01-.75-.75V5.25z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2 text-[11px] font-display uppercase tracking-[.14em]">
                        <span class="flex items-center gap-1.5 truncate text-[#c5c5c5]">
                            <span class="h-1.5 w-1.5 rounded-full" :class="playing ? 'bg-lucille-accent animate-pulse' : 'bg-white/30'"></span>
                            <span class="truncate" x-text="playing ? 'Reproduciendo episodio' : 'Listo para reproducir'"></span>
                        </span>
                        <span class="shrink-0 whitespace-nowrap font-mono text-[11px] tracking-normal text-[#f0f0f0]">
                            <span x-text="formatTime(currentTime)"></span>
                            <span class="mx-0.5 text-white/30">/</span>
                            <span x-text="formatTime(fixedDuration)"></span>
                        </span>
                    </div>

                    <!-- Barra de progreso -->
                    <div
                        class="group relative mt-2 h-1.5 w-full cursor-pointer rounded-full bg-white/12 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                        @click="seekAudio($event)"
                        tabindex="0"
                        role="slider"
                        aria-label="Progreso del episodio"
                        :aria-valuenow="Math.round(progress)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div class="absolute left-0 top-0 h-full rounded-full bg-lucille-accent transition-all duration-100" :style="'width: ' + progressWidth"></div>
                        <div class="absolute top-1/2 -ml-1.5 -mt-1.5 h-3 w-3 rounded-full bg-white opacity-0 shadow transition-opacity group-hover:opacity-100" :style="'left: ' + progressWidth"></div>
                    </div>
                </div>
            </div>

            <!-- Control de volumen -->
            <div class="flex items-center justify-end gap-2 sm:pl-1">
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-sm text-[#b5b5b5] transition-colors hover:bg-white/[0.06] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                    @click="muted = !muted; const audio = $refs.audio; if (audio) { audio.muted = muted; }"
                    :aria-pressed="muted ? 'true' : 'false'"
                    :aria-label="muted ? 'Activar sonido' : 'Silenciar'"
                    title="Volumen"
                >
                    <svg x-show="!muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path d="M5.889 6H4a1 1 0 00-1 1v6a1 1 0 001 1h1.889l4.265 4.437A.75.75 0 0011.5 17.89V2.11a.75.75 0 00-1.346-.546L5.89 6zM14.05 4.95a.75.75 0 011.06 0 7.5 7.5 0 010 10.1.75.75 0 11-1.06-1.06 6 6 0 000-8.08.75.75 0 010-1.06z" />
                    </svg>
                    <svg x-show="muted" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 text-lucille-accent" aria-hidden="true">
                        <path d="M5.889 6H4a1 1 0 00-1 1v6a1 1 0 001 1h1.889l4.265 4.437A.75.75 0 0011.5 17.89V2.11a.75.75 0 00-1.346-.546L5.89 6zM17.06 5.06a.75.75 0 10-1.06-1.06L14 6.06l-2-2a.75.75 0 10-1.06 1.06l2 2-2 2a.75.75 0 101.06 1.06l2-2 2 2a.75.75 0 101.06-1.06l-2-2 2-2z" />
                    </svg>
                </button>
                <div class="flex w-20 items-center">
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        :value="volume"
                        @input="setVolume($event.target.value)"
                        class="lucille-range-slider focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lucille-accent"
                        aria-label="Volumen del episodio"
                    >
                </div>
            </div>
        </div>
    </div>

    <audio
        x-ref="audio"
        preload="none"
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
