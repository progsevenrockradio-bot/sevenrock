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
    data-player-fallback-cover="{{ $fallbackCover }}"
    data-player-default-title=""
    data-player-default-artist=""
    x-bind:data-mode="mode"
    x-data="radioPlayer({
        mode: @js($mode),
        statusUrl: @js(route('api.player.status')),
        streamUrl: @js($player['streams']['direct']),
        altStreamUrl: @js($player['streams']['alt_direct']),
        listenUrl: @js($player['streams']['listen']),
        bandInfoUrl: @js(route('api.player.band-info')),
        playlistM3u: @js($player['streams']['m3u']),
        playlistPls: @js($player['streams']['pls']),
        fallbackCover: @js($fallbackCover),
        pollInterval: @js($player['poll_interval']),
        historyLimit: @js($player['history_limit']),
    })"
    x-init="init()"
>
    <audio x-ref="audio" data-radio-audio src="{{ $player['streams']['direct'] }}" preload="none" playsinline></audio>

    @if ($mode === 'popup')
        <div class="radio-player-popup-shell" style="display:flex; flex-direction:column; min-height:100vh; background:rgba(0,0,0,.18); overflow:hidden;">
            <div class="radio-player-popup-stage" aria-hidden="true" style="position:relative; flex:1 1 auto; min-height:calc(100vh - 92px); background:linear-gradient(180deg, rgba(0,0,0,.84), rgba(0,0,0,.76));">
                <span class="radio-player-popup-stage-label">Compartir Pop-out</span>
            </div>

            <section class="radio-player-popup-bar" style="position:relative; display:grid; grid-template-columns:minmax(280px,1.1fr) minmax(220px,.8fr) 1fr; gap:14px; align-items:center; min-height:92px; padding:10px 14px 10px 8px; border-top:1px solid rgba(195,39,32,.48); background:linear-gradient(180deg, rgba(16,16,18,.98), rgba(8,8,10,.98));">
                <div class="radio-player-popup-track" style="display:grid; grid-template-columns:56px minmax(0,1fr); gap:10px; align-items:center;">
                    <img class="radio-player-popup-cover" :src="track.cover || fallbackCover" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" style="width:56px; height:56px; object-fit:cover; display:block;">
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
                        <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" style="width:100%; accent-color:#b7ad9f;">
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
                        <p style="white-space:pre-line; overflow-wrap:anywhere;" x-text="track.band_info || track.comment || 'Buscando información de banda...'"></p>
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
                                        <img :src="item.cover || fallbackCover" alt="">
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
    @else
        <div
            class="radio-player-dock"
            aria-label="Reproductor"
            :style="dockMinimized
                ? 'position:fixed; left:50%; bottom:12px; z-index:90; display:grid; grid-template-columns:minmax(94px,.11fr) minmax(232px,.34fr) minmax(244px,.28fr) minmax(290px,.27fr); align-items:center; gap:10px; width:min(1100px, calc(100vw - 24px)); min-height:58px; padding:6px 12px 6px 10px; border:1px solid rgba(184,175,162,.28); background:linear-gradient(180deg, rgba(18,17,16,.98), rgba(12,11,10,.98)); box-shadow:0 10px 26px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.03); transform:translateX(-50%); pointer-events:auto; overflow:hidden;'
                : 'position:fixed; left:50%; bottom:12px; z-index:90; display:grid; grid-template-columns:minmax(104px,.11fr) minmax(260px,.34fr) minmax(260px,.29fr) minmax(326px,.26fr); align-items:center; gap:10px; width:min(1160px, calc(100vw - 24px)); min-height:66px; padding:8px 12px 8px 10px; border:1px solid rgba(184,175,162,.28); background:linear-gradient(180deg, rgba(18,17,16,.98), rgba(12,11,10,.98)); box-shadow:0 12px 32px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.03); transform:translateX(-50%); pointer-events:auto; overflow:hidden;'"
        >
                <div class="rbcloud_nowplaying" style="display:flex; flex-direction:column; gap:4px; min-width:0; align-items:flex-start; padding-left:0; margin-right:0;">
                    <button type="button" data-player-band-trigger @click="setTab('lyrics'); openBandWindow()" aria-label="Abrir información de la banda" style="appearance:none; display:inline-flex; border:0; background:transparent; padding:0; cursor:pointer; text-align:left;">
                        <img id="rbcloud_np_c1266" class="radio-player-cover" data-player-cover-image src="https://c30.radioboss.fm/w/artwork/569.jpg" alt="cover art" x-bind:style="dockMinimized ? 'width:62px; height:62px; border:1px solid rgba(184,175,162,.18); box-shadow:0 1px 10px rgba(0,0,0,.2); object-fit:cover;' : 'width:84px; height:84px; border:1px solid rgba(184,175,162,.18); box-shadow:0 1px 10px rgba(0,0,0,.2); object-fit:cover;'">
                    </button>
                </div>
                <script src="https://c30.radioboss.fm/w/nowplaying2.js?u=569&amp;wid=1266&amp;tf=1" defer></script>

                <div style="display:flex; flex-direction:column; gap:2px; min-width:0; justify-content:center; padding-left:0; margin-left:-18px; transform:translateY(-1px);">
                    <div class="radio-player-meta" style="min-width:0; gap:2px; overflow:hidden; align-items:flex-start;">
                        <strong id="rbcloud_np_t1266" data-player-title-text style="font-size:14px; color:#ddd7cb; line-height:1.08; max-width:100%;" x-text="track.title || ''"></strong>
                        <span id="rbcloud_np_a1266" data-player-artist-text style="font-size:12px; color:#b9b1a5; line-height:1.08; max-width:100%;" x-text="track.artist || ''"></span>
                    </div>
                    <div class="rbcloud_tracktimer" style="display:flex; align-items:center; justify-content:flex-start; gap:8px; min-height:18px; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.18em; text-transform:uppercase; white-space:nowrap; margin-top:0;">
                        <span id='rbcloud_tracktimer_e13829'></span>
                        <span id='rbcloud_tracktimer_sep13829' hidden> / </span>
                        <span id='rbcloud_tracktimer_r13829'></span>
                    </div>
                    <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&t=0&wid=13829" defer></script>
                </div>

                <span class="radio-player-actions" style="display:flex; flex:0 0 auto; align-items:center; justify-content:flex-end; gap:8px; white-space:nowrap; margin-left:auto; padding-right:4px; transform:translateX(8px);">
                    <button type="button" class="radio-player-icon" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause" style="display:inline-flex; width:auto; min-width:102px; min-height:40px; padding:0 12px; gap:8px; border-color:rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; font-family:var(--font-display); font-size:11px; letter-spacing:.14em; text-transform:uppercase; line-height:1; flex-shrink:0;">
                        <span x-text="playing ? '❚❚' : '▶'">▶</span>
                        <span x-text="playing ? 'Pause' : 'Play'">Play</span>
                    </button>
                    <div style="display:flex; align-items:center; justify-content:center; min-width:0; flex:1 1 380px; max-width:520px;">
                        <div style="display:grid; grid-template-columns:auto minmax(220px,1fr) auto; align-items:center; gap:12px; width:100%; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.16); padding:8px 12px; box-shadow:inset 0 1px 0 rgba(255,255,255,.03);">
                            <span style="color:#b7ad9f; font-family:var(--font-display); font-size:10px; letter-spacing:.18em; text-transform:uppercase;">Vol</span>
                            <input data-player-volume-input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" style="width:100%; accent-color:#b7ad9f;">
                            <span data-player-volume-output x-text="Math.round(volume * 100) + '%'" style="color:#b7ad9f; font-family:var(--font-display); font-size:10px; letter-spacing:.12em; text-transform:uppercase;">80%</span>
                        </div>
                    </div>
                    <button type="button" class="radio-player-icon" data-player-action="details" @click.stop="setTab('lyrics'); openBandWindow()" title="Detalles" style="display:inline-flex; width:auto; min-width:102px; min-height:40px; padding:0 12px; gap:8px; border-color:rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; font-family:var(--font-display); font-size:11px; letter-spacing:.14em; text-transform:uppercase; line-height:1; flex-shrink:0;">
                        <span>i</span>
                        <span>Detalles</span>
                    </button>
                    <button type="button" class="radio-player-icon" data-player-action="mute" @click.stop="toggleMute()" aria-label="Silenciar" title="Silenciar" style="display:inline-flex; width:42px; height:40px; border-color:rgba(184,175,162,.38); background:rgba(0,0,0,.22); color:#dcd7cc; line-height:1; flex-shrink:0;">
                        <span data-player-mute-muted-icon x-show="muted" aria-hidden="true" style="display:inline-flex; width:18px; height:18px; align-items:center; justify-content:center;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9l5 6"></path>
                                <path d="M21 9l-5 6"></path>
                            </svg>
                        </span>
                        <span data-player-mute-unmuted-icon x-show="!muted" aria-hidden="true" style="display:inline-flex; width:18px; height:18px; align-items:center; justify-content:center;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9a4 4 0 0 1 0 6"></path>
                                <path d="M19 7a8 8 0 0 1 0 10"></path>
                            </svg>
                        </span>
                    </button>
                    <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" :style="(isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; min-height:40px; padding:0 12px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; box-shadow:0 0 0 1px rgba(195,39,32,.14) inset;' : 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; min-height:40px; padding:0 12px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer;') + '; flex-shrink:0;'">
                        <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                        <span data-player-favorite-label>Like</span>
                    </button>
                </span>
        </div>

        <section
            class="radio-player-band-window"
            x-show="bandWindowOpen"
            x-transition.opacity
            x-cloak
            style="display:flex; position:fixed; inset:0; z-index:120; align-items:center; justify-content:center; padding:18px; background:rgba(0,0,0,.72); backdrop-filter:blur(8px);"
            @click.self="closeBandWindow()"
            @keydown.escape.window="closeBandWindow()"
        >
            <div style="position:relative; width:min(1320px, calc(100vw - 24px)); max-height:min(86vh, 900px); border:1px solid rgba(184,175,162,.22); border-radius:28px; background:linear-gradient(180deg, rgba(16,16,18,.92), rgba(10,10,11,.96)); backdrop-filter:blur(12px); box-shadow:0 28px 72px rgba(0,0,0,.58); padding:22px; overflow:auto; margin:auto;">
                <button type="button" data-player-band-close @click="closeBandWindow()" aria-label="Cerrar" style="position:absolute; right:16px; top:14px; appearance:none; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.22); color:#dcd7cb; width:38px; height:38px; display:grid; place-items:center; cursor:pointer;">×</button>
                <div style="display:grid; grid-template-columns:minmax(320px,360px) minmax(0,1fr); gap:22px; align-items:start;">
                    <aside style="display:flex; flex-direction:column; gap:14px; min-width:0;">
                        <div style="position:relative;">
                            <img data-player-band-cover-image src="{{ $fallbackCover }}" :src="bandPanel.cover || track.band_thumbnail || track.cover || fallbackCover" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" style="width:100%; aspect-ratio:1/1; object-fit:cover; border:1px solid rgba(184,175,162,.20); box-shadow:0 24px 56px rgba(0,0,0,.50); transform:translateY(-14px) scale(1.01); border-radius:20px;">
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; justify-content:flex-start;">
                            <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'LIVE' : 'PLAYBACK'"></span>
                            <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" :style="isFavoriteCurrent() ? 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(195,39,32,.55); background:rgba(195,39,32,.14); color:#fff; min-height:28px; padding:0 10px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer; box-shadow:0 0 0 1px rgba(195,39,32,.14) inset;' : 'display:inline-flex; align-items:center; justify-content:center; gap:6px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#dcd7cb; min-height:28px; padding:0 10px; font-family:var(--font-display); font-size:10px; letter-spacing:.16em; text-transform:uppercase; cursor:pointer;'">
                                <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                                <span data-player-favorite-label>Like</span>
                            </button>
                        </div>

                        <div style="display:grid; gap:8px; padding:14px 14px 12px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                            <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Redes</h4>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <template x-for="link in bandLinks()" :key="link.url">
                                    <a :href="link.url" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:0 12px; border:1px solid rgba(184,175,162,.28); background:rgba(0,0,0,.2); color:#dcd7cb; text-decoration:none; font-family:var(--font-display); font-size:10px; letter-spacing:.14em; text-transform:uppercase;" x-text="link.label"></a>
                                </template>
                                <p x-show="!bandLinks().length" style="margin:0; color:#8f877d; font-size:12px;">Sin enlaces disponibles.</p>
                            </div>
                        </div>

                        <div style="display:grid; gap:8px; padding:14px 14px 12px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                            <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Alineación</h4>
                            <div style="display:grid; gap:8px;">
                                <template x-for="member in (Array.isArray(track.band_members) ? track.band_members : [])" :key="typeof member === 'string' ? member : (member.name || member.member || member.title || JSON.stringify(member))">
                                    <div style="display:flex; flex-direction:column; gap:2px; padding:10px 12px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); border-radius:12px;">
                                        <strong style="color:#e6e0d6; font-size:13px;" x-text="typeof member === 'string' ? member : (member.name || member.member || member.title || '')"></strong>
                                        <span x-show="typeof member === 'object' && member && (member.role || member.instrument || member.position)" style="color:#a7a093; font-size:11px; letter-spacing:.06em; text-transform:uppercase;" x-text="member.role || member.instrument || member.position"></span>
                                    </div>
                                </template>
                                <p x-show="!Array.isArray(track.band_members) || !track.band_members.length" style="margin:0; color:#8f877d; font-size:12px;">Sin datos de alineación.</p>
                            </div>
                        </div>
                    </aside>
                    <div style="display:flex; flex-direction:column; gap:14px; min-width:0;">
                        <div>
                            <h3 data-player-band-title style="margin:8px 0 0; color:#fff; font-family:var(--font-display); text-transform:uppercase;" x-text="bandPanel.title || track.title || ''"></h3>
                            <p data-player-band-artist style="margin:4px 0 0; color:#b9b1a5;" x-text="bandPanel.artist || track.artist || ''"></p>
                            <p x-show="bandPanel.foundedLabel || track.band_founded_label" style="margin:4px 0 0; color:#b7ad9f; font-size:12px; letter-spacing:.08em; text-transform:uppercase;" x-text="bandPanel.foundedLabel || track.band_founded_label"></p>
                            <template x-if="Array.isArray(bandPanel.facts) && bandPanel.facts.length">
                                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                                    <template x-for="fact in bandPanel.facts.slice(0, 3)" :key="fact">
                                        <span style="display:inline-flex; align-items:center; min-height:24px; padding:0 10px; border:1px solid rgba(184,175,162,.22); background:rgba(0,0,0,.18); color:#d8d3ca; font-size:11px; letter-spacing:.06em; text-transform:uppercase;" x-text="fact"></span>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div style="display:flex; gap:8px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); padding:8px; border-radius:16px;">
                            <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')" style="flex:1 1 0; min-height:38px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); color:#dcd7ca; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Letra</button>
                            <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')" style="flex:1 1 0; min-height:38px; border:1px solid rgba(184,175,162,.16); background:rgba(255,255,255,.02); color:#dcd7ca; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Banda</button>
                        </div>

                        <div style="display:grid; gap:14px; min-width:0; min-height:0;">
                            <section x-show="activeTab === 'lyrics'" style="display:grid; gap:8px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                                <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Letra</h4>
                                <p style="color:#e7e1d6; line-height:1.75; margin:0; white-space:pre-line; overflow-wrap:anywhere; font-size:15px;" x-text="track.lyrics || 'Letra no disponible para este tema.'"></p>
                            </section>

                            <section x-show="activeTab === 'band'" style="display:grid; gap:10px; padding:16px; border:1px solid rgba(184,175,162,.14); background:rgba(0,0,0,.16); border-radius:18px;">
                                <h4 style="margin:0; color:#b7ad9f; font-family:var(--font-display); font-size:11px; letter-spacing:.16em; text-transform:uppercase;">Info de banda</h4>
                                <p data-player-band-info style="color:#d8d3ca; line-height:1.65; margin:0; white-space:pre-line; overflow-wrap:anywhere;" x-text="bandPanel.info || track.band_info || track.comment || 'Buscando información de banda...'"></p>
                            </section>
                        </div>
                    </div>
                </div>
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
                    <h2 class="radio-player-title" x-text="track.title || ''"></h2>
                    <p class="radio-player-subtitle" x-text="track.artist || ''"></p>
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
                    <img class="radio-player-cover-large" :src="track.cover || fallbackCover" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" style="width:128px; height:128px; min-height:128px;">
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
                        <p x-text="track.band_info || track.comment || 'Buscando información de banda...'"></p>
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
                                                <img :src="item.cover || fallbackCover" alt="">
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
                                            <img :src="item.cover || fallbackCover" alt="">
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

</div>
