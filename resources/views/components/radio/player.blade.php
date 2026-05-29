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
        <div class="radio-player-popup-shell" style="display:flex; flex-direction:column; min-height:100vh; background:rgba(0,0,0,.18); overflow:hidden;">
            <div class="radio-player-popup-stage" aria-hidden="true" style="position:relative; flex:1 1 auto; min-height:calc(100vh - 92px); background:linear-gradient(180deg, rgba(0,0,0,.84), rgba(0,0,0,.76));">
                <span class="radio-player-popup-stage-label">Compartir Pop-out</span>
            </div>

            <section class="radio-player-popup-bar" style="position:relative; display:grid; grid-template-columns:minmax(280px,1.1fr) minmax(220px,.8fr) 1fr; gap:14px; align-items:center; min-height:92px; padding:10px 14px 10px 8px; border-top:1px solid rgba(195,39,32,.48); background:linear-gradient(180deg, rgba(16,16,18,.98), rgba(8,8,10,.98));">
                <div class="radio-player-popup-track" style="display:grid; grid-template-columns:56px minmax(0,1fr); gap:10px; align-items:center;">
                    <img class="radio-player-popup-cover" :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" style="width:56px; height:56px; object-fit:cover; display:block;" loading="lazy">
                    <div class="radio-player-popup-meta" style="display:flex; flex-direction:column; gap:2px; min-width:0;">
                        <span class="radio-player-live-pill is-live" x-show="track.is_live" style="display:inline-flex; align-items:center; justify-content:center; width:max-content; min-height:22px; padding:0 8px; border-radius:9999px; background:#b7ad9f; color:#151515; font-size:10px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;">LIVE</span>
                        <strong x-text="track.title || defaultTitle"></strong>
                        <p x-text="track.artist || defaultArtist"></p>
                        <small x-text="track.is_live ? '' : (track.program_name || '')"></small>
                        <small x-text="listeners > 0 ? `${listeners} oyentes` : ''"></small>
                    </div>
                </div>

                <div class="radio-player-popup-center" style="display:flex; flex-direction:column; justify-content:center; gap:10px;">
                    <div class="radio-player-popup-time" style="display:flex; align-items:baseline; justify-content:center; gap:10px; color:#dcdcdc; font-family:var(--font-display); font-size:18px; font-weight:700;">
                        <span x-text="formatTime(progress.elapsed)"></span>
                        <strong>/</strong>
                        <span x-text="formatTime(progress.duration)"></span>
                    </div>
                    <div class="radio-player-popup-volume" style="display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:12px; width:min(240px,100%); margin-inline:auto; border:1px solid rgba(184,175,162,.3); background:rgba(0,0,0,.18); padding:7px 10px;">
                        <span class="radio-player-popup-volume-label" style="color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.18em; text-transform:uppercase;">Vol</span>
                        <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" style="width:100%; accent-color:#b7ad9f; min-height:24px; cursor:pointer;">
                        <span class="radio-player-popup-volume-value" x-text="Math.round(volume * 100) + '%'" style="color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.18em; text-transform:uppercase;"></span>
                    </div>
                </div>

                <div class="radio-player-popup-actions" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); display:flex; align-items:center; justify-content:flex-end; gap:8px; z-index:5;">
                    <button type="button" class="radio-player-popup-action" @click="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" style="display:inline-flex; flex-direction:column; align-items:center; justify-content:center; min-width:62px; min-height:44px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.2); padding:6px 8px 5px; color:#dcdcdc; font-family:var(--font-display); font-size:9px; letter-spacing:.14em; text-transform:uppercase;">
                        <span class="radio-player-popup-action-icon" x-text="playing ? '❚❚' : '▶'"></span>
                        <span x-text="playing ? 'Pause' : 'Play'"></span>
                    </button>
                    <button type="button" class="radio-player-popup-action" @click="togglePanel()" style="display:inline-flex; flex-direction:column; align-items:center; justify-content:center; min-width:62px; min-height:44px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.2); padding:6px 8px 5px; color:#dcdcdc; font-family:var(--font-display); font-size:9px; letter-spacing:.14em; text-transform:uppercase;">
                        <span class="radio-player-popup-action-icon">i</span>
                        <span x-text="bandWindowOpen ? 'Cerrar' : 'Detalles'"></span>
                    </button>
                    <button type="button" class="radio-player-popup-action" @click="closePanel()" style="display:inline-flex; flex-direction:column; align-items:center; justify-content:center; min-width:62px; min-height:44px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.2); padding:6px 8px 5px; color:#dcdcdc; font-family:var(--font-display); font-size:9px; letter-spacing:.14em; text-transform:uppercase;">
                        <span class="radio-player-popup-action-icon">⌄</span>
                        <span x-text="dockMinimized ? 'Maximizar' : 'Minimizar'">Minimizar</span>
                    </button>
                </div>
            </section>

            <section class="radio-player-popup-drawer" x-show="panelOpen" x-transition style="border-top:1px solid #2b2b2b; background:rgba(16,16,18,.98);">
                <div class="radio-player-popup-drawer-grid" style="display:grid; grid-template-columns:repeat(5, minmax(0,1fr)); border-bottom:1px solid #2b2b2b;">
                    <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')">Letra</button>
                    <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')">Banda</button>
                    <button type="button" :class="{ 'is-active': activeTab === 'program' }" @click="setTab('program')">Programa</button>
                    <button type="button" :class="{ 'is-active': activeTab === 'notices' }" @click="setTab('notices')">Noticias</button>
                    <button type="button" :class="{ 'is-active': activeTab === 'history' }" @click="setTab('history')">Historial</button>
                </div>

                <div class="radio-player-popup-drawer-body" style="max-height:240px; overflow:auto; padding:18px 18px 10px;">
                    <div x-show="activeTab === 'lyrics'">
                        <h4>Letra del tema</h4>
                        <p style="white-space:pre-line; overflow-wrap:anywhere;" x-text="track.lyrics || 'Letra no disponible'"></p>
                    </div>
                    <div x-show="activeTab === 'band'">
                        <h4>Info de banda</h4>
                        <p style="white-space:pre-line; overflow-wrap:anywhere;" x-text="track.band_info || 'Buscando información de banda...'"></p>
                    </div>
                    <div x-show="activeTab === 'program'">
                        <h4>Programa</h4>
                        <p x-text="programText()"></p>
                        <template x-if="nextProgram">
                            <div class="radio-player-popup-next">
                                <span>Próximo programa</span>
                                <strong x-text="nextProgram.name"></strong>
                                <small x-text="nextProgram.schedule || ''"></small>
                            </div>
                        </template>
                    </div>
                    <div x-show="activeTab === 'notices'">
                        <h4>Noticias</h4>
                        <template x-if="notices.length">
                            <div class="space-y-3">
                                <template x-for="notice in notices" :key="notice.title">
                                    <article class="radio-player-popup-notice" :data-type="notice.type">
                                        <strong x-text="notice.title"></strong>
                                        <p x-text="notice.content"></p>
                                    </article>
                                </template>
                            </div>
                        </template>
                        <p x-show="!notices.length">Sin avisos activos.</p>
                    </div>
                    <div x-show="activeTab === 'history'">
                        <h4>Historial</h4>
                        <template x-if="history.length">
                            <div class="space-y-3">
                                <template x-for="item in history" :key="`${item.title}-${item.played_at}`">
                                    <article class="radio-player-popup-history">
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
            </section>
        </div>
    @else        <div
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

                <div style="display:flex; flex-direction:column; gap:2px; min-width:0; justify-content:center; padding-left:0; margin-left:8px; transform:translateY(0);">
                    <div class="radio-player-meta" style="min-width:0; gap:4px; align-items:flex-start;">
                        <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'PLAYBACK'" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:max-content; min-height:16px; padding:0 5px; border-radius:9999px; background:#b7ad9f; color:#151515; font-size:8px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;' : 'display:inline-flex; align-items:center; justify-content:center; width:max-content; min-height:22px; padding:0 8px; border-radius:9999px; background:#b7ad9f; color:#151515; font-size:10px; font-weight:700; letter-spacing:.16em; text-transform:uppercase;'"></span>
                        <strong data-player-title-text x-bind:style="dockMinimized ? 'font-size:12px; color:#ddd7cb; line-height:1.1; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' : 'font-size:14px; color:#ddd7cb; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;'" x-text="track.title || defaultTitle"></strong>
                        <span x-show="!dockMinimized" data-player-artist-text style="font-size:12px; color:#b9b1a5; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" x-text="track.artist || defaultArtist"></span>
                        <small x-show="!dockMinimized && track.program_name" data-player-program-text style="font-size:11px; color:#b7ad9f; line-height:1.08; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" x-text="track.program_name || ''"></small>
                    </div>
                    <div x-show="!dockMinimized" class="rbcloud_tracktimer" style="display:flex; align-items:center; justify-content:flex-start; gap:8px; min-height:18px; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.18em; text-transform:uppercase; white-space:nowrap; margin-top:2px;">
                        <span id="rbcloud_tracktimer_e11096"></span>
                        <span id="rbcloud_tracktimer_sep11096" hidden> &frasl; </span>
                        <span id="rbcloud_tracktimer_r11096"></span>
                    </div>
                    <div x-show="!dockMinimized" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:6px;">
                        <button type="button" class="radio-player-chip" @click="shareCurrent()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;">Share</button>
                        <button type="button" class="radio-player-chip" @click="openPopout()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;">Pop-out</button>
                    </div>
                </div>
                <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11096"></script>

                <span class="radio-player-actions" style="display:flex; flex:0 0 auto; align-items:center; justify-content:center; gap:8px; white-space:nowrap; margin-left:auto; padding-right:4px;">
                    <button type="button" class="radio-player-icon" data-player-action="details" @click.stop="toggleInfoWindow()" title="Detalles" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.25); background:rgba(0,0,0,.15); color:#dcd7cc; border-radius:50%; cursor:pointer; font-size:10px; font-weight:600; line-height:1; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; border-radius:50%; cursor:pointer; font-size:13px; font-weight:600; line-height:1; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;'">i</button>
                    <button type="button" class="radio-player-primary" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause" x-bind:style="dockMinimized ? 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:2px solid rgba(184,175,162,.4); background:rgba(184,175,162,.08); color:#eee; border-radius:50%; cursor:pointer; font-size:14px; line-height:1; transition:all .15s ease; flex-shrink:0; padding:0; margin:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border:2px solid rgba(184,175,162,.5); background:rgba(184,175,162,.12); color:#eee; border-radius:50%; cursor:pointer; font-size:16px; line-height:1; transition:all .15s ease; flex-shrink:0; padding:0; margin:0;'">
                        <span x-text="playing ? '❚❚' : '▶'">▶</span>
                    </button>
                    <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" x-bind:style="dockMinimized ? (isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; border-radius:50%; cursor:pointer; font-size:10px; flex-shrink:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.1); color:#dcd7cb; border-radius:50%; cursor:pointer; font-size:10px; flex-shrink:0;') : (isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; border-radius:50%; cursor:pointer; flex-shrink:0;' : 'display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; border-radius:50%; cursor:pointer; flex-shrink:0;')">
                        <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                    </button>
                    <button type="button" data-player-action="expand" @click.stop="dockMinimized = false" aria-label="Expandir" title="Expandir" x-show="dockMinimized" style="display:none; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid rgba(184,175,162,.25); background:rgba(0,0,0,.15); color:#dcd7cc; cursor:pointer; border-radius:50%; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;" class="radio-player-expand-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block; margin:auto;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                    <button type="button" data-player-action="minimize" @click.stop="dockMinimized = true" aria-label="Minimizar" title="Minimizar" x-show="!dockMinimized" style="display:none; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; cursor:pointer; border-radius:50%; position:relative; z-index:10; flex-shrink:0; padding:0; margin:0;" class="radio-player-minimize-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block; margin:auto;"><polyline points="18 15 12 9 6 15"></polyline></svg>
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
        </div>

        <section
            class="radio-player-band-window"
            x-show="bandWindowOpen"
            x-transition.opacity
            style="display:none; position:fixed; inset:0; z-index:120; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.72); backdrop-filter:blur(8px);"
            @click.self="closeBandWindow()"
            @keydown.escape.window="closeBandWindow()"
        >
            <div class="sr-band-modal-container" style="position:relative; width:min(1100px, calc(100vw - 24px)); height:min(75vh, 720px); border:1px solid rgba(184,175,162,.22); border-radius:28px; background:linear-gradient(180deg, rgba(16,16,18,.92), rgba(10,10,11,.96)); backdrop-filter:blur(12px); box-shadow:0 28px 72px rgba(0,0,0,.58); padding:22px; overflow:visible; overscroll-behavior:contain; margin:auto; display:flex; flex-direction:column;">
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
                        <div style="display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; gap:14px;">
                            <div class="sr-band-header" style="display:flex; gap:22px; align-items:flex-start; flex:0 0 auto;">
                                <div style="flex-shrink:0;">
                                    <img class="sr-band-cover"
                                        data-player-band-cover-image
                                        :src="bandPanel.cover || track.band_thumbnail || track.cover || fallbackCover"
                                        alt=""
                                        onerror="this.onerror=null; this.src='{{ $fallbackCover }}'; this.style.width=''; this.style.height=''; this.style.minWidth='';"
                                        style="width:200px; height:200px; min-width:200px; object-fit:cover; border-radius:16px; border:1px solid rgba(184,175,162,.20); box-shadow:0 12px 32px rgba(0,0,0,.40); background:rgba(255,255,255,.04);"
                                        loading="lazy">
                                </div>

                                <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:8px;">
                                    <h3 data-player-band-title style="margin:0; color:#fff; font-family:var(--font-display); text-transform:uppercase; font-size:20px;" x-text="bandPanel.title || track.title || ''"></h3>
                                    <p data-player-band-artist style="margin:0; color:#b9b1a5; font-size:14px;" x-text="bandPanel.artist || track.artist || ''"></p>
                                    <p x-show="(bandPanel.foundedLabel || track.band_founded_label)" style="margin:0; color:#b7ad9f; font-size:12px; letter-spacing:.08em; text-transform:uppercase;" x-text="bandPanel.foundedLabel || track.band_founded_label"></p>

                                    <div x-show="bandPanel.country || track.band_country || bandPanel.genre || track.band_genre || bandPanel.membersCount || track.band_members_count || bandPanel.status || track.band_status" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                                        <span x-show="bandPanel.country || track.band_country" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🌍 ' + (bandPanel.country || track.band_country || '')"></span>
                                        <span x-show="bandPanel.genre || track.band_genre" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🎵 ' + (bandPanel.genre || track.band_genre || '')"></span>
                                        <span x-show="bandPanel.membersCount || track.band_members_count" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'👥 ' + ((bandPanel.membersCount ?? track.band_members_count) || 0) + ' miembros'"></span>
                                        <span x-show="bandPanel.status || track.band_status" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="(() => { const status = bandPanel.status || track.band_status || ''; return status === 'active' ? '✅ Activo' : (status === 'on_hold' ? '⏸ En pausa' : (status === 'disbanded' ? '❌ Disuelto' : status)); })()"></span>
                                    </div>

                                    <div x-show="bandLinks().length" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                                        <template x-for="link in bandLinks()" :key="link.url">
                                            <a :href="link.url" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.2); color:#dcd7cb; text-decoration:none; font-family:var(--font-display); font-size:10px; letter-spacing:.14em; text-transform:uppercase; border-radius:14px;" x-text="link.label"></a>
                                        </template>
                                    </div>

                                    <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                                        <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'LIVE' : 'PLAYBACK'"></span>
                                        <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" :style="isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; min-height:28px; padding:0 10px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;' : 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; min-height:28px; padding:0 10px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; border-radius:14px;'">
                                            <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                                            <span data-player-favorite-label>Like</span>
                                        </button>
                                    </div>

                                    <div x-show="bandPanel.logo || track.band_logo" style="margin-top:6px;">
                                        <img :src="bandPanel.logo || track.band_logo" alt="" style="max-height:36px; width:auto; object-fit:contain; opacity:0.8;" onerror="this.style.display='none'" loading="lazy">
                                    </div>
                                </div>
                            </div>

                            <div x-show="isMobile" class="sr-band-tabs" x-cloak style="display:none; gap:8px; margin-top:0; flex:0 0 auto;">
                                <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')" style="flex:1 1 0; min-height:38px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); color:#dcd7ca; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase; border-radius:12px;">Letra</button>
                                <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')" style="flex:1 1 0; min-height:38px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); color:#dcd7ca; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase; border-radius:12px;">Información</button>
                            </div>

                            <div class="sr-band-columns" style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:4px; flex:1; min-height:0; overflow:hidden;">
                                <section
                                    class="sr-band-lyrics-col"
                                    x-show="!isMobile || activeTab === 'lyrics'"
                                    style="display:flex; flex-direction:column; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px; min-height:0; overflow:hidden;"
                                >
                                    <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Letra</h4>
                                    <div class="sr-band-text-scroll" style="flex:1; min-height:0; overflow-y:auto; max-height:400px;">
                                        <p style="color:#e7e1d6; line-height:1.8; margin:0; white-space:pre-line; overflow-wrap:anywhere; font-size:14px;" x-text="track.lyrics || 'Letra no disponible para este tema.'"></p>
                                    </div>
                                </section>

                                <section
                                    class="sr-band-info-col"
                                    x-show="!isMobile || activeTab === 'band'"
                                    style="display:flex; flex-direction:column; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px; min-height:0; overflow:hidden;"
                                >
                                    <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Información</h4>
                                    <div class="sr-band-text-scroll" style="flex:1; min-height:0; overflow-y:auto; max-height:400px;">
                                        <p data-player-band-info style="color:#d8d3ca; line-height:1.8; margin:0; white-space:pre-line; overflow-wrap:anywhere; font-size:14px;" x-text="bandPanel.info || track.band_info || 'Buscando información de banda...'"></p>

                                        <div x-show="bandPanel.country || track.band_country || bandPanel.genre || track.band_genre || bandPanel.membersCount || track.band_members_count || bandPanel.status || track.band_status" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:12px;">
                                            <span x-show="bandPanel.country || track.band_country" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🌍 ' + (bandPanel.country || track.band_country || '')"></span>
                                            <span x-show="bandPanel.genre || track.band_genre" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'🎵 ' + (bandPanel.genre || track.band_genre || '')"></span>
                                            <span x-show="bandPanel.membersCount || track.band_members_count" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="'👥 ' + ((bandPanel.membersCount ?? track.band_members_count) || 0) + ' miembros'"></span>
                                            <span x-show="bandPanel.status || track.band_status" style="display:inline-flex; align-items:center; gap:4px; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.18); background:rgba(0,0,0,.12); color:#c4bdb0; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="(() => { const status = bandPanel.status || track.band_status || ''; return status === 'active' ? '✅ Activo' : (status === 'on_hold' ? '⏸ En pausa' : (status === 'disbanded' ? '❌ Disuelto' : status)); })()"></span>
                                        </div>

                                        <template x-if="Array.isArray(bandPanel.facts) && bandPanel.facts.length">
                                            <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:6px; border-top:1px solid rgba(184,175,162,.12); padding-top:10px;">
                                                <template x-for="fact in bandPanel.facts" :key="fact">
                                                    <span style="display:inline-flex; align-items:center; min-height:22px; padding:0 8px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#d8d3ca; font-size:10px; letter-spacing:.06em; text-transform:uppercase; border-radius:11px;" x-text="fact"></span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </section>
                            </div>

                            <div x-show="Array.isArray(track.band_members) && track.band_members.length > 0" style="margin-top:0; display:grid; gap:8px; padding:14px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px; flex:0 0 auto;">
                                <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Alineación</h4>
                                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                    <template x-for="member in (Array.isArray(track.band_members) ? track.band_members : [])" :key="typeof member === 'string' ? member : (member.name || member.member || member.title || JSON.stringify(member))">
                                        <div style="display:flex; flex-direction:column; gap:2px; padding:8px 12px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); border-radius:12px;">
                                            <strong style="color:#e6e0d6; font-size:12px;" x-text="typeof member === 'string' ? member : (member.name || member.member || member.title || '')"></strong>
                                            <span x-show="typeof member === 'object' && member && (member.role || member.instrument || member.position)" style="color:#a7a093; font-size:10px; letter-spacing:.06em; text-transform:uppercase;" x-text="member.role || member.instrument || member.position"></span>
                                        </div>
                                    </template>
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
                                <h2 x-text="programInfo.name || ''" style="color:#ddd7cb; font-size:18px; font-weight:700; line-height:1.2; margin:0;"></h2>
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
                                    <p x-text="programInfo.description || 'Información del programa no disponible.'" style="color:#a7a093; font-size:13px; line-height:1.6; margin:0; white-space:pre-line;"></p>
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
                        <small x-text="track.program_name || ''"></small>
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
    grid-template-columns: minmax(50px,.08fr) minmax(0,1fr) auto !important;
    gap:6px !important; padding:6px 8px !important;
    width:calc(100vw - 12px) !important; min-height:54px !important;
  }
  .radio-player-dock .rbcloud_nowplaying button img { width:60px !important; height:60px !important; }
  .radio-player-dock .radio-player-meta strong { font-size:12px !important; }
  .radio-player-dock .radio-player-meta span { font-size:10px !important; }
  .radio-player-dock .rbcloud_tracktimer { display: flex !important; }
  .radio-player-dock .radio-player-actions > div { display:none !important; }
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
  .sr-band-tabs { display: none !important; }
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

  /* EXPANDED: single row — cover | meta | play/actions | volume */
  .radio-player-dock:not(.is-minimized) {
    grid-template-columns: 64px minmax(0,1fr) auto minmax(120px,160px) !important;
    grid-template-rows: auto !important;
    gap:8px !important;
    padding:8px 10px !important;
    width:calc(100vw - 16px) !important;
    min-height:auto !important;
    align-items:center !important;
  }

  /* Row 1: cover + meta + chips */
  .radio-player-dock:not(.is-minimized) .rbcloud_nowplaying {
    grid-row: 1;
    display:flex !important;
    flex-direction:row !important;
    gap:12px !important;
    align-items:center !important;
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

  .radio-player-dock:not(.is-minimized) .radio-player-actions {
    grid-column: 3;
    display:flex !important;
    flex-wrap:nowrap !important;
    justify-content:center !important;
    align-items:center !important;
    gap:6px !important;
    padding:0 !important;
    width:auto !important;
    transform:none !important;
    margin:0 !important;
  }

  /* No wrapping divs for volume inside actions on mobile expanded */
  .radio-player-dock:not(.is-minimized) .radio-player-actions > div {
    display:none !important;
  }

  /* Compact buttons on mobile expanded */
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="play"] {
    min-width:48px !important;
    width:48px !important;
    height:48px !important;
    padding:0 !important;
    justify-content:center !important;
    border-width:2px !important;
    font-size:16px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="details"],
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="mute"],
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="favorite"] {
    min-width:40px !important;
    width:40px !important;
    height:40px !important;
    padding:0 !important;
    justify-content:center !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-minimize-btn {
    min-width:40px !important;
    width:40px !important;
    height:40px !important;
    padding:0 !important;
    justify-content:center !important;
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

  /* Row 2: controls inline */
  .radio-player-dock:not(.is-minimized) .radio-player-actions {
    grid-row: 2;
    display:flex !important;
    flex-wrap:nowrap !important;
    justify-content:center !important;
    align-items:center !important;
    gap:8px !important;
    padding:0 !important;
    width:auto !important;
    transform:none !important;
    margin:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > div {
    display:none !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="play"] {
    min-width:44px !important;
    width:44px !important;
    height:44px !important;
    padding:0 !important;
    justify-content:center !important;
    font-size:16px !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="details"],
  .radio-player-dock:not(.is-minimized) .radio-player-actions > button[data-player-action="favorite"] {
    min-width:36px !important;
    width:36px !important;
    height:36px !important;
    padding:0 !important;
  }

  .radio-player-dock:not(.is-minimized) .radio-player-minimize-btn {
    min-width:36px !important;
    width:36px !important;
    height:36px !important;
    padding:0 !important;
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
