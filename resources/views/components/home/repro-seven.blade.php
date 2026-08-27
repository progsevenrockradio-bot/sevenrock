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
<div class="mt-6 border border-base-300 bg-base-200 p-4 shadow-[0_18px_48px_rgba(0,0,0,.35)]">
    <div class="mejs-container wp-audio-shortcode mejs-audio flex w-full flex-col gap-4" tabindex="0" role="application" aria-label="Audio Player">
        <div class="mejs-inner">
            <div class="flex items-start gap-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden border border-base-300 bg-base-300 shadow-[0_10px_30px_rgba(0,0,0,.35)]">
                    <img
                        :src="activeEpisode.image"
                        :alt="activeEpisode.program || activeEpisode.title || 'Podcast'"
                        width="1280"
                        height="720"
                        fetchpriority="high"
                        class="h-full w-full object-cover"
                    >
                </div>

                <div class="min-w-0 flex-1 overflow-hidden" 
                     x-data="{ isOverflowing: false }"
                     x-init="$watch('activeEpisode', () => { 
                                $nextTick(() => { 
                                    if($refs.titleText && $refs.titleWrapper) isOverflowing = $refs.titleText.scrollWidth > $refs.titleWrapper.clientWidth; 
                                }); 
                             });
                             window.addEventListener('resize', () => {
                                 if($refs.titleText && $refs.titleWrapper) {
                                     // Small hysteresis to prevent rapid toggling
                                     const currentScroll = $refs.titleText.scrollWidth;
                                     const wrapperWidth = $refs.titleWrapper.clientWidth;
                                     if (isOverflowing && currentScroll < wrapperWidth + 20) isOverflowing = false;
                                     else if (!isOverflowing && currentScroll > wrapperWidth) isOverflowing = true;
                                 }
                             });
                             setTimeout(() => { if($refs.titleText && $refs.titleWrapper) isOverflowing = $refs.titleText.scrollWidth > $refs.titleWrapper.clientWidth; }, 150);"
                >
                    <div class="text-[10px] uppercase tracking-[.28em] text-base-content/60">
                        ReproSeven
                    </div>
                    
                    <div x-ref="titleWrapper" class="mt-1 w-full overflow-hidden" :style="isOverflowing ? 'mask-image: linear-gradient(to right, transparent, black 10px, black calc(100% - 20px), transparent); -webkit-mask-image: linear-gradient(to right, transparent, black 10px, black calc(100% - 20px), transparent);' : ''">
                        <div :class="isOverflowing ? 'repro-marquee-snake flex w-max' : 'block truncate'">
                            <!-- Primary text -->
                            <p x-ref="titleText" 
                               class="font-display text-[12px] uppercase tracking-[.16em] text-primary"
                               :class="isOverflowing ? 'whitespace-nowrap pr-12' : 'truncate'"
                               x-text="activeEpisode.episode_title || 'Último episodio'"></p>
                               
                            <!-- Duplicate text for seamless snake loop, only visible when overflowing -->
                            <p x-show="isOverflowing" 
                               class="font-display text-[12px] uppercase tracking-[.16em] text-primary whitespace-nowrap pr-12"
                               x-text="activeEpisode.episode_title || 'Último episodio'" 
                               aria-hidden="true"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mejs-controls flex items-center gap-4 border-t border-base-300 pt-4 w-full">
            <!-- Play button -->
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-content shadow-lg transition-colors hover:bg-primary/80 disabled:cursor-not-allowed disabled:opacity-40"
                @click="togglePlayback()"
                :disabled="!activeEpisode.src && !activeEpisode.archive_url"
                aria-label="Play/Pause"
            >
                <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 ml-1">
                    <path fill-rule="evenodd" d="M4.5 5.653c0-1.426 1.529-2.33 2.779-1.643l11.54 6.348c1.295.712 1.295 2.573 0 3.285L7.28 19.991c-1.25.687-2.779-.217-2.779-1.643V5.653z" clip-rule="evenodd" />
                </svg>
                <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                    <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 01.75-.75H9a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75H7.5a.75.75 0 01-.75-.75V5.25zm7.5 0a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v13.5a.75.75 0 01-.75.75h-1.5a.75.75 0 01-.75-.75V5.25z" clip-rule="evenodd" />
                </svg>
            </button>

            <!-- Timer -->
            <div class="flex shrink-0 items-center gap-1 text-[11px] font-display uppercase tracking-[.18em] text-base-content/80">
                <span x-text="formatTime(currentTime)"></span>
                <span class="mx-0.5 text-base-content/40">/</span>
                <span x-text="formatTime(fixedDuration)"></span>
            </div>

            <!-- Custom Progress Bar -->
            <div class="group relative flex-1 h-1.5 cursor-pointer rounded-full bg-base-300" @click="seekAudio($event)">
                <div class="absolute left-0 top-0 h-full rounded-full bg-primary transition-all duration-100" :style="'width: ' + progressWidth"></div>
                <div class="absolute top-1/2 -ml-1.5 -mt-1.5 h-3 w-3 rounded-full bg-base-content opacity-0 shadow transition-opacity group-hover:opacity-100" :style="'left: ' + progressWidth"></div>
            </div>

            <!-- Volume -->
            <div class="hidden sm:flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    class="text-base-content/60 transition-colors hover:text-base-content"
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
                <div class="flex items-center w-20">
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
