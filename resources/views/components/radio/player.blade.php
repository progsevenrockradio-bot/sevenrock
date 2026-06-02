@props(['mode' => 'dock'])

@php
    $player = config('player');
    $theme = $themeAppearance;
    $fallbackCover = ! empty($theme['media']['home_album_cover_url'] ?? '')
        ? $theme['media']['home_album_cover_url']
        : asset('assets/lucille/album3.jpg');
@endphp

    <div
        class="radio-player"
        data-radio-player-root
        data-player-status-url="{{ route('api.player.status') }}"
        data-player-stream-url="{{ $player['streams']['direct'] }}"
        data-player-stream-alt-url="{{ $player['streams']['alt_direct'] }}"
        data-player-listen-url="{{ $player['streams']['listen'] }}"
        data-player-band-info-url="{{ route('api.player.band-info') }}"
        data-player-program-info-url="{{ route('api.player.program-info') }}"
        data-player-fallback-cover="{{ $fallbackCover }}"
        data-player-default-title="{{ $player['defaults']['title'] ?? '' }}"
        data-player-default-artist="{{ $player['defaults']['artist'] ?? '' }}"
        x-bind:data-mode="mode"
        x-data="radioPlayer({
            mode: @js($mode),
            statusUrl: @js(route('api.player.status')),
            streamUrl: @js($player['streams']['direct']),
        altStreamUrl: @js($player['streams']['alt_direct']),
        listenUrl: @js($player['streams']['listen']),
        bandInfoUrl: @js(route('api.player.band-info')),
        programInfoUrl: @js(route('api.player.program-info')),
            playlistM3u: @js($player['streams']['m3u']),
            playlistPls: @js($player['streams']['pls']),
            fallbackCover: @js($fallbackCover),
            pollInterval: @js($player['poll_interval']),
            historyLimit: @js($player['history_limit']),
            defaultTitle: @js($player['defaults']['title'] ?? ''),
            defaultArtist: @js($player['defaults']['artist'] ?? ''),
        })"
    x-init="init(); $nextTick(() => { if (window.innerWidth <= 640) dockMinimized = true; })"
    >
    <audio x-ref="audio" data-radio-audio src="{{ $player['streams']['direct'] }}" preload="none" playsinline></audio>

    <div
        aria-hidden="true"
        style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden; pointer-events:none;"
    >
        <span id="rbcloud_nowplaying15715">Cargando...</span>
        <img
            id="rbcloud_cover8795"
            src="https://c30.radioboss.fm/w/artwork/569.png"
            width="300"
            height="300"
            alt=""
         loading="lazy">
        <script src="https://c30.radioboss.fm/w/nowplaying.js?u=569&amp;wid=15715&amp;nl=1&amp;nnt=1"></script>
        <script src="https://c30.radioboss.fm/w/cover.js?u=569&amp;wid=8795"></script>
    </div>

    @if ($mode === 'popup')
        <style>
            @keyframes srPopupWave {
                0%, 100% { transform: scaleY(.38); opacity: .55; }
                50% { transform: scaleY(1); opacity: 1; }
            }

            @keyframes fontCycle {
                0%, 16% {
                    opacity: 1;
                    font-family: 'Playfair Display', serif;
                }

                20% {
                    opacity: 0;
                }

                25%, 41% {
                    opacity: 1;
                    font-family: 'Bebas Neue', sans-serif;
                }

                45% {
                    opacity: 0;
                }

                50%, 66% {
                    opacity: 1;
                    font-family: 'Dancing Script', cursive;
                }

                70% {
                    opacity: 0;
                }

                75%, 91% {
                    opacity: 1;
                    font-family: 'Orbitron', monospace;
                }

                95%, 100% {
                    opacity: 0;
                }
            }

            .radio-player-popup-shell {
                display: flex;
                flex-direction: column;
                position: relative;
                height: 100vh;
                height: 100dvh;
                max-height: 100vh;
                overflow: hidden;
                background: #050505;
                color: #f4f1ea;
            }

            .radio-player-popup-bg,
            .radio-player-popup-overlay {
                position: absolute;
                inset: 0;
            }

            .radio-player-popup-bg {
                background-position: center;
                background-size: cover;
                transform: scale(1.08);
                filter: blur(44px) saturate(1.1);
                opacity: .38;
            }

            .radio-player-popup-overlay {
                background: linear-gradient(180deg, rgba(0,0,0,.40) 0%, rgba(7,7,9,.72) 44%, rgba(6,6,8,.96) 100%);
            }

            .radio-player-popup-shell button {
                font-family: var(--font-display);
            }

            .radio-player-popup-content-area {
                position: relative;
                z-index: 10;
                display: flex;
                flex: 1 1 auto;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
                gap: clamp(.5rem, 1.6vh, 1.5rem);
                padding: clamp(.75rem, 2vh, 1.5rem) clamp(1rem, 3vw, 1.5rem);
            }

            .radio-player-popup-stage {
                display: flex;
                flex: 1 1 auto;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
                min-height: 0;
                gap: clamp(.5rem, 1.2vh, 1rem);
                margin-top: clamp(0.5rem, 2vh, 1.5rem);
            }

            .radio-player-popup-track-wrap {
                width: min(90vw, 500px);
                max-width: 100%;
                flex: none;
            }

            .radio-player-popup-cover {
                width: clamp(140px, 35vw, 280px);
                height: clamp(140px, 35vw, 280px);
                max-width: 50vh;
                max-height: 50vh;
                object-fit: cover;
                flex-shrink: 0;
            }

            .radio-player-popup-meta {
                max-width: min(90vw, 32rem);
                text-align: center;
                margin-bottom: clamp(.125rem, .5vh, .5rem);
                padding-bottom: 0;
            }

            .radio-player-popup-meta p {
                margin-top: clamp(.125rem, .5vh, .25rem);
                font-size: clamp(.75rem, 1.8vw, 1rem);
            }

            .band-name-animated {
                display: block;
                overflow: hidden;
                color: #f4f1ea;
                font-size: clamp(1rem, 3vw, 1.5rem);
                line-height: 1.08;
                text-align: center;
                text-overflow: ellipsis;
                white-space: nowrap;
                transition: opacity .4s ease;
                animation: fontCycle 8s ease-in-out infinite;
            }

            .radio-player-popup-controls {
                display: flex;
                flex: none;
                flex-direction: column;
                gap: clamp(.5rem, 1vh, .9rem);
                width: min(100%, 560px);
                margin-top: clamp(0.25rem, 1vh, 0.75rem);
            }

            .radio-player-popup-time-row {
                display: flex;
                align-items: center;
                gap: clamp(.5rem, 1.3vw, .75rem);
                width: 100%;
            }

            .radio-player-popup-time-row .min-w-\[64px\] {
                min-width: clamp(3.5rem, 10vw, 4.5rem);
            }

            .radio-player-popup-play-button {
                width: clamp(3rem, 9vw, 4rem);
                height: clamp(3rem, 9vw, 4rem);
            }

            .radio-player-popup-fav-button {
                width: clamp(2.5rem, 7vw, 2.75rem);
                height: clamp(2.5rem, 7vw, 2.75rem);
            }

            .radio-player-popup-progress {
                width: 100%;
            }

            .radio-player-popup-volume {
                display: flex;
                align-items: center;
                flex-shrink: 0;
                gap: clamp(.5rem, 1.3vw, .75rem);
                width: 100%;
                border: 1px solid rgba(184,175,162,.10);
                background: rgba(0,0,0,.20);
                padding: clamp(.5rem, 1.2vh, .75rem) clamp(.75rem, 2vw, 1rem) clamp(.75rem, 2vh, 1.25rem);
                backdrop-filter: blur(18px);
            }

            .radio-player-popup-volume input {
                width: 100%;
            }

            .radio-player-popup-chip,
            .radio-player-popup-control {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(184,175,162,.22);
                background: rgba(0,0,0,.22);
                color: #ddd7cb;
                transition: transform .15s ease, border-color .15s ease, background .15s ease;
            }

            .radio-player-popup-chip:hover,
            .radio-player-popup-control:hover {
                transform: translateY(-1px);
                border-color: rgba(184,175,162,.38);
                background: rgba(0,0,0,.30);
            }

            .radio-player-popup-drawer {
                border-top: 1px solid rgba(184,175,162,.14);
                background: rgba(10,10,12,.76);
                backdrop-filter: blur(18px);
                flex-shrink: 0;
                max-height: 33vh;
            }

            .radio-player-popup-drawer-grid {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 0;
                border-bottom: 1px solid rgba(184,175,162,.10);
            }

            .radio-player-popup-drawer-grid button {
                min-height: clamp(2rem, 4vw, 2.625rem);
                border: 0;
                background: transparent;
                color: #b7ad9f;
                font-size: clamp(.55rem, 1.1vw, .75rem);
                letter-spacing: .16em;
                text-transform: uppercase;
                border-right: 1px solid rgba(184,175,162,.08);
            }

            .radio-player-popup-drawer-grid button.is-active {
                color: #fff;
                background: rgba(195,39,32,.12);
            }

            .radio-player-popup-drawer-body {
                max-height: clamp(100px, 25vh, 280px);
                overflow-y: auto;
                padding: clamp(.75rem, 1.8vw, 1.125rem);
            }

            .radio-player-popup-action-icon {
                font-size: clamp(1rem, 2vw, 1.125rem);
                line-height: 1;
            }

            .radio-player-popup-wavebar {
                width: clamp(.25rem, .5vw, .3rem);
                height: clamp(.9rem, 2vh, 1.125rem);
                border-radius: 9999px;
                background: linear-gradient(180deg, #ff5b4c 0%, #c32720 100%);
                transform-origin: bottom;
                animation: srPopupWave 1.2s ease-in-out infinite;
            }
        </style>

        <div class="radio-player-popup-shell">
            <div
                class="radio-player-popup-bg"
                :style="`background-image:url('${(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')}')`"
                aria-hidden="true"
            ></div>
            <div class="radio-player-popup-overlay" aria-hidden="true"></div>

            <div class="radio-player-popup-content-area popup-content-area">
                <header class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <span style="display:block; color:#8f887d; font-size:10px; letter-spacing:.34em; text-transform:uppercase;">Seven Rock Radio</span>
                        <div class="mt-1 flex items-center gap-2 min-w-0">
                            <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'POP-UP'"></span>
                            <span class="truncate text-[11px] text-zinc-300" x-text="track.program_name || (track.artist || defaultArtist)"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="radio-player-popup-chip rounded-full px-3 py-2 text-[10px] uppercase tracking-[.16em]" @click="shareCurrent()">Share</button>
                        <button type="button" class="radio-player-popup-chip rounded-full px-3 py-2 text-[10px] uppercase tracking-[.16em]" @click="window.close()">Cerrar</button>
                    </div>
                </header>

                <main class="radio-player-popup-stage">
                    <div class="radio-player-popup-track-wrap relative">
                        <div class="mx-auto flex w-full flex-col items-center gap-4 text-center">
                            <div class="relative">
                                <img
                                    class="radio-player-popup-cover rounded-[28px] object-cover shadow-[0_20px_50px_rgba(0,0,0,.80)] ring-1 ring-white/10"
                                    :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')"
                                    alt=""
                                    onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;"
                                    loading="lazy"
                                >
                                <div class="absolute bottom-3 right-3 flex items-end gap-1 rounded-full bg-black/45 px-2 py-1 backdrop-blur">
                                    <span class="radio-player-popup-wavebar" style="animation-delay:.05s;"></span>
                                    <span class="radio-player-popup-wavebar" style="animation-delay:.20s;height:14px;"></span>
                                    <span class="radio-player-popup-wavebar" style="animation-delay:.35s;height:20px;"></span>
                                    <span class="radio-player-popup-wavebar" style="animation-delay:.15s;height:16px;"></span>
                                </div>
                            </div>

                            <div class="radio-player-popup-meta max-w-[32rem] mb-4 pb-1">
                                <span class="radio-player-live-pill mx-auto mb-2" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'PLAYBACK'"></span>
                                <h1 class="m-0 text-2xl font-black uppercase tracking-tight text-white sm:text-3xl" x-text="track.title || defaultTitle"></h1>
                                <p class="mt-1 band-name-animated text-sm font-medium text-zinc-300 sm:text-base" x-text="track.artist || defaultArtist"></p>
                                <p class="mt-2 text-[11px] uppercase tracking-[.18em] text-zinc-500" x-text="track.is_live ? 'Transmitiendo ahora' : (track.program_name || '')"></p>
                                <p class="mt-1 text-[11px] uppercase tracking-[.18em] text-zinc-500" x-text="listeners > 0 ? `${listeners} oyentes` : ''"></p>
                            </div>
                        </div>
                    </div>

                    <div class="radio-player-popup-controls">
                        <div class="radio-player-popup-time-row">
                            <div class="flex-1 min-w-0">
                                <div class="rbcloud_tracktimer" style="display:flex; align-items:center; justify-content:flex-start; gap:8px; min-height:18px; color:#b7ad9f; font-family:var(--font-display); font-size:14px; letter-spacing:.18em; text-transform:uppercase; white-space:nowrap;">
                                    <span id="rbcloud_tracktimer_e11097"></span>
                                    <span id="rbcloud_tracktimer_sep11097" hidden> &frasl; </span>
                                    <span id="rbcloud_tracktimer_r11097"></span>
                                </div>
                            </div>
                            <button type="button" class="radio-player-popup-control radio-player-popup-play-button shrink-0 rounded-full border-2 border-white/60 bg-white text-black shadow-[0_20px_40px_rgba(0,0,0,.45)]" @click="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'">
                                <span class="radio-player-popup-action-icon" x-text="playing ? '❚❚' : '▶'"></span>
                            </button>
                            <div class="flex-1 min-w-0 flex justify-end">
                                <button type="button" class="radio-player-popup-control radio-player-popup-fav-button shrink-0 rounded-full text-lg text-zinc-300" @click="toggleFavorite()" :aria-label="isFavoriteCurrent() ? 'Quitar de favoritos' : 'Me gusta'" :aria-pressed="isFavoriteCurrent()">
                                    <span x-text="isFavoriteCurrent() ? '♥' : '♡'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="radio-player-popup-progress">
                            <button type="button" class="h-1.5 w-full overflow-hidden rounded-full bg-white/10" @click="seek($event)" aria-label="Progreso">
                                <span class="block h-full rounded-full bg-red-600" :style="`width:${progress.ratio}%`"></span>
                            </button>
                        </div>
                    </div>
                </main>

                <div class="radio-player-popup-volume">
                    <button type="button" class="radio-player-popup-control h-8 w-8 shrink-0 rounded-full border-white/10 bg-white/0 text-zinc-200" @click="toggleMute()" aria-label="Mute">
                        <span x-text="muted ? '🔇' : '🔊'"></span>
                    </button>
                    <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" class="h-1 w-full cursor-pointer accent-red-600">
                    <span class="min-w-[34px] text-right text-[11px] font-mono uppercase tracking-[.18em] text-zinc-400" x-text="Math.round(volume * 100) + '%'">80%</span>
                </div>

                <section class="radio-player-popup-drawer overflow-hidden rounded-[22px] border border-white/10" x-show="panelOpen" x-transition.opacity>
                    <div class="radio-player-popup-drawer-grid">
                        <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')">Letra</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')">Banda</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'program' }" @click="setTab('program')">Programa</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'notices' }" @click="setTab('notices')">Noticias</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'history' }" @click="setTab('history')">Historial</button>
                    </div>

                    <div class="radio-player-popup-drawer-body">
                        <div x-show="activeTab === 'lyrics'">
                            <h4 class="mb-3 text-[11px] uppercase tracking-[.22em] text-zinc-400">Letra del tema</h4>
                            <p class="whitespace-pre-line text-sm leading-7 text-zinc-200" x-text="track.lyrics || 'Letra no disponible'"></p>
                        </div>

                        <div x-show="activeTab === 'band'">
                            <h4 class="mb-3 text-[11px] uppercase tracking-[.22em] text-zinc-400">Info de banda</h4>
                            <p class="whitespace-pre-line text-sm leading-7 text-zinc-200" x-text="track.band_info || 'Buscando información de banda...'"></p>
                        </div>

                        <div x-show="activeTab === 'program'">
                            <h4 class="mb-3 text-[11px] uppercase tracking-[.22em] text-zinc-400">Programa</h4>
                            <p class="text-sm leading-7 text-zinc-200" x-text="programText()"></p>
                            <template x-if="nextProgram">
                                <div class="mt-4 rounded-[18px] border border-white/10 bg-white/5 p-4">
                                    <span class="mb-1 block text-[10px] uppercase tracking-[.22em] text-zinc-400">Próximo programa</span>
                                    <strong class="block text-sm text-white" x-text="nextProgram.name"></strong>
                                    <small class="block text-[11px] uppercase tracking-[.16em] text-zinc-500" x-text="nextProgram.schedule || ''"></small>
                                </div>
                            </template>
                        </div>

                        <div x-show="activeTab === 'notices'">
                            <h4 class="mb-3 text-[11px] uppercase tracking-[.22em] text-zinc-400">Noticias</h4>
                            <template x-if="notices.length">
                                <div class="space-y-3">
                                    <template x-for="notice in notices" :key="notice.title">
                                        <article class="rounded-[18px] border border-white/10 bg-white/5 p-4">
                                            <strong class="block text-sm text-white" x-text="notice.title"></strong>
                                            <p class="mt-2 text-sm leading-6 text-zinc-300" x-text="notice.content"></p>
                                        </article>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!notices.length" class="text-sm text-zinc-300">Sin avisos activos.</p>
                        </div>

                        <div x-show="activeTab === 'history'">
                            <h4 class="mb-3 text-[11px] uppercase tracking-[.22em] text-zinc-400">Historial</h4>
                            <template x-if="history.length">
                                <div class="space-y-3">
                                    <template x-for="item in history" :key="`${item.title}-${item.played_at}`">
                                        <article class="flex items-center gap-3 rounded-[18px] border border-white/10 bg-white/5 p-3">
                                            <img :src="item.cover || fallbackCover" alt="" loading="lazy" class="h-12 w-12 rounded-[12px] object-cover">
                                            <div class="min-w-0">
                                                <strong class="block truncate text-sm text-white" x-text="item.title"></strong>
                                                <p class="truncate text-sm text-zinc-300" x-text="item.artist || defaultArtist"></p>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!history.length" class="text-sm text-zinc-300">Sin historial todavía.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @else
        <div
            class="radio-player-dock"
            aria-label="Reproductor"
            :class="{ 'is-minimized': dockMinimized }"
            :style="dockMinimized
                ? 'position:fixed; left:50%; bottom:12px; z-index:90; display:grid; grid-template-columns:76px minmax(0,1fr) auto minmax(140px,200px); align-items:center; gap:8px; width:min(1100px, calc(100vw - 24px)); min-height:72px; padding:6px 12px 6px 10px; border:1px solid rgba(184,175,162,.28); background:linear-gradient(180deg, rgba(18,17,16,.98), rgba(12,11,10,.98)); box-shadow:0 10px 26px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.03); transform:translateX(-50%); pointer-events:auto;'
                : 'position:fixed; left:50%; bottom:12px; z-index:90; display:grid; grid-template-columns:100px minmax(0,1fr) auto minmax(180px,280px); align-items:center; gap:12px; width:min(1160px, calc(100vw - 24px)); min-height:84px; padding:10px 16px; border:1px solid rgba(184,175,162,.28); background:linear-gradient(180deg, rgba(18,17,16,.98), rgba(12,11,10,.98)); box-shadow:0 12px 32px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.03); transform:translateX(-50%); pointer-events:auto;"'
        >
                <div class="rbcloud_nowplaying" style="display:flex; flex-direction:column; gap:4px; min-width:0; align-items:flex-start; padding-left:0; margin-right:0;">
                    <button type="button" data-player-band-trigger @click="toggleInfoWindow()" aria-label="Abrir información" style="appearance:none; display:inline-flex; border:0; background:transparent; padding:0; cursor:pointer; text-align:left;">
                        <img class="radio-player-cover" data-player-cover-image :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')" alt="cover art" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" x-bind:style="dockMinimized ? 'width:48px; height:48px; border:1px solid rgba(184,175,162,.14); box-shadow:0 1px 6px rgba(0,0,0,.18); object-fit:cover; border-radius:6px;' : 'width:80px; height:80px; border:1px solid rgba(184,175,162,.18); box-shadow:0 1px 10px rgba(0,0,0,.2); object-fit:cover; border-radius:8px;'" loading="lazy">
                    </button>
                </div>

                <div class="radio-player-meta-column" style="display:flex; flex-direction:column; gap:2px; min-width:0; justify-content:center; padding-left:0; margin-left:8px; transform:translateY(0);">
                    <div class="radio-player-meta" style="min-width:0; gap:4px; align-items:flex-start;">
                        <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'PLAYBACK'" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:max-content; min-height:16px; padding:0 5px; border-radius:9999px; background:#b7ad9f; color:#151515; font-size:8px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;' : 'display:inline-flex; align-items:center; justify-content:center; width:max-content; min-height:22px; padding:0 8px; border-radius:9999px; background:#b7ad9f; color:#151515; font-size:10px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;'"></span>
                        <strong data-player-title-text x-bind:style="dockMinimized ? 'font-size:12px; color:#ddd7cb; line-height:1.1; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' : 'font-size:14px; color:#ddd7cb; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;'" x-text="track.title || defaultTitle"></strong>
                        <span x-show="!dockMinimized" data-player-artist-text style="font-size:12px; color:#b9b1a5; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" x-text="track.artist || defaultArtist"></span>
                        <small x-show="!dockMinimized && track.program_name" data-player-program-text
                            style="font-size:11px; color:#b7ad9f; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                            x-text="track.program_name"></small>
                        <small x-show="!dockMinimized && !track.program_name && nextProgram" data-player-next-program-text
                            style="font-size:11px; color:#b7ad9f; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                            x-text="'Próximo: ' + (nextProgram.name || '') + (nextProgram.schedule_time ? ' a las ' + nextProgram.schedule_time : '')"></small>
                    </div>
                    <div x-show="!dockMinimized" class="rbcloud_tracktimer" style="display:flex; align-items:center; justify-content:flex-start; gap:8px; min-height:18px; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.18em; text-transform:uppercase; white-space:nowrap; margin-top:2px;">
                        <span id="rbcloud_tracktimer_e11096"></span>
                        <span id="rbcloud_tracktimer_sep11096" hidden> &frasl; </span>
                        <span id="rbcloud_tracktimer_r11096"></span>
                    </div>
                    <div class="radio-player-mobile-share-popout radio-player-mobile-share-popout-inline" aria-label="Acciones móviles extra" style="display:none;">
                        <button type="button" class="radio-player-chip radio-player-mobile-share" @click="shareCurrent()">Share</button>
                        <button type="button" class="radio-player-chip radio-player-mobile-popout" @click="openPopout()">Pop-out</button>
                    </div>
                    <div class="radio-player-share-popout-desktop" x-show="!dockMinimized" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:6px;">
                        <button type="button" class="radio-player-chip" @click="shareCurrent()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;">Share</button>
                        <button type="button" class="radio-player-chip" @click="openPopout()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;">Pop-out</button>
                    </div>
                </div>
                <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11096"></script>
                <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11097"></script>

                <span class="radio-player-actions" style="display:flex; flex:0 0 auto; align-items:center; justify-content:center; gap:8px; white-space:nowrap; margin-left:auto; padding-right:4px;">
                    <button type="button" class="radio-player-icon" data-player-action="details" @click.stop="toggleInfoWindow()" title="Detalles" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.25); background:rgba(0,0,0,.15); color:#dcd7cc; border-radius:50%; cursor:pointer; font-size:10px; font-weight:600; line-height:1; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; border-radius:50%; cursor:pointer; font-size:13px; font-weight:600; line-height:1; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;'">i</button>
                    <div class="radio-player-actions-center" style="display:flex; align-items:center; justify-content:center; gap:8px; min-width:0; flex:1 1 auto;">
                        <button type="button" data-player-action="expand" @click.stop="dockMinimized = false" aria-label="Expandir" title="Expandir" x-show="dockMinimized" style="display:none; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.25); background:rgba(0,0,0,.15); color:#dcd7cc; cursor:pointer; border-radius:50%; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;" class="radio-player-expand-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block; margin:auto;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <button type="button" class="radio-player-primary" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:2px solid rgba(184,175,162,.4); background:rgba(184,175,162,.08); color:#eee; border-radius:50%; cursor:pointer; font-size:14px; line-height:1; transition:all .15s ease; flex-shrink:0; padding:0; margin:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border:2px solid rgba(184,175,162,.5); background:rgba(184,175,162,.12); color:#eee; border-radius:50%; cursor:pointer; font-size:16px; line-height:1; transition:all .15s ease; flex-shrink:0; padding:0; margin:0;'">
                            <span x-text="playing ? '❚❚' : '▶'">▶</span>
                        </button>
                        <button type="button" data-player-action="minimize" @click.stop="dockMinimized = true" aria-label="Minimizar" title="Minimizar" x-show="!dockMinimized" style="display:none; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; cursor:pointer; border-radius:50%; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;" class="radio-player-minimize-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block; margin:auto;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                        </button>
                    </div>
                    <div class="radio-player-actions-spacer" aria-hidden="true" style="flex:1 1 28px;"></div>
                    <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" x-bind:style="dockMinimized ? (isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; border-radius:50%; cursor:pointer; font-size:10px; flex-shrink:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.1); color:#dcd7cb; border-radius:50%; cursor:pointer; font-size:10px; flex-shrink:0;') : (isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; border-radius:50%; cursor:pointer; flex-shrink:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; border-radius:50%; cursor:pointer; flex-shrink:0;')">
                        <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                    </button>
                </span>

                <div class="sr-dock-volume" x-bind:style="dockMinimized ? 'display:flex; align-items:center; gap:0; min-width:36px; max-width:36px; justify-self:end;' : 'display:flex; align-items:center; gap:10px; min-width:160px; max-width:240px; justify-self:end; overflow:visible;'">
                    <button type="button" data-player-action="mute" @click.stop="toggleMute()" aria-label="Silenciar" title="Silenciar" x-bind:style="dockMinimized ? 'display:inline-flex; width:28px; height:28px; align-items:center; justify-content:center; border:0; background:transparent; color:#b7ad9f; border-radius:4px; cursor:pointer; flex-shrink:0;' : 'display:inline-flex; width:32px; height:32px; align-items:center; justify-content:center; border:1px solid rgba(184,175,162,.3); background:transparent; color:#b7ad9f; border-radius:4px; cursor:pointer; flex-shrink:0;'">
                        <span data-player-mute-muted-icon x-show="muted" aria-hidden="true" style="display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9l5 6"></path>
                                <path d="M21 9l-5 6"></path>
                            </svg>
                        </span>
                        <span data-player-mute-unmuted-icon x-show="!muted" aria-hidden="true" style="display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9a4 4 0 0 1 0 6"></path>
                                <path d="M19 7a8 8 0 0 1 0 10"></path>
                            </svg>
                        </span>
                    </button>
                    <input x-show="!dockMinimized" data-player-volume-input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" style="width:100%; accent-color:#b7ad9f; min-height:24px; cursor:pointer;">
                    <span x-show="!dockMinimized" data-player-volume-output x-text="Math.round(volume * 100) + '%'" style="color:#b7ad9f; font-family:var(--font-display); font-size:10px; letter-spacing:.12em; text-transform:uppercase; min-width:32px; text-align:right;">80%</span>
                </div>

                <div class="radio-player-mobile-share-popout" aria-label="Acciones móviles extra" style="display:none;">
                    <button type="button" class="radio-player-chip radio-player-mobile-share" @click="shareCurrent()">Share</button>
                    <button type="button" class="radio-player-chip radio-player-mobile-popout" @click="openPopout()">Pop-out</button>
                </div>

                <div class="radio-player-mobile-actions" aria-label="Controles móviles" style="display:none;">
                    <button type="button" class="radio-player-icon radio-player-mobile-details" data-player-action="details" @click.stop="toggleInfoWindow()" title="Detalles" aria-label="Detalles">
                        <span>i</span>
                    </button>

                    <div class="radio-player-mobile-actions-center">
                        <button type="button" class="radio-player-icon radio-player-mobile-toggle radio-player-expand-btn" data-player-action="expand" @click.stop="dockMinimized = false" aria-label="Expandir" title="Expandir" x-show="dockMinimized" style="display:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>

                        <button type="button" class="radio-player-primary radio-player-mobile-play" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause">
                            <span x-text="playing ? '❚❚' : '▶'">▶</span>
                        </button>

                        <button type="button" class="radio-player-icon radio-player-mobile-toggle radio-player-minimize-btn" data-player-action="minimize" @click.stop="dockMinimized = true" aria-label="Contraer" title="Contraer" x-show="!dockMinimized" style="display:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                        </button>
                    </div>

                    <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" class="radio-player-mobile-favorite">
                        <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                    </button>
                </div>
        </div>

        <section
            class="radio-player-band-window"
            x-cloak
            x-show="bandWindowOpen"
            x-transition.opacity
            style="display:none; position:fixed; inset:0; z-index:120; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.72); backdrop-filter:blur(8px);"
            @click.self="closeBandWindow()"
            @keydown.escape.window="closeBandWindow()"
        >
            <div class="sr-band-modal-container" style="position:relative; width:min(1100px, calc(100vw - 24px)); height:min(75vh, 720px); border:1px solid rgba(184,175,162,.22); border-radius:28px; background:linear-gradient(180deg, rgba(16,16,18,.92), rgba(10,10,11,.96)); backdrop-filter:blur(12px); box-shadow:0 28px 72px rgba(0,0,0,.58); padding:22px; overflow:hidden; overscroll-behavior:contain; margin:auto; display:flex; flex-direction:column;">
                <button type="button" data-player-band-close @click.stop="closeBandWindow()" aria-label="Cerrar" style="position:absolute; right:16px; top:14px; z-index:9999; pointer-events:auto; appearance:none; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.22); color:#dcd7cb; width:44px; height:44px; display:grid; place-items:center; cursor:pointer; font-size:22px;">×</button>

                <div class="sr-band-stage" style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; gap:14px;">
                    <template x-if="bandInfoLoading">
                        <div style="display:flex; flex-direction:column; gap:18px; flex:1; min-height:0; overflow:hidden;">
                            <div style="display:flex; gap:22px; align-items:flex-start;">
                                <div style="width:200px; height:200px; min-width:200px; border-radius:16px; background:rgba(255,255,255,.06);" class="sr-pulse"></div>
                                <div style="flex:1; display:flex; flex-direction:column; gap:10px;">
                                    <div style="height:24px; width:60%; border-radius:6px; background:rgba(255,255,255,.06);" class="sr-pulse"></div>
                                    <div style="height:16px; width:40%; border-radius:6px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                    <div style="height:16px; width:50%; border-radius:6px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                    <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;">
                                        <div style="height:28px; width:80px; border-radius:14px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                        <div style="height:28px; width:80px; border-radius:14px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                        <div style="height:28px; width:80px; border-radius:14px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:18px; flex:1; min-height:0; align-items:stretch;">
                                <div style="height:100%; min-height:0; border-radius:18px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                                <div style="height:100%; min-height:0; border-radius:18px; background:rgba(255,255,255,.04);" class="sr-pulse"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!bandInfoLoading">
                        <div style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
                            <div class="sr-band-header" style="display:flex; gap:18px; align-items:flex-start; flex:0 0 auto;">
                                <div style="flex-shrink:0;">
                                    <img class="sr-band-cover"
                                        data-player-band-cover-image
                                        :src="bandPanel.cover || track.band_thumbnail || track.cover || fallbackCover"
                                        alt=""
                                        onerror="this.onerror=null; this.src='{{ $fallbackCover }}'; this.style.width=''; this.style.height=''; this.style.minWidth='';"
                                        style="width:180px; height:180px; min-width:180px; object-fit:cover; border-radius:16px; border:1px solid rgba(184,175,162,.20); box-shadow:0 12px 32px rgba(0,0,0,.40); background:rgba(255,255,255,.04);"
                                        loading="lazy">
                                </div>

                                <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:8px; padding-top:2px;">
                                    <h3 data-player-band-title style="margin:0; color:#fff; font-family:var(--font-display); text-transform:uppercase; font-size:20px; line-height:1.1;" x-text="bandPanel.title || track.title || ''"></h3>
                                    <p data-player-band-artist style="margin:0; color:#b9b1a5; font-size:14px;" x-text="bandPanel.artist || track.artist || ''"></p>
                                    <p x-show="(bandPanel.foundedLabel || track.band_founded_label)" style="margin:0; color:#b7ad9f; font-size:12px; letter-spacing:.08em; text-transform:uppercase;" x-text="bandPanel.foundedLabel || track.band_founded_label"></p>
                                    <div x-show="bandPanel.country || track.band_country || bandPanel.genre || track.band_genre || bandPanel.membersCount || track.band_members_count || bandPanel.status || track.band_status" style="display:flex; flex-wrap:wrap; gap:6px;">
                                        <span x-show="bandPanel.country || track.band_country" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🌍 ' + (bandPanel.country || track.band_country || '')"></span>
                                        <span x-show="bandPanel.genre || track.band_genre" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🎵 ' + (bandPanel.genre || track.band_genre || '')"></span>
                                        <span x-show="bandPanel.membersCount || track.band_members_count" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'👥 ' + ((bandPanel.membersCount ?? track.band_members_count) || 0) + ' miembros'"></span>
                                        <span x-show="bandPanel.status || track.band_status" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="(() => { const status = bandPanel.status || track.band_status || ''; return status === 'active' ? '✅ Activo' : (status === 'on_hold' ? '⏸ En pausa' : (status === 'disbanded' ? '❌ Disuelto' : status)); })()"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="sr-band-tabs" style="display:flex; gap:18px; align-items:flex-end; margin-top:16px; border-bottom:1px solid rgba(184,175,162,.14); flex:0 0 auto;">
                                <button
                                    type="button"
                                    @click="activeTab = 'bio'"
                                    :aria-pressed="activeTab === 'bio'"
                                    :class="activeTab === 'bio' ? 'border-b-2 border-white text-white' : 'border-b-2 border-transparent text-[#a7a093] hover:text-white'"
                                    style="margin-bottom:-1px; padding:10px 2px 12px; background:transparent; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase; transition:color .15s ease, border-color .15s ease;"
                                >
                                    Biografía
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'lyrics'"
                                    :aria-pressed="activeTab === 'lyrics'"
                                    :class="activeTab === 'lyrics' ? 'border-b-2 border-white text-white' : 'border-b-2 border-transparent text-[#a7a093] hover:text-white'"
                                    style="margin-bottom:-1px; padding:10px 2px 12px; background:transparent; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase; transition:color .15s ease, border-color .15s ease;"
                                >
                                    Letras
                                </button>
                            </div>

                            <div class="sr-band-body" style="flex:1; min-height:0; overflow:hidden; margin-top:14px;">
                                <div
                                    x-show="activeTab === 'bio'"
                                    x-cloak
                                    style="display:flex; flex-direction:column; gap:14px; height:100%; min-height:0; overflow-y:auto; padding-right:6px; overscroll-behavior:contain;"
                                >
                                    <section style="display:flex; flex-direction:column; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                                        <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Biografía / información</h4>
                                        <p data-player-band-info style="color:#d8d3ca; line-height:1.8; margin:0; white-space:pre-line; overflow-wrap:anywhere; font-size:14px;" x-text="resumenBio || 'Buscando información de banda...'"></p>

                                        <div x-show="bandPanel.logo || track.band_logo || bandLinks().length" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                                            <template x-for="link in bandLinks()" :key="link.url">
                                                <a :href="link.url" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.2); color:#dcd7cb; text-decoration:none; font-family:var(--font-display); font-size:10px; letter-spacing:.14em; text-transform:uppercase; border-radius:14px;" x-text="link.label"></a>
                                            </template>
                                        </div>
                                    </section>

                                    <section x-show="Array.isArray(track.band_members) && track.band_members.length > 0" style="display:grid; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                                        <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Integrantes</h4>
                                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                            <template x-for="member in (Array.isArray(track.band_members) ? track.band_members : [])" :key="typeof member === 'string' ? member : (member.name || member.member || member.title || JSON.stringify(member))">
                                                <div style="display:flex; flex-direction:column; gap:2px; padding:8px 12px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); border-radius:12px;">
                                                    <strong style="color:#e6e0d6; font-size:12px;" x-text="typeof member === 'string' ? member : (member.name || member.member || member.title || '')"></strong>
                                                    <span x-show="typeof member === 'object' && member && (member.role || member.instrument || member.position)" style="color:#a7a093; font-size:10px; letter-spacing:.06em; text-transform:uppercase;" x-text="member.role || member.instrument || member.position"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                </div>

                                <div
                                    x-show="activeTab === 'lyrics'"
                                    x-cloak
                                    style="display:flex; flex-direction:column; gap:10px; height:100%; min-height:0; overflow-y:auto; padding-right:6px; overscroll-behavior:contain;"
                                >
                                    <section style="display:flex; flex-direction:column; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                                        <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Letra</h4>
                                        <p
                                            style="color:#e7e1d6; line-height:1.9; margin:0; white-space:pre-wrap; overflow-wrap:anywhere; font-size:14px;"
                                            x-text="track.lyrics && track.lyrics.trim() ? track.lyrics : 'No hay letra disponible para esta canción'"
                                        ></p>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- ========== MODAL DE PROGRAMA ========== -->
        <section
            class="sr-program-window"
            x-cloak
            :class="{ 'is-open': programWindowOpen }"
            x-show="programWindowOpen"
            x-transition.opacity
            style="display:none; position:fixed; inset:0; z-index:100; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.72); backdrop-filter:blur(8px);"
            @keydown.escape.window="closeProgramWindow()"
            @click.self="closeProgramWindow()"
        >
            <div class="sr-program-modal" style="position:relative; width:min(680px, calc(100vw - 32px)); height:min(75vh, 720px); display:flex; flex-direction:column; background:linear-gradient(180deg, #161413 0%, #0c0b0a 100%); border:1px solid rgba(184,175,162,.22); border-radius:12px; box-shadow:0 24px 64px rgba(0,0,0,.5); overflow:hidden;" @click.stop>
                <button type="button" @click="closeProgramWindow()" aria-label="Cerrar" style="position:absolute; top:12px; right:12px; z-index:9999; pointer-events:auto; display:flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.25); background:rgba(0,0,0,.45); color:#b7ad9f; border-radius:50%; cursor:pointer; transition:all .15s ease;">×</button>

                <template x-if="programInfoLoading && !programInfo">
                    <div style="display:flex; flex-direction:column; flex:1; min-height:0; padding:24px; gap:16px;">
                        <div style="display:flex; gap:16px; align-items:flex-start;">
                            <div style="width:180px; height:180px; min-height:180px; background:rgba(184,175,162,.08); border-radius:8px;" class="sr-pulse"></div>
                            <div style="flex:1; display:flex; flex-direction:column; gap:10px;">
                                <div style="width:120px; height:16px; background:rgba(184,175,162,.12); border-radius:4px;" class="sr-pulse"></div>
                                <div style="width:80%; height:22px; background:rgba(184,175,162,.12); border-radius:4px;" class="sr-pulse"></div>
                                <div style="width:60%; height:14px; background:rgba(184,175,162,.08); border-radius:4px;" class="sr-pulse"></div>
                                <div style="width:70%; height:14px; background:rgba(184,175,162,.08); border-radius:4px;" class="sr-pulse"></div>
                            </div>
                        </div>
                        <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                            <div style="width:90%; height:14px; background:rgba(184,175,162,.08); border-radius:4px;" class="sr-pulse"></div>
                            <div style="width:75%; height:14px; background:rgba(184,175,162,.08); border-radius:4px;" class="sr-pulse"></div>
                            <div style="width:85%; height:14px; background:rgba(184,175,162,.08); border-radius:4px;" class="sr-pulse"></div>
                        </div>
                    </div>
                </template>

                <template x-if="programInfo">
                    <div style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
                        <div class="sr-program-header" style="display:flex; gap:16px; padding:20px 20px 0 20px; align-items:flex-start;">
                            <img :src="programInfo.cover || fallbackCover" :alt="programInfo.name || ''" onerror="this.src=fallbackCover; this.onerror=null;" style="width:180px; height:180px; min-height:180px; object-fit:cover; border-radius:8px; border:1px solid rgba(184,175,162,.18); flex-shrink:0;" loading="lazy">
                            <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:6px; min-height:200px; align-items:flex-start;">
                                <span x-show="track.is_live" style="display:inline-flex; align-items:center; gap:5px; padding:3px 10px; background:rgba(195,39,32,.18); border:1px solid rgba(195,39,32,.45); color:#ff6b5a; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; border-radius:3px;">
                                    <span style="width:6px; height:6px; background:#c32720; border-radius:50%;" class="sr-pulse"></span>
                                    EN VIVO
                                </span>
                                <span x-text="programInfo.genre || ''" style="color:#b7ad9f; font-family:var(--font-display); font-size:10px; letter-spacing:.18em; text-transform:uppercase;"></span>
                                <h2 x-text="track.program_name || programInfo.name || ''" style="color:#ddd7cb; font-size:18px; font-weight:700; line-height:1.2; margin:0;"></h2>
                                <p x-text="programInfo.host ? 'Conduce: ' + programInfo.host : ''" style="color:#b7ad9f; font-size:13px; margin:0;"></p>
                                <p x-text="programInfo.schedule || ''" style="color:#8a8378; font-size:12px; margin:0; font-family:var(--font-display); letter-spacing:.06em; text-transform:uppercase;"></p>
                                <div x-show="programInfo.social_links && (programInfo.social_links.facebook || programInfo.social_links.instagram)" style="display:flex; gap:10px; margin-top:6px;">
                                    <a x-show="programInfo.social_links?.facebook" :href="programInfo.social_links.facebook" target="_blank" rel="noopener" style="display:inline-flex; color:#b7ad9f; font-size:12px; text-decoration:none;">Facebook</a>
                                    <a x-show="programInfo.social_links?.instagram" :href="programInfo.social_links.instagram" target="_blank" rel="noopener" style="display:inline-flex; color:#b7ad9f; font-size:12px; text-decoration:none;">Instagram</a>
                                </div>
                            </div>
                        </div>

                        <div style="height:1px; background:rgba(184,175,162,.15); margin:12px 20px;"></div>

                        <div class="sr-program-body" style="flex:1; min-height:0; overflow-y:auto; padding:0 20px 20px 20px; overscroll-behavior:contain;">
                            <template x-if="programInfo.episode && (programInfo.episode.guest_bio || programInfo.episode.guest_image)">
                                <div>
                                    <div x-show="programInfo.episode.guest_image" style="margin-bottom:12px;">
                                        <img :src="programInfo.episode.guest_image" :alt="programInfo.episode.title || ''" onerror="this.style.display='none'" style="width:100%; max-height:200px; object-fit:cover; border-radius:6px;" loading="lazy">
                                    </div>
                                    <h3 x-text="programInfo.episode.title || ''" style="color:#ddd7cb; font-size:15px; font-weight:600; margin:0 0 8px 0;"></h3>
                                    <p x-text="programInfo.episode.guest_bio || ''" style="color:#a7a093; font-size:13px; line-height:1.6; margin:0; white-space:pre-line;"></p>
                                    <small x-show="programInfo.episode.episode_number" x-text="'Episodio ' + programInfo.episode.episode_number" style="color:#8a8378; font-size:11px; margin-top:8px; display:block; font-family:var(--font-display); letter-spacing:.06em; text-transform:uppercase;"></small>
                                </div>
                            </template>

                            <template x-if="!(programInfo.episode && (programInfo.episode.guest_bio || programInfo.episode.guest_image))">
                                <div>
                                    <h3 style="color:#ddd7cb; font-size:15px; font-weight:600; margin:0 0 8px 0;">Acerca del programa</h3>
                                    <p x-text="track.program_description || programInfo.description || 'Información del programa no disponible.'" style="color:#a7a093; font-size:13px; line-height:1.6; margin:0; white-space:pre-line;"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        @if ($mode !== 'page' && $mode !== 'popup')
        <section
            class="radio-player-panel"
            :class="{ 'is-open': panelOpen }"
            style="display:none; position:fixed; left:50%; bottom:82px; z-index:89; width:min(1180px, calc(100vw - 24px)); max-height:none; transform:translateX(-50%) translateY(16px); opacity:0; visibility:hidden; pointer-events:none;"
            x-show="mode !== 'page' && panelOpen"
            x-transition
        >
            <header class="radio-player-head" style="padding:16px 18px;">
                <div>
                    <div class="radio-player-kicker" x-text="track.is_live ? 'EN VIVO AHORA' : 'ON DEMAND'"></div>
                    <h2 class="radio-player-title" x-text="track.title || defaultTitle"></h2>
                    <p class="radio-player-subtitle" x-text="track.artist || defaultArtist"></p>
                    <small x-text="listeners > 0 ? `${listeners} oyentes` : 'Sin oyentes'"></small>
                </div>
                <div class="radio-player-head-actions">
                    <button type="button" class="radio-player-chip" data-player-action="favorite" @click="toggleFavorite()">Favorito +</button>
                    <button type="button" class="radio-player-chip" @click="shareCurrent()">Compartir</button>
                    <button type="button" class="radio-player-chip" @click="openPopout()">Pop-out</button>
                    <button type="button" class="radio-player-chip" @click="closePanel()">Min</button>
                </div>
            </header>

            <div class="radio-player-body" style="grid-template-columns:minmax(0,1fr) minmax(280px,.72fr); gap:14px; padding:16px 18px 18px;">
                <section class="radio-player-now" style="grid-template-columns:128px minmax(0,1fr); gap:14px; align-items:start;">
                    <img class="radio-player-cover-large" :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" style="width:128px; height:128px; min-height:128px;" loading="lazy">
                    <div class="radio-player-now-copy" style="justify-content:flex-start; gap:6px;">
                        <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'LIVE' : 'PLAYBACK'"></span>
                        <h3 x-text="track.title || defaultTitle"></h3>
                        <p x-text="track.artist || defaultArtist"></p>
                        <small x-show="track.program_name" x-text="track.program_name"></small>
                        <small x-show="!track.program_name && nextProgram" x-text="'Próximo: ' + (nextProgram.name || '') + (nextProgram.schedule_time ? ' a las ' + nextProgram.schedule_time : '')"></small>
                        <small x-text="listeners > 0 ? `${listeners} oyentes` : ''"></small>

                        <div class="radio-player-progress-wrap" style="margin-top:8px;">
                            <div class="radio-player-time">
                                <span x-text="formatTime(progress.elapsed)"></span>
                                <span x-text="formatTime(progress.duration)"></span>
                            </div>
                            <button type="button" class="radio-player-progress" @click="seek($event)">
                                <span class="radio-player-progress-fill" :style="`width:${progress.ratio}%`"></span>
                            </button>
                        </div>

                        <div class="radio-player-controls" style="gap:10px;">
                            <button type="button" class="radio-player-primary" @click="togglePlay()" x-text="playing ? 'Pause' : 'Play'"></button>
                            <button type="button" class="radio-player-secondary" @click="toggleMute()" x-text="muted ? 'Unmute' : 'Mute'"></button>
                            <label class="radio-player-volume">
                                <span>Vol</span>
                                <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()">
                            </label>
                        </div>
                    </div>
                </section>

                <aside class="radio-player-side" style="display:flex; flex-direction:column; max-height:320px;">
                    <div class="radio-player-tabs" style="grid-template-columns:repeat(4, minmax(0, 1fr));">
                        <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')">Letra</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')">Banda</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'program' }" @click="setTab('program')">Programa</button>
                        <button type="button" :class="{ 'is-active': activeTab === 'history' }" @click="setTab('history')">Historial</button>
                    </div>

                    <div class="radio-player-tab-body" style="padding:14px; overflow:auto; max-height:280px;">
                        <div x-show="activeTab === 'lyrics'">
                            <h4>Letra del tema</h4>
                            <p x-text="track.lyrics || 'Letra no disponible'"></p>
                        </div>
                    <div x-show="activeTab === 'band'">
                        <h4>Info de banda</h4>
                        <p x-text="track.band_founded_label || ''"></p>
                        <p x-text="track.band_info || 'Buscando información de banda...'"></p>
                    </div>
                        <div x-show="activeTab === 'program'">
                            <h4>Programa</h4>
                            <p x-text="programText()"></p>
                            <template x-if="nextProgram">
                                <div class="radio-player-next">
                                    <span>Próximo programa</span>
                                    <strong x-text="nextProgram.name"></strong>
                                    <small x-text="nextProgram.schedule || ''"></small>
                                </div>
                            </template>
                            <template x-if="queue.length">
                                <div class="radio-player-queue" style="margin-top:14px;">
                                    <h4>A continuación</h4>
                                    <div class="radio-player-queue-grid" style="grid-template-columns:1fr;">
                                        <template x-for="item in queue" :key="item.title">
                                            <article class="radio-player-queue-item">
                                                <img :src="item.cover || fallbackCover" alt="" loading="lazy">
                                                <div>
                                                    <strong x-text="item.title"></strong>
                                                    <p x-text="item.artist || defaultArtist"></p>
                                                </div>
                                            </article>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="activeTab === 'history'">
                            <h4>Historial</h4>
                            <template x-if="history.length">
                                <div class="space-y-3">
                                    <template x-for="item in history" :key="`${item.title}-${item.played_at}`">
                                        <article class="radio-player-history">
                                            <img :src="item.cover || fallbackCover" alt="" loading="lazy">
                                            <div>
                                                <strong x-text="item.title"></strong>
                                                <p x-text="item.artist || defaultArtist"></p>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!history.length">Sin historial todavía.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
        @endif
    @endif

    <div class="radio-player-toast" x-show="toast.visible" x-transition>
        <span x-text="toast.message"></span>
    </div>



    <style>
    /* Expanded dock responsive */
    @media (max-width: 900px) {
      .radio-player-dock > div:last-child {
        grid-template-columns: minmax(70px,.08fr) minmax(140px,.28fr) minmax(0,1fr) !important;
      }
      .radio-player-dock .radio-player-actions > div {
        display:none !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="details"] span:last-child,
      .radio-player-dock .radio-player-actions > button[data-player-action="play"] span:last-child,
      .radio-player-dock .radio-player-actions > button[data-player-action="favorite"] span:last-child {
        display:none !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="play"] {
        min-width:40px !important;
        width:40px !important;
        padding:0 !important;
        justify-content:center !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="details"] {
        min-width:40px !important;
        width:40px !important;
        padding:0 !important;
        justify-content:center !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="favorite"] {
        min-width:40px !important;
        width:40px !important;
        padding:0 !important;
        justify-content:center !important;
      }
    }
    @media (max-width: 480px) {
      .radio-player-dock > div:last-child {
        grid-template-columns: minmax(50px,.08fr) minmax(0,1fr) auto !important;
        gap:6px !important;
        padding:6px 8px !important;
        width:calc(100vw - 12px) !important;
        min-height:54px !important;
      }
      .radio-player-dock .rbcloud_nowplaying button img {
        width:48px !important;
        height:48px !important;
      }
      .radio-player-dock .radio-player-meta strong {
        font-size:12px !important;
      }
      .radio-player-dock .radio-player-meta span {
        font-size:10px !important;
      }
      .radio-player-dock .rbcloud_tracktimer { display: flex !important; }
      .radio-player-dock .radio-player-actions > div {
        display:none !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="details"] span:last-child,
      .radio-player-dock .radio-player-actions > button[data-player-action="play"] span:last-child,
      .radio-player-dock .radio-player-actions > button[data-player-action="favorite"] span:last-child {
        display:none !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="play"] {
        min-width:38px !important;
        width:38px !important;
        padding:0 !important;
        justify-content:center !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="details"] {
        min-width:38px !important;
        width:38px !important;
        padding:0 !important;
        justify-content:center !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="mute"] {
        width:38px !important;
        height:38px !important;
      }
      .radio-player-dock .radio-player-actions > button[data-player-action="favorite"] {
        min-width:38px !important;
        width:38px !important;
        padding:0 !important;
        justify-content:center !important;
      }
    }
    </style>
    <style>
/* Reproductor responsive */
@media (max-width: 900px) {
  .radio-player-dock {
    grid-template-columns: minmax(70px,.08fr) minmax(160px,.3fr) minmax(0,1fr) !important;
  }
  .radio-player-dock .radio-player-actions > div { display:none !important; }
  .radio-player-dock .radio-player-actions > button[data-player-action=details] span:last-child,
  .radio-player-dock .radio-player-actions > button[data-player-action=play] span:last-child,
  .radio-player-dock .radio-player-actions > button[data-player-action=favorite] span:last-child {
    display:none !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=play] {
    min-width:40px !important; width:40px !important; padding:0 !important; justify-content:center !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=details] {
    min-width:40px !important; width:40px !important; padding:0 !important; justify-content:center !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=favorite] {
    min-width:40px !important; width:40px !important; padding:0 !important; justify-content:center !important;
  }
}
@media (max-width: 480px) {
  .radio-player-dock {
    gap:6px !important;
    padding:6px 8px !important;
    width:calc(100vw - 12px) !important;
    min-height:54px !important;
  }

  .radio-player-dock:not(.is-minimized) {
    display:flex !important;
    flex-direction:column !important;
    gap:8px !important;
    align-items:stretch !important;
  }

  .radio-player-dock .rbcloud_nowplaying button img { width:56px !important; height:56px !important; }
  .radio-player-dock .radio-player-meta strong { font-size:12px !important; }
  .radio-player-dock .radio-player-meta span { font-size:10px !important; }
  .radio-player-dock .rbcloud_tracktimer { display: flex !important; }

  .radio-player-dock:not(.is-minimized) .radio-player-actions {
    display:flex !important;
    width:100% !important;
    justify-content:flex-start !important;
    gap:8px !important;
    padding:0 !important;
    margin:0 !important;
    transform:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions-center {
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:6px !important;
    flex:0 0 auto !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions-spacer {
    display:block !important;
    flex:1 1 12px !important;
    min-width:12px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="share"],
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="popout"] {
    display:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button {
    flex:0 0 auto !important;
    position:relative !important;
    transform:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="details"] {
    order:1 !important;
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    padding:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="expand"] {
    order:2 !important;
    width:30px !important;
    height:30px !important;
    min-width:30px !important;
    padding:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="play"] {
    order:3 !important;
    width:52px !important;
    height:52px !important;
    min-width:52px !important;
    padding:0 !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    border-width:2px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="play"] span {
    color:#ffffff !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:100% !important;
    height:100% !important;
    font-size:18px !important;
    line-height:1 !important;
    position:relative !important;
    z-index:2 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="minimize"] {
    order:4 !important;
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    padding:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="expand"] svg,
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="minimize"] svg {
    width:16px !important;
    height:16px !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume {
    display:flex !important;
    width:100% !important;
    min-width:0 !important;
    max-width:100% !important;
    gap:8px !important;
    overflow:hidden !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume input[type="range"] {
    flex:1 1 auto !important;
    min-width:0 !important;
  }

  .radio-player-dock .radio-player-actions {
    display:none !important;
  }

  .radio-player-mobile-actions {
    display:flex !important;
    align-items:center !important;
    justify-content:space-between !important;
    gap:10px !important;
    width:100% !important;
    min-width:0 !important;
    margin:0 !important;
    padding:0 !important;
  }

  .radio-player-mobile-details,
  .radio-player-mobile-play,
  .radio-player-mobile-toggle {
    flex:0 0 auto !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    border-radius:50% !important;
    padding:0 !important;
    margin:0 !important;
    position:relative !important;
    overflow:visible !important;
  }

  .radio-player-mobile-details {
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    border:1px solid rgba(184,175,162,.30) !important;
    background:rgba(0,0,0,.18) !important;
    color:#dcd7cc !important;
    font-size:13px !important;
    font-weight:600 !important;
    line-height:1 !important;
  }

  .radio-player-mobile-actions-center {
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:8px !important;
    min-width:0 !important;
    flex:1 1 auto !important;
  }

  .radio-player-mobile-play {
    width:52px !important;
    height:52px !important;
    min-width:52px !important;
    border:2px solid rgba(184,175,162,.50) !important;
    background:rgba(184,175,162,.12) !important;
    color:#fff !important;
    z-index:2 !important;
  }

  .radio-player-mobile-play span {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:100% !important;
    height:100% !important;
    color:#fff !important;
    font-size:18px !important;
    line-height:1 !important;
    position:relative !important;
    z-index:3 !important;
  }

  .radio-player-mobile-toggle {
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    border:1px solid rgba(184,175,162,.30) !important;
    background:rgba(0,0,0,.18) !important;
    color:#dcd7cc !important;
  }

  .radio-player-mobile-toggle svg {
    width:16px !important;
    height:16px !important;
    display:block !important;
  }

  .radio-player-mobile-actions-spacer {
    flex:0 0 34px !important;
    min-width:34px !important;
    height:1px !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=details] span:last-child,
  .radio-player-dock .radio-player-actions > button[data-player-action=play] span:last-child,
  .radio-player-dock .radio-player-actions > button[data-player-action=favorite] span:last-child {
    display:none !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=play] {
    min-width:38px !important; width:38px !important; padding:0 !important; justify-content:center !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=details] {
    min-width:38px !important; width:38px !important; padding:0 !important; justify-content:center !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=mute] {
    width:38px !important; height:38px !important;
  }
  .radio-player-dock .radio-player-actions > button[data-player-action=favorite] {
    min-width:38px !important; width:38px !important; padding:0 !important; justify-content:center !important;
  }
}
@keyframes srpulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.sr-pulse {
  animation: srpulse 1.5s infinite;
}

@media (min-width: 640px) {
  .sr-band-tabs { display: flex !important; }
  .sr-band-columns { grid-template-columns: 1fr 1fr !important; }
  .sr-band-lyrics-col,
  .sr-band-info-col { display: flex !important; }
  .sr-band-header { flex-direction: row !important; align-items: flex-start !important; }
  .sr-band-cover { width: 200px !important; height: 200px !important; min-width: 200px !important; }
}

        @media (max-width: 639px) {
  .sr-band-modal-container {
    height: min(85vh, 600px) !important;
  }
  .sr-band-tabs { display: flex !important; }
  .sr-band-columns { grid-template-columns: 1fr !important; }
  .sr-band-header { flex-direction: column !important; align-items: center !important; }
  .sr-band-cover { width: 150px !important; height: 150px !important; min-width: 150px !important; }
  .sr-band-text-scroll { max-height: 280px !important; }
}

@media (min-width: 640px) {
  .sr-band-modal-container {
    height: min(75vh, 720px) !important;
  }
}

/* Mobile: minimized/expanded states */
@media (max-width: 640px) {
  /* Hide expand/minimize buttons outside mobile by default */
  .radio-player-expand-btn { display: none !important; }
  .radio-player-minimize-btn { display: none !important; }
  
  /* Show on mobile */
  .radio-player-expand-btn { display: inline-flex !important; }
  .radio-player-minimize-btn { display: inline-flex !important; }

  /* MINIMIZED: hide extra controls, play icon-only */
  
  .radio-player-dock.is-minimized .radio-player-actions > div,
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="details"],
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="mute"],
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="favorite"],
  .radio-player-dock.is-minimized .radio-player-minimize-btn,
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="play"] span:last-child
    { display: none !important; }

  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="play"] {
    min-width:44px !important;
    width:44px !important;
    padding:0 !important;
    justify-content:center !important;
  }

  .radio-player-dock.is-minimized .rbcloud_nowplaying button img {
    margin-top: -2px !important;
  }

  /* Grid: minimized = cover | title | play+expand */
  .radio-player-dock.is-minimized {
    grid-template-columns: minmax(60px,.1fr) minmax(0,1fr) auto !important;
    gap:8px !important;
    padding:8px 12px !important;
    width:calc(100vw - 16px) !important;
    min-height:72px !important;
  }

  /* EXPANDED: vertical blocks on mobile */
  .radio-player-dock:not(.is-minimized) {
    display:flex !important;
    flex-direction:column !important;
    gap:10px !important;
    width:calc(100vw - 16px) !important;
    min-height:auto !important;
    padding:10px 12px 12px !important;
    align-items:stretch !important;
  }

  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying,
  .radio-player-dock:not(.is-minimized) .radio-player-meta-column,
  .radio-player-dock:not(.is-minimized) .radio-player-actions,
  .radio-player-dock:not(.is-minimized) .sr-dock-volume {
    width:100% !important;
    max-width:100% !important;
    min-width:0 !important;
    margin:0 !important;
  }

  /* FILA 1 */
  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying {
    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;
    gap:12px !important;
  }

  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying button img {
    width:56px !important;
    height:56px !important;
    border-radius:10px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta-column {
    display:grid !important;
    grid-template-columns:minmax(0,1fr) auto !important;
    gap:4px 8px !important;
    min-width:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta {
    grid-column:1 / -1 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta strong {
    font-size:14px !important;
    line-height:1.05 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta span,
  .radio-player-dock:not(.is-minimized) .radio-player-meta small {
    font-size:11px !important;
    line-height:1.05 !important;
  }

  /* FILA 2 */
  .radio-player-dock:not(.is-minimized) .sr-dock-volume {
    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;
    gap:10px !important;
    min-width:0 !important;
    max-width:100% !important;
    overflow:hidden !important;
    padding:4px 0 0 !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume button[data-player-action="mute"] {
    flex:0 0 auto !important;
    width:32px !important;
    height:32px !important;
    padding:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume input[type="range"] {
    flex:1 1 auto !important;
    width:100% !important;
    min-width:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume span[data-player-volume-output] {
    flex:0 0 auto !important;
    min-width:32px !important;
    text-align:right !important;
  }

  .radio-player-share-popout-desktop {
    display:none !important;
  }

  .radio-player-dock:not(.is-minimized) > .radio-player-mobile-share-popout {
    display:none !important;
  }

  /* FILA 3 */
  .radio-player-mobile-actions {
    display:flex !important;
    align-items:center !important;
    justify-content:space-between !important;
    gap:10px !important;
    width:100% !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions {
    display:none !important;
  }

  .radio-player-mobile-actions-center {
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:16px !important;
    flex:1 1 auto !important;
    min-width:0 !important;
  }

  .radio-player-mobile-share-popout-inline {
    display:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-mobile-share-popout-inline {
    display:flex !important;
    align-items:center !important;
    justify-content:flex-end !important;
    gap:6px !important;
    flex:0 0 auto !important;
    min-width:0 !important;
    margin-left:auto !important;
  }

  .radio-player-dock.is-minimized .radio-player-mobile-share-popout-inline {
    display:none !important;
  }

  .radio-player-mobile-share-popout-inline .radio-player-chip {
    min-height:24px !important;
    padding:0 8px !important;
    font-size:9px !important;
    letter-spacing:.12em !important;
  }

  .radio-player-mobile-actions-center > button {
    flex:0 0 auto !important;
    position:relative !important;
    left:auto !important;
    right:auto !important;
    top:auto !important;
    bottom:auto !important;
    transform:none !important;
    z-index:1 !important;
    overflow:visible !important;
  }

  .radio-player-mobile-play {
    width:52px !important;
    height:52px !important;
    min-width:52px !important;
    padding:0 !important;
    margin:0 !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    border-width:2px !important;
    background:rgba(184,175,162,.12) !important;
  }

  .radio-player-mobile-play span {
    color:#ffffff !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:100% !important;
    height:100% !important;
    font-size:18px !important;
    line-height:1 !important;
    position:relative !important;
    z-index:2 !important;
  }

  .radio-player-mobile-toggle {
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    padding:0 !important;
    margin:0 !important;
  }

  .radio-player-mobile-favorite {
    width:34px !important;
    height:34px !important;
    min-width:34px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    border:1px solid rgba(184,175,162,.30) !important;
    background:rgba(0,0,0,.18) !important;
    color:#dcd7cc !important;
    border-radius:50% !important;
    padding:0 !important;
    margin:0 !important;
    flex:0 0 auto !important;
  }

  .radio-player-mobile-favorite span {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:100% !important;
    height:100% !important;
    line-height:1 !important;
    font-size:14px !important;
  }

  .radio-player-dock.is-minimized .radio-player-mobile-favorite {
    display:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-mobile-favorite {
    display:inline-flex !important;
  }

  /* Timer visible on mobile */
  .radio-player-dock .rbcloud_tracktimer { display: flex !important; }
}

.sr-program-window.is-open {
  opacity: 1 !important;
  visibility: visible !important;
  pointer-events: auto !important;
}

@keyframes srpulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.sr-pulse {
  animation: srpulse 1.5s infinite;
}

@media (min-width: 640px) {
  .sr-band-modal-container {
    height: min(75vh, 720px) !important;
  }

  .sr-program-modal {
    height: min(75vh, 720px) !important;
  }
}

@media (max-width: 639px) {
  .sr-band-modal-container {
    height: min(85vh, 600px) !important;
  }

  .sr-program-modal {
    width: calc(100vw - 16px) !important;
    height: min(85vh, 600px) !important;
  }

  .sr-program-header {
    flex-direction: column !important;
    align-items: center !important;
  }

  .sr-program-header img {
    width: 100% !important;
    height: 160px !important;
    min-height: 160px !important;
  }

  .sr-program-body {
    padding: 0 16px 16px !important;
  }

  /* ====== MINIMIZED mobile dock ====== */
  .radio-player-dock.is-minimized {
    grid-template-columns: 56px minmax(0,1fr) auto auto !important;
    grid-template-rows: auto !important;
    gap:6px !important;
    padding:8px 10px !important;
    width:calc(100vw - 16px) !important;
    min-height:68px !important;
  }

  .radio-player-dock.is-minimized .rbcloud_nowplaying button img {
    width:56px !important;
    height:56px !important;
  }

  .radio-player-dock.is-minimized .radio-player-meta strong {
    font-size:12px !important;
  }

  .radio-player-dock.is-minimized .radio-player-meta span,
  .radio-player-dock.is-minimized .radio-player-meta small {
    font-size:10px !important;
  }

  .radio-player-dock.is-minimized .radio-player-actions {
    gap:4px !important;
  }

  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="play"] {
    width:36px !important;
    height:36px !important;
    min-width:36px !important;
    padding:0 !important;
    font-size:14px !important;
  }

  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="details"],
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="favorite"],
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="expand"] {
    width:32px !important;
    height:32px !important;
    min-width:32px !important;
    padding:0 !important;
  }

  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="share"],
  .radio-player-dock.is-minimized .radio-player-actions > button[data-player-action="popout"] {
    display:none !important;
  }

  .radio-player-dock.is-minimized .sr-dock-volume {
    min-width:36px !important;
    max-width:36px !important;
    gap:0 !important;
  }

  .radio-player-dock.is-minimized .sr-dock-volume input[type="range"],
  .radio-player-dock.is-minimized .sr-dock-volume span[data-player-volume-output] {
    display:none !important;
  }

  /* ====== EXPANDED mobile dock ====== */
  .radio-player-dock:not(.is-minimized) {
    grid-template-columns: 1fr !important;
    grid-template-rows: auto auto auto !important;
    gap:8px !important;
    padding:10px 12px 8px !important;
    width:calc(100vw - 16px) !important;
    min-height:auto !important;
  }

  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying {
    grid-column: 1;
  }

  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying button img {
    width:56px !important;
    height:56px !important;
  }
  
  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying + div {
    grid-column: 2;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta strong {
    font-size:14px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-meta span {
    font-size:12px !important;
  }

  .radio-player-dock .radio-player-actions {
    display:none !important;
  }

  /* Row 3: volume slider full width */
  .radio-player-dock:not(.is-minimized) .sr-dock-volume {
    display:flex !important;
    grid-row: 3;
    min-width:100% !important;
    max-width:100% !important;
    gap:8px !important;
    padding:2px 0 0 !important;
    align-items:center !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume input[type="range"] {
    display:block !important;
    flex:1 !important;
    min-height:28px !important;
  }

  .radio-player-dock:not(.is-minimized) .sr-dock-volume span[data-player-volume-output] {
    display:inline !important;
    min-width:28px !important;
  }

  /* General mobile adjustments */
  .radio-player-dock .radio-player-chip {
    font-size:9px !important;
    padding:0 6px !important;
    min-height:22px !important;
  }

  .radio-player-dock .rbcloud_tracktimer { display: flex !important; }
}
</style>
</div>
