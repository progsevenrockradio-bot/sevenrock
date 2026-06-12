@props(['mode' => 'dock'])

@php
    $player = config('player');
    $theme = $themeAppearance;
    $preferredSocialOrder = ['facebook', 'instagram', 'youtube'];
    $socialLinks = collect($theme['social_links'] ?? [])
        ->filter(fn (array $social): bool => trim((string) ($social['url'] ?? '')) !== '')
        ->map(static function (array $social): array {
            $network = strtolower(trim((string) ($social['network'] ?? 'social')));
            $label = match ($network) {
                'facebook' => 'Facebook',
                'instagram' => 'Instagram',
                'youtube' => 'YouTube',
                'x', 'twitter' => 'X',
                'tiktok' => 'TikTok',
                default => ucfirst($network !== '' ? $network : 'Social'),
            };
            $badge = match ($network) {
                'facebook' => 'f',
                'instagram' => 'ig',
                'youtube' => 'yt',
                'x', 'twitter' => 'x',
                'tiktok' => 'tt',
                default => strtoupper(substr($label, 0, 2)),
            };

            return [
                'order' => $network,
                'label' => $label,
                'badge' => $badge,
                'url' => trim((string) ($social['url'] ?? '')),
            ];
        })
        ->filter(fn (array $social): bool => in_array($social['order'], $preferredSocialOrder, true))
        ->sortBy(fn (array $social): int => array_search($social['order'], $preferredSocialOrder, true))
        ->values();
    $logoUrl = $theme['media']['logo_url'] ?? $theme['logo_url'] ?? asset('assets/lucille/logo.png');
    $fallbackCover = ! empty($theme['media']['home_album_cover_url'] ?? '') && ! str_contains($theme['media']['home_album_cover_url'], 'album3.jpg')
        ? $theme['media']['home_album_cover_url']
        : $logoUrl;
@endphp

@once
<style>
    .radio-player-share-panel {
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: 8px !important;
        width: 100% !important;
        margin-top: 12px !important;
        padding: 8px 0 !important;
    }
    .radio-player-share-panel-label {
        width: 100% !important;
        margin-bottom: 6px !important;
    }
    .radio-player-share-link {
        width: 40px !important;
        height: 40px !important;
        padding: 0 !important;
        border-radius: 50% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: unset !important;
    }
    .radio-player-share-link svg {
        width: 18px !important;
        height: 18px !important;
        display: block !important;
    }
    .radio-player-share-link-code,
    .radio-player-share-link-text {
        display: none !important;
    }

    @media (max-width: 767px) {
        .radio-player-dock.radio-player-mobile.is-expanded {
            grid-template-rows: auto auto !important;
            min-height: unset !important;
            height: auto !important;
            padding-bottom: 12px !important;
        }
        .radio-player-dock.radio-player-mobile.is-expanded .radio-player-dock-side {
            display: flex !important;
            grid-column: 1 / -1 !important;
            width: 100% !important;
            margin-top: 8px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding-top: 8px !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .radio-player-dock.radio-player-mobile.is-expanded .radio-player-dock-volume {
            display: flex !important;
            width: 100% !important;
            max-width: none !important;
            min-width: 0 !important;
            flex-grow: 1 !important;
            align-items: center !important;
            gap: 12px !important;
        }
        .radio-player-dock.radio-player-mobile.is-expanded .radio-player-dock-volume-range {
            display: inline-block !important;
            flex-grow: 1 !important;
            width: 100% !important;
            height: 24px !important;
            margin: 0 !important;
        }
        .radio-player-dock.radio-player-mobile.is-expanded .radio-player-dock-volume-output {
            display: inline-flex !important;
            min-width: 36px !important;
            justify-content: flex-end !important;
        }
    }
</style>
@endonce

    <div
        class="radio-player"
        data-radio-player-root
        data-player-status-url="{{ route('api.player.status') }}"
        data-player-stream-url="{{ $player['streams']['direct'] }}"
        data-player-stream-alt-url="{{ $player['streams']['alt_direct'] }}"
        data-player-listen-url="{{ $player['streams']['listen'] }}"
        data-player-band-info-url="{{ route('api.player.band-info') }}"
        data-player-program-info-url="{{ route('api.player.program-info') }}"
        data-player-favorites-url="{{ route('api.player.favorites.index') }}"
        data-player-favorites-toggle-url="{{ route('api.player.favorites.toggle') }}"
        data-player-favorites-import-url="{{ route('api.player.favorites.import') }}"
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
            favoritesUrl: @js(route('api.player.favorites.index')),
            favoritesToggleUrl: @js(route('api.player.favorites.toggle')),
            favoritesImportUrl: @js(route('api.player.favorites.import')),
            playlistM3u: @js($player['streams']['m3u']),
            playlistPls: @js($player['streams']['pls']),
            fallbackCover: @js($fallbackCover),
            logoUrl: @js($logoUrl),
            pollInterval: @js($player['poll_interval']),
            historyLimit: @js($player['history_limit']),
            defaultTitle: @js($player['defaults']['title'] ?? ''),
            defaultArtist: @js($player['defaults']['artist'] ?? ''),
        })"
    x-init="init(); $nextTick(() => { if (window.innerWidth <= 640) dockMinimized = true; })"
    >
    <audio x-ref="audio" data-radio-audio src="{{ $player['streams']['direct'] }}" preload="none" playsinline></audio>

    <div class="radio-player-sr-widget" aria-hidden="true">
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

        <div class="radio-player-popup-shell">
            <img
                class="radio-player-popup-bg"
                :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')"
                alt=""
                aria-hidden="true"
                loading="lazy"
            >
            <div class="radio-player-popup-overlay" aria-hidden="true"></div>

            <div class="radio-player-popup-content-area">
                <header class="radio-player-popup-header">
                    <div class="min-w-0">
                        <span class="radio-player-popup-brand">Seven Rock Radio</span>
                        <div class="radio-player-popup-header-meta">
                            <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'POP-UP'"></span>
                            <span class="radio-player-popup-header-subtitle" x-text="track.program_name || (track.artist || defaultArtist)"></span>
                        </div>
                    </div>

                    <div class="radio-player-popup-header-actions">
                        <button type="button" class="radio-player-popup-chip" @click="togglePanel()" :class="{ 'is-active': panelOpen }">Info</button>
                        <button type="button" class="radio-player-popup-chip" @click="toggleSharePanel()" :aria-expanded="sharePanelOpen">Share</button>
                        <button type="button" class="radio-player-popup-chip" @click="window.close()">Cerrar</button>
                    </div>
                </header>

                <div x-show="sharePanelOpen" x-cloak class="radio-player-share-panel radio-player-share-panel--popup" aria-label="Compartir emisión">
                    <span class="radio-player-share-panel-label">Compartir</span>
                    <a :href="shareTargets().facebook" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en Facebook" title="Facebook">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                        <span class="radio-player-share-link-code">FB</span>
                        <span class="radio-player-share-link-text">Facebook</span>
                    </a>
                    <a :href="shareTargets().whatsapp" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en WhatsApp" title="WhatsApp">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 14.25c-.25-.13-1.5-.74-1.73-.82-.23-.08-.4-.12-.57.14-.17.26-.67.84-.82 1.01-.15.17-.3.19-.55.06A7.4 7.4 0 0 1 10.3 12.3a8.13 8.13 0 0 1-1.39-1.73c-.15-.26 0-.4.12-.52.1-.1.25-.3.37-.44.13-.15.17-.25.26-.42.08-.17.04-.32 0-.45C9.6 8.63 9 7.37 8.78 6.85c-.22-.5-.45-.43-.6-.44l-.52-.01c-.18 0-.47.07-.72.34C6.7 7.01 6 7.7 6 9.1s1 2.76 1.14 2.95c.14.19 1.96 3 4.75 4.2.66.29 1.18.46 1.59.59.66.21 1.27.18 1.75.11.53-.08 1.63-.67 1.86-1.31.23-.65.23-1.2.16-1.31-.07-.11-.25-.18-.5-.3z"></path>
                            <path d="M3 21l1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                        </svg>
                        <span class="radio-player-share-link-code">WA</span>
                        <span class="radio-player-share-link-text">WhatsApp</span>
                    </a>
                    <a :href="shareTargets().telegram" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en Telegram" title="Telegram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                        <span class="radio-player-share-link-code">TG</span>
                        <span class="radio-player-share-link-text">Telegram</span>
                    </a>
                    <a :href="shareTargets().twitter" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en X" title="X">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
                            <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                        </svg>
                        <span class="radio-player-share-link-code">X</span>
                        <span class="radio-player-share-link-text">X</span>
                    </a>
                    <button type="button" class="radio-player-share-link radio-player-share-link--native" @click="shareCurrent()" aria-label="Compartir nativo" title="Compartir">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        <span class="radio-player-share-link-code">N</span>
                        <span class="radio-player-share-link-text">Nativo</span>
                    </button>
                </div>

                @if ($socialLinks->isNotEmpty())
                    <div class="radio-player-popup-socials">
                        <span class="radio-player-popup-socials-label">Síguenos</span>
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="radio-player-popup-social-link" aria-label="Seguir en {{ $social['label'] }}" title="{{ $social['label'] }}">
                                @if ($social['order'] === 'facebook')
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                    </svg>
                                @elseif ($social['order'] === 'instagram')
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                                    </svg>
                                @elseif ($social['order'] === 'youtube')
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
                                        <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
                                    </svg>
                                @else
                                    <span class="radio-player-popup-social-badge">{{ $social['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                <main class="radio-player-popup-stage">
                    <div class="radio-player-popup-track-wrap">
                        <div class="radio-player-popup-track">
                            <div class="radio-player-popup-cover-wrap">
                                <img
                                    class="radio-player-popup-cover"
                                    :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')"
                                    alt=""
                                    onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;"
                                    loading="lazy"
                                >
                                <div class="radio-player-popup-wavebars" :class="{ 'is-playing': playing }">
                                    <span class="radio-player-popup-wavebar radio-player-popup-wavebar--1"></span>
                                    <span class="radio-player-popup-wavebar radio-player-popup-wavebar--2"></span>
                                    <span class="radio-player-popup-wavebar radio-player-popup-wavebar--3"></span>
                                    <span class="radio-player-popup-wavebar radio-player-popup-wavebar--4"></span>
                                </div>
                            </div>

                            <div class="radio-player-popup-meta">
                                <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'PLAYBACK'"></span>
                                <h1 class="radio-player-popup-title" x-text="track.title || defaultTitle"></h1>
                                <p class="band-name-animated radio-player-popup-artist" x-text="track.artist || defaultArtist"></p>
                                <p class="radio-player-popup-state" x-text="track.is_live ? 'Transmitiendo ahora' : (track.program_name || '')"></p>
                                <p class="radio-player-popup-listeners" x-text="listeners > 0 ? `${listeners} oyentes` : ''"></p>
                            </div>
                        </div>
                    </div>

                    <div class="radio-player-popup-controls">
                        <div class="radio-player-popup-time-row">
                            <div class="radio-player-popup-tracktimer-wrap">
                                <div class="rbcloud_tracktimer radio-player-popup-tracktimer">
                                    <span id="rbcloud_tracktimer_e11096"></span>
                                    <span id="rbcloud_tracktimer_sep11096" hidden> &frasl; </span>
                                    <span id="rbcloud_tracktimer_r11096"></span>
                                </div>
                            </div>
                            <button type="button" class="radio-player-popup-control radio-player-popup-play-button" @click="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'">
                                <span class="radio-player-popup-action-icon" x-text="playing ? '❚❚' : '▶'"></span>
                            </button>
                            <div class="radio-player-popup-time-action radio-player-popup-time-action--favorite">
                                <button type="button" class="radio-player-popup-control radio-player-popup-fav-button" @click="toggleFavorite()" :aria-label="isFavoriteCurrent() ? 'Quitar de favoritos' : 'Me gusta'" :aria-pressed="isFavoriteCurrent()">
                                    <span x-text="isFavoriteCurrent() ? '♥' : '♡'"></span>
                                </button>
                                <span class="radio-player-favorite-count" x-show="favoriteCount > 0" x-cloak x-text="favoriteCount"></span>
                            </div>
                        </div>

                        <div class="radio-player-popup-progress">
                            <progress class="radio-player-popup-progress-meter" :value="progress.ratio" max="100" @click="seek($event)" aria-label="Progreso"></progress>
                        </div>

                        <div x-cloak x-show="showNextTrackWidget">
                            <x-radio.next-track-widget variant="popup" />
                        </div>
                    </div>
                </main>

                <div class="radio-player-popup-volume">
                    <button type="button" class="radio-player-popup-control radio-player-popup-mute" @click="toggleMute()" aria-label="Mute">
                        <span x-text="muted ? '🔇' : '🔊'"></span>
                    </button>
                    <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" class="radio-player-popup-volume-range">
                    <span class="radio-player-popup-volume-value" x-text="Math.round(volume * 100) + '%'">80%</span>
                </div>

                <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11096"></script>

                <section class="radio-player-popup-drawer overflow-hidden rounded-[22px] border border-white/10" x-show="panelOpen" x-transition.opacity>
                    <div class="radio-player-popup-drawer-grid">
                        <button type="button" class="radio-player-popup-tab" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')">Letra</button>
                        <button type="button" class="radio-player-popup-tab" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')">Banda</button>
                        <button type="button" class="radio-player-popup-tab" :class="{ 'is-active': activeTab === 'program' }" @click="setTab('program')">Programa</button>
                        <button type="button" class="radio-player-popup-tab" :class="{ 'is-active': activeTab === 'notices' }" @click="setTab('notices')">Noticias</button>
                        <button type="button" class="radio-player-popup-tab" :class="{ 'is-active': activeTab === 'history' }" @click="setTab('history')">Historial</button>
                    </div>

                    <div class="radio-player-popup-drawer-body">
                        <div x-show="activeTab === 'lyrics'">
                            <h4 class="radio-player-popup-section-title">Letra del tema</h4>
                            <p class="radio-player-popup-section-text" x-text="track.lyrics || 'Letra no disponible'"></p>
                        </div>

                        <div x-show="activeTab === 'band'">
                            <h4 class="radio-player-popup-section-title">Info de banda</h4>
                            <div class="radio-player-popup-source-line">
                                <span x-show="formatBiographySourceLabel(track.band_biography_source || '')" x-cloak class="radio-player-popup-source-badge" x-text="formatBiographySourceLabel(track.band_biography_source || '')"></span>
                            </div>
                            <p class="radio-player-popup-section-text" x-text="track.band_biography || track.band_info || 'Buscando información de banda...'"></p>
                        </div>

                        <div x-show="activeTab === 'program'">
                            <h4 class="radio-player-popup-section-title">Programa</h4>
                            <p class="radio-player-popup-section-text" x-text="programText()"></p>
                            <template x-if="nextProgram">
                                <div class="radio-player-popup-card">
                                    <span class="radio-player-popup-card-label">Próximo programa</span>
                                    <strong class="radio-player-popup-card-title" x-text="nextProgram.name"></strong>
                                    <small class="radio-player-popup-card-meta" x-text="nextProgram.schedule || ''"></small>
                                </div>
                            </template>
                        </div>

                        <div x-show="activeTab === 'notices'">
                            <h4 class="radio-player-popup-section-title">Noticias</h4>
                            <template x-if="notices.length">
                                <div class="radio-player-popup-stack">
                                    <template x-for="notice in notices" :key="notice.title">
                                        <article class="radio-player-popup-card">
                                            <strong class="radio-player-popup-card-title" x-text="notice.title"></strong>
                                            <p class="radio-player-popup-card-text" x-text="notice.content"></p>
                                        </article>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!notices.length" class="radio-player-popup-section-text">Sin avisos activos.</p>
                        </div>

                        <div x-show="activeTab === 'history'">
                            <h4 class="radio-player-popup-section-title">Historial</h4>
                            <!-- RadioBOSS Cloud Recent Tracks Widget (Start) -->
                            <div class='rbcloud_recenttracks' id='rbcloud_recent378' data-cnt='7'>
                                <div class='rbcloud_recent_track' style='display: flex; align-items: center; margin-bottom: 5pt;'>
                                    <div class='rbcloud_recent_track_cover' data-size='65'></div>
                                    <div style='margin-left: 5pt;'><div class='rbcloud_recent_artist' style='font-weight: bold'></div><div class='rbcloud_recent_title'>...</div></div>
                                </div>
                            </div>
                            <script src='https://c30.radioboss.fm/w/recent.js?u=569&amp;wid=378&amp;v=2&amp;tf=1'></script>
                            <!-- RadioBOSS Cloud Recent Tracks Widget (End) -->
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @else
        <div
            class="radio-player-dock is-dock-default"
            aria-label="Reproductor"
            :class="{ 'is-expanded': !dockMinimized }"
            :data-dock-state="dockMinimized ? 'minimized' : 'expanded'"
        >
            <div class="rbcloud_nowplaying radio-player-dock-cover-wrap">
                <span class="radio-player-live-pill radio-player-dock-live" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'EN VIVO' : 'PLAYBACK'"></span>
                <button type="button" class="radio-player-dock-trigger" data-player-band-trigger @click="toggleInfoWindow($event)" aria-label="Abrir información">
                    <img class="radio-player-cover radio-player-dock-cover" data-player-cover-image :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')" alt="cover art" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" loading="lazy">
                </button>
            </div>

            <div class="radio-player-meta-column">
                <div class="radio-player-dock-copy">
                    <span x-show="!dockMinimized" x-cloak class="radio-player-dock-line" aria-hidden="true"></span>
                    <div class="radio-player-meta radio-player-dock-meta">
                        <strong class="radio-player-dock-title" data-player-title-text x-text="track.title || defaultTitle"></strong>
                        <span x-show="!dockMinimized" x-cloak data-player-artist-text class="radio-player-dock-artist" x-text="track.artist || defaultArtist"></span>
                        <small x-show="!dockMinimized && track.program_name" x-cloak data-player-program-text class="radio-player-dock-program" x-text="track.program_name"></small>
                        <small x-show="!dockMinimized && !track.program_name && nextProgram" x-cloak data-player-next-program-text class="radio-player-dock-next-program" x-text="'Próximo: ' + (nextProgram.name || '') + (nextProgram.schedule_time ? ' a las ' + nextProgram.schedule_time : '')"></small>
                    </div>
                    <div x-show="!dockMinimized" x-cloak class="rbcloud_tracktimer radio-player-dock-timer">
                        <span id="rbcloud_tracktimer_e11096"></span>
                        <span id="rbcloud_tracktimer_sep11096" hidden> &frasl; </span>
                        <span id="rbcloud_tracktimer_r11096"></span>
                    </div>
                    <div class="radio-player-share-popout radio-player-dock-share-popout-desktop" x-show="!dockMinimized" x-cloak>
                        <button type="button" class="radio-player-chip radio-player-dock-share-button" @click="toggleSharePanel()" :aria-expanded="sharePanelOpen" title="Compartir" aria-label="Compartir">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </button>
                        <button type="button" class="radio-player-chip radio-player-dock-share-button" @click="openPopout()" title="Reproductor emergente" aria-label="Reproductor emergente">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="radio-player-mobile-share-popout" aria-label="Acciones móviles extra" x-show="!dockMinimized" x-cloak>
                        <button type="button" class="radio-player-chip radio-player-mobile-share" @click="toggleSharePanel()" :aria-expanded="sharePanelOpen" title="Compartir" aria-label="Compartir">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </button>
                        <button type="button" class="radio-player-chip radio-player-mobile-popout" @click="openPopout()" title="Reproductor emergente" aria-label="Reproductor emergente">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </button>
                    </div>
                    <div x-show="sharePanelOpen && !dockMinimized" x-cloak class="radio-player-share-panel radio-player-share-panel--dock" aria-label="Compartir emisión">
                        <span class="radio-player-share-panel-label">Compartir</span>
                        <a :href="shareTargets().facebook" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en Facebook" title="Facebook">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                            <span class="radio-player-share-link-code">FB</span>
                            <span class="radio-player-share-link-text">Facebook</span>
                        </a>
                        <a :href="shareTargets().whatsapp" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en WhatsApp" title="WhatsApp">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 14.25c-.25-.13-1.5-.74-1.73-.82-.23-.08-.4-.12-.57.14-.17.26-.67.84-.82 1.01-.15.17-.3.19-.55.06A7.4 7.4 0 0 1 10.3 12.3a8.13 8.13 0 0 1-1.39-1.73c-.15-.26 0-.4.12-.52.1-.1.25-.3.37-.44.13-.15.17-.25.26-.42.08-.17.04-.32 0-.45C9.6 8.63 9 7.37 8.78 6.85c-.22-.5-.45-.43-.6-.44l-.52-.01c-.18 0-.47.07-.72.34C6.7 7.01 6 7.7 6 9.1s1 2.76 1.14 2.95c.14.19 1.96 3 4.75 4.2.66.29 1.18.46 1.59.59.66.21 1.27.18 1.75.11.53-.08 1.63-.67 1.86-1.31.23-.65.23-1.2.16-1.31-.07-.11-.25-.18-.5-.3z"></path>
                                <path d="M3 21l1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                            </svg>
                            <span class="radio-player-share-link-code">WA</span>
                            <span class="radio-player-share-link-text">WhatsApp</span>
                        </a>
                        <a :href="shareTargets().telegram" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en Telegram" title="Telegram">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                            <span class="radio-player-share-link-code">TG</span>
                            <span class="radio-player-share-link-text">Telegram</span>
                        </a>
                        <a :href="shareTargets().twitter" target="_blank" rel="noopener noreferrer" class="radio-player-share-link" aria-label="Compartir en X" title="X">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
                                <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                            </svg>
                            <span class="radio-player-share-link-code">X</span>
                            <span class="radio-player-share-link-text">X</span>
                        </a>
                        <button type="button" class="radio-player-share-link radio-player-share-link--native" @click="shareCurrent()" aria-label="Compartir nativo" title="Compartir">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                            <span class="radio-player-share-link-code">N</span>
                            <span class="radio-player-share-link-text">Nativo</span>
                        </button>
                    </div>
                </div>
            </div>

            <span class="radio-player-actions radio-player-dock-actions">
                <button type="button" class="radio-player-icon radio-player-dock-icon radio-player-dock-icon--details" data-player-action="details" @click.stop="toggleInfoWindow($event)" title="Detalles">i</button>
                <div class="radio-player-actions-center radio-player-dock-actions-center">
                    <button type="button" data-player-action="expand" @click.stop="dockMinimized = false" aria-label="Expandir" title="Expandir" x-show="dockMinimized" x-cloak class="radio-player-expand-btn radio-player-dock-icon radio-player-dock-icon--toggle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                    <button type="button" class="radio-player-primary radio-player-dock-play" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause">
                        <span x-text="playing ? '❚❚' : '▶'">▶</span>
                    </button>
                    <button type="button" data-player-action="minimize" @click.stop="dockMinimized = true" aria-label="Minimizar" title="Minimizar" x-show="!dockMinimized" x-cloak class="radio-player-minimize-btn radio-player-dock-icon radio-player-dock-icon--toggle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                    </button>
                </div>
                <div class="radio-player-actions-spacer radio-player-dock-actions-spacer" aria-hidden="true"></div>
                <div class="radio-player-favorite-wrap">
                    <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" class="radio-player-dock-icon radio-player-dock-icon--favorite">
                        <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                    </button>
                    <span class="radio-player-favorite-count" x-show="favoriteCount > 0" x-cloak x-text="favoriteCount"></span>
                </div>
            </span>

            <div class="radio-player-dock-side">
                <div class="sr-dock-volume radio-player-dock-volume">
                    <button type="button" class="radio-player-dock-mute" data-player-action="mute" @click.stop="toggleMute()" aria-label="Silenciar" title="Silenciar">
                        <span data-player-mute-muted-icon x-show="muted" aria-hidden="true" class="radio-player-dock-mute-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9l5 6"></path>
                                <path d="M21 9l-5 6"></path>
                            </svg>
                        </span>
                        <span data-player-mute-unmuted-icon x-show="!muted" aria-hidden="true" class="radio-player-dock-mute-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 5 6 9H3v6h3l5 4z"></path>
                                <path d="M16 9a4 4 0 0 1 0 6"></path>
                                <path d="M19 7a8 8 0 0 1 0 10"></path>
                            </svg>
                        </span>
                    </button>
                    <input x-show="!dockMinimized" x-cloak data-player-volume-input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()" class="radio-player-dock-volume-range">
                    <span x-show="!dockMinimized" x-cloak data-player-volume-output x-text="Math.round(volume * 100) + '%'" class="radio-player-dock-volume-output">80%</span>
                </div>

                @if ($socialLinks->isNotEmpty())
                    <div x-show="!dockMinimized" x-cloak class="radio-player-dock-socials radio-player-dock-socials--icons">
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="radio-player-dock-social-link radio-player-dock-social-link--icon" aria-label="Seguir en {{ $social['label'] }}" title="{{ $social['label'] }}">
                                <span class="radio-player-dock-social-badge">{{ $social['badge'] }}</span>
                                <span class="radio-player-dock-social-label">{{ $social['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11096"></script>
            <script src="https://c30.radioboss.fm/w/tracktimer.js?u=569&amp;t=0&amp;wid=11097"></script>
        </div>

        <template x-if="bandWindowOpen">
        <section
            class="radio-modal-overlay radio-modal-overlay--band"
            x-cloak
            x-transition.opacity
            @click.self="closeBandWindow()"
            @keydown.escape.window="closeBandWindow()"
        >
            <div class="radio-modal-container radio-modal-container--band">
                <button type="button" class="radio-modal-close-btn" data-player-band-close @click.stop="closeBandWindow()" aria-label="Cerrar">×</button>

                <div class="radio-modal-stage radio-modal-stage--band">
                    <template x-if="bandInfoLoading">
                        <div class="radio-modal-skeleton">
                            <div class="radio-modal-skeleton__top">
                                <div class="radio-modal-skeleton__cover sr-pulse"></div>
                                <div class="radio-modal-skeleton__copy">
                                    <div class="radio-modal-skeleton__line radio-modal-skeleton__line--title sr-pulse"></div>
                                    <div class="radio-modal-skeleton__line radio-modal-skeleton__line--meta sr-pulse"></div>
                                    <div class="radio-modal-skeleton__line radio-modal-skeleton__line--meta sr-pulse"></div>
                                    <div class="radio-modal-skeleton__chips">
                                        <div class="radio-modal-skeleton__chip sr-pulse"></div>
                                        <div class="radio-modal-skeleton__chip sr-pulse"></div>
                                        <div class="radio-modal-skeleton__chip sr-pulse"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="radio-modal-skeleton__bottom">
                                <div class="radio-modal-skeleton__panel sr-pulse"></div>
                                <div class="radio-modal-skeleton__panel sr-pulse"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!bandInfoLoading">
                        <div class="radio-modal-content radio-modal-content--band">
                            <div class="radio-modal-header radio-modal-header--band">
                                <div class="radio-modal-media radio-modal-media--band" :class="{ 'is-lyrics': bandWindowTab === 'lyrics' }">
                                    <img
                                        class="radio-modal-cover radio-modal-cover--band radio-modal-cover--band-bio"
                                        data-player-band-cover-image
                                        :src="bandWindowBioCover"
                                        alt=""
                                        onerror="this.onerror=null; this.src='{{ $fallbackCover }}';"
                                        loading="lazy"
                                    >
                                    <img
                                        class="radio-modal-cover radio-modal-cover--band radio-modal-cover--band-track"
                                        :src="bandWindowTrackCover"
                                        alt=""
                                        onerror="this.onerror=null; this.src='{{ $fallbackCover }}';"
                                        loading="lazy"
                                    >
                                </div>

                                <div class="radio-modal-copy">
                                    <h3 class="radio-modal-title" data-player-band-title x-text="bandWindowView.title || ''"></h3>
                                    <p class="radio-modal-artist" data-player-band-artist x-text="bandWindowView.artist || ''"></p>
                                    <p
                                        x-show="resumenBio"
                                        x-cloak
                                        class="radio-modal-summary"
                                        data-player-band-summary
                                        x-text="resumenBio"
                                    ></p>
                                    <p x-show="bandWindowView.foundedLabel" class="radio-modal-label" x-text="bandWindowView.foundedLabel"></p>
                                    <div x-show="bandWindowView.country || bandWindowView.genre || bandWindowView.membersCount || bandWindowView.status" class="radio-modal-tags">
                                        <span x-show="bandWindowView.country" class="radio-modal-tag" x-text="'🌍 ' + (bandWindowView.country || '')"></span>
                                        <span x-show="bandWindowView.genre" class="radio-modal-tag" x-text="'🎵 ' + (bandWindowView.genre || '')"></span>
                                        <span x-show="bandWindowView.membersCount" class="radio-modal-tag" x-text="'👥 ' + (bandWindowView.membersCount || 0) + ' miembros'"></span>
                                        <span x-show="bandWindowView.status" class="radio-modal-tag" x-text="(() => { const status = bandWindowView.status || ''; return status === 'active' ? '✅ Activo' : (status === 'on_hold' ? '⏸ En pausa' : (status === 'disbanded' ? '❌ Disuelto' : status)); })()"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="radio-modal-tabs">
                                <button
                                    type="button"
                                    @click="setBandWindowTab('bio')"
                                    :aria-pressed="bandWindowTab === 'bio'"
                                    :class="bandWindowTab === 'bio' ? 'border-b-2 border-white text-white' : 'border-b-2 border-transparent text-[#a7a093] hover:text-white'"
                                    class="radio-modal-tab"
                                >
                                    Biografía
                                </button>
                                <button
                                    type="button"
                                    @click="setBandWindowTab('lyrics')"
                                    :aria-pressed="bandWindowTab === 'lyrics'"
                                    :class="bandWindowTab === 'lyrics' ? 'border-b-2 border-white text-white' : 'border-b-2 border-transparent text-[#a7a093] hover:text-white'"
                                    class="radio-modal-tab"
                                >
                                    Letras
                                </button>
                            </div>

                            <div class="radio-modal-body radio-modal-body--band">
                                <div
                                    :class="bandWindowTab === 'bio' ? 'is-active' : ''"
                                    :aria-hidden="bandWindowTab !== 'bio'"
                                    x-cloak
                                    class="radio-modal-pane"
                                >
                                    <div class="radio-modal-scroll">
                                    <section class="radio-modal-card">
                                        <div class="radio-modal-card-header">
                                            <h4 class="radio-modal-card-title">Biografía</h4>
                                            <span x-show="biographySourceLabel" x-cloak class="radio-modal-source-badge" x-text="biographySourceLabel"></span>
                                        </div>
                                        <div class="radio-modal-text-paragraphs" data-player-band-info>
                                            <template x-for="(paragraph, index) in (biographyExpanded || 'No hay una biografía ampliada disponible para esta banda.').split(/\r?\n\r?\n/)" :key="index">
                                                <p class="radio-modal-text" x-text="paragraph"></p>
                                            </template>
                                        </div>

                                        <div x-show="bandWindowView.logo || bandLinks().length" class="radio-modal-links">
                                            <template x-for="link in bandLinks()" :key="link.url">
                                                <a :href="link.url" target="_blank" rel="noopener noreferrer" class="radio-modal-link" x-text="link.label"></a>
                                            </template>
                                        </div>
                                    </section>

                                    <section x-show="Array.isArray(bandWindowView.bandMembers) && bandWindowView.bandMembers.length > 0" class="radio-modal-card">
                                        <h4 class="radio-modal-card-title">Integrantes</h4>
                                        <div class="radio-modal-members">
                                            <template x-for="member in (Array.isArray(bandWindowView.bandMembers) ? bandWindowView.bandMembers : [])" :key="typeof member === 'string' ? member : (member.name || member.member || member.title || JSON.stringify(member))">
                                                <div class="radio-modal-member">
                                                    <strong class="radio-modal-member-name" x-text="typeof member === 'string' ? member : (member.name || member.member || member.title || '')"></strong>
                                                    <span x-show="typeof member === 'object' && member && (member.role || member.instrument || member.position)" class="radio-modal-member-role" x-text="member.role || member.instrument || member.position"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </section>
                                    </div>
                                </div>

                                <div
                                    :class="bandWindowTab === 'lyrics' ? 'is-active' : ''"
                                    :aria-hidden="bandWindowTab !== 'lyrics'"
                                    x-cloak
                                    class="radio-modal-pane"
                                >
                                    <div class="radio-modal-scroll">
                                    <section class="radio-modal-card">
                                        <h4 class="radio-modal-card-title">Letra</h4>
                                        <p class="radio-modal-text radio-modal-text--lyrics" x-text="bandWindowView.lyrics && bandWindowView.lyrics.trim() ? bandWindowView.lyrics : 'No hay letra disponible para esta canción'"></p>
                                    </section>
                                </div>
                            </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
        </template>

        <!-- ========== MODAL DE PROGRAMA ========== -->
        <template x-if="programWindowOpen">
        <section
            class="radio-modal-overlay radio-modal-overlay--program"
            x-cloak
            :class="{ 'is-open': programWindowOpen }"
            x-transition.opacity
            @keydown.escape.window="closeProgramWindow()"
            @click.self="closeProgramWindow()"
        >
            <div class="radio-modal-container radio-modal-container--program" @click.stop>
                <div class="radio-modal-header-actions">
                    <button type="button" @click="openPopout()" class="radio-modal-header-action" aria-label="Abrir popout">Popout</button>
                    <button type="button" class="radio-modal-close-btn radio-modal-close-btn--program" @click="closeProgramWindow()" aria-label="Cerrar">×</button>
                </div>

                <template x-if="programInfoLoading && !programInfo">
                    <div class="radio-modal-skeleton radio-modal-skeleton--program">
                        <div class="radio-modal-skeleton__top radio-modal-skeleton__top--program">
                            <div class="radio-modal-skeleton__cover radio-modal-skeleton__cover--program sr-pulse"></div>
                            <div class="radio-modal-skeleton__copy radio-modal-skeleton__copy--program">
                                <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-title sr-pulse"></div>
                                <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-strong sr-pulse"></div>
                                <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-meta sr-pulse"></div>
                                <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-meta sr-pulse"></div>
                            </div>
                        </div>
                        <div class="radio-modal-skeleton__body radio-modal-skeleton__body--program">
                            <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-body sr-pulse"></div>
                            <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-body sr-pulse"></div>
                            <div class="radio-modal-skeleton__line radio-modal-skeleton__line--program-body sr-pulse"></div>
                        </div>
                    </div>
                </template>

                <template x-if="programInfo">
                    <div class="radio-modal-content radio-modal-content--program">
                        <div class="radio-modal-header radio-modal-header--program">
                            <img class="radio-modal-cover radio-modal-cover--program" :src="programInfo.cover || fallbackCover" :alt="programInfo.name || ''" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" loading="lazy">
                            <div class="radio-modal-copy radio-modal-copy--program">
                                <span x-show="track.is_live" class="radio-modal-live-badge">
                                    <span class="radio-modal-live-dot sr-pulse"></span>
                                    EN VIVO
                                </span>
                                <span class="radio-modal-program-genre" x-text="programInfo.genre || ''"></span>
                                <h2 class="radio-modal-program-title" x-text="track.program_name || programInfo.name || ''"></h2>
                                <p class="radio-modal-program-host" x-text="programInfo.host ? 'Conduce: ' + programInfo.host : ''"></p>
                                <p class="radio-modal-program-schedule" x-text="programInfo.schedule || ''"></p>
                                <div x-show="programInfo.social_links && (programInfo.social_links.facebook || programInfo.social_links.instagram)" class="radio-modal-program-socials">
                                    <a x-show="programInfo.social_links?.facebook" :href="programInfo.social_links.facebook" target="_blank" rel="noopener noreferrer" class="radio-modal-program-social">Facebook</a>
                                    <a x-show="programInfo.social_links?.instagram" :href="programInfo.social_links.instagram" target="_blank" rel="noopener noreferrer" class="radio-modal-program-social">Instagram</a>
                                </div>
                            </div>
                        </div>

                        <div class="radio-modal-divider"></div>

                        <div class="radio-modal-body radio-modal-body--program">
                            <template x-if="programInfo.episode">
                                <div>
                                    <div x-show="programInfo.episode.guest_image" class="radio-modal-guest-image-wrap">
                                        <img class="radio-modal-guest-image" :src="programInfo.episode.guest_image" :alt="programInfo.episode.title || ''" onerror="this.hidden = true; this.onerror = null;" loading="lazy">
                                    </div>
                                    <h3 class="radio-modal-episode-title" x-text="programInfo.episode.title || ''"></h3>
                                    <p class="radio-modal-episode-text" x-text="programInfo.episode.description || track.program_description || programInfo.description || 'Información del programa no disponible.'"></p>
                                    
                                    <div x-show="programInfo.episode.guest_bio" class="mt-3 border-t border-white/5 pt-2">
                                        <span class="text-[10px] uppercase tracking-wider text-[#7b7b7b]">Invitado</span>
                                        <p class="text-xs text-[#b4b4b4]" x-text="programInfo.episode.guest_bio"></p>
                                    </div>
                                    
                                    <small x-show="programInfo.episode.episode_number" class="radio-modal-episode-meta block mt-3" x-text="'Episodio ' + programInfo.episode.episode_number"></small>
                                </div>
                            </template>

                            <template x-if="!programInfo.episode">
                                <div>
                                    <h3 class="radio-modal-episode-title">Acerca del programa</h3>
                                    <p class="radio-modal-episode-text" x-text="track.program_description || programInfo.description || 'Información del programa no disponible.'"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </section>
        </template>

        @if ($mode !== 'page' && $mode !== 'popup')
        <section
            class="radio-player-panel radio-player-panel--dock"
            :class="{ 'is-open': panelOpen }"
            x-show="mode !== 'page' && panelOpen"
            x-transition
        >
            <header class="radio-player-head radio-player-head--dock">
                <div>
                    <div class="radio-player-kicker" x-text="track.is_live ? 'EN VIVO AHORA' : 'ON DEMAND'"></div>
                    <h2 class="radio-player-title" x-text="track.title || defaultTitle"></h2>
                    <p class="radio-player-subtitle" x-text="track.artist || defaultArtist"></p>
                    <small x-text="listeners > 0 ? `${listeners} oyentes` : 'Sin oyentes'"></small>
                </div>
                <div class="radio-player-head-actions radio-player-head-actions--dock">
                    <button type="button" class="radio-player-chip radio-player-chip--ghost" @click="closePanel()">Min</button>
                </div>
            </header>

            <div class="player-expanded-container">
                <section class="player-expanded-main">
                    <div class="player-expanded-hero">
                        <div class="radio-player-cover-stack radio-player-cover-stack--dock">
                            <img class="radio-player-cover-large radio-player-cover-large--dock" :src="(track.cover || fallbackCover) + ((track.signature || '') ? ('?v=' + encodeURIComponent(track.signature)) : '')" alt="" onerror="this.src='{{ $fallbackCover }}'; this.onerror=null;" loading="lazy">
                            <div class="radio-player-cover-actions" aria-label="Acciones de portada">
                                <button type="button" class="radio-player-chip radio-player-chip--ghost" @click="shareCurrent()">Share</button>
                                <button type="button" class="radio-player-chip radio-player-chip--ghost" @click="openPopout()">Popup</button>
                            </div>
                        </div>

                        <div class="radio-player-now-copy radio-player-now-copy--dock player-expanded-copy">
                            <span class="player-expanded-accent-line" aria-hidden="true"></span>
                            <span class="radio-player-live-pill" :class="{ 'is-live': track.is_live }" x-text="track.is_live ? 'LIVE' : 'PLAYBACK'"></span>
                            <h3 x-text="track.title || defaultTitle"></h3>
                            <p x-text="track.artist || defaultArtist"></p>
                            <small x-show="track.program_name" x-text="track.program_name"></small>
                            <small x-show="!track.program_name && nextProgram" x-text="'Próximo: ' + (nextProgram.name || '') + (nextProgram.schedule_time ? ' a las ' + nextProgram.schedule_time : '')"></small>
                            <small x-text="listeners > 0 ? `${listeners} oyentes` : ''"></small>

                            <div class="radio-player-progress-wrap radio-player-progress-wrap--dock">
                                <div class="radio-player-time">
                                    <span x-text="formatTime(progress.elapsed)"></span>
                                    <span x-text="formatTime(progress.duration)"></span>
                                </div>
                                <progress class="radio-player-progress-meter radio-player-progress-meter--dock" :value="progress.ratio" max="100" @click="seek($event)" aria-label="Progreso"></progress>
                            </div>

                            <div x-cloak x-show="showNextTrackWidget">
                                <x-radio.next-track-widget variant="dock" />
                            </div>

                            <div class="player-expanded-controls" aria-label="Controles principales">
                                <button type="button" class="player-expanded-control" data-player-action="details" @click.stop="toggleInfoWindow($event)" aria-label="Detalles" title="Detalles">
                                    <span class="player-expanded-control__icon">i</span>
                                </button>
                                <button type="button" class="player-expanded-control player-expanded-control--play" data-player-action="play" @click.stop="togglePlay()" :aria-label="playing ? 'Pausar' : 'Reproducir'" title="Play / Pause">
                                    <span class="player-expanded-control__icon" x-text="playing ? '❚❚' : '▶'">▶</span>
                                </button>
                                <button type="button" class="player-expanded-control" data-player-action="minimize" @click.stop="closePanel()" aria-label="Minimizar" title="Minimizar">
                                    <span class="player-expanded-control__icon">⌄</span>
                                </button>
                                <div class="player-expanded-control-group">
                                    <button type="button" class="player-expanded-control" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" title="Me gusta">
                                        <span class="player-expanded-control__icon" data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                                    </button>
                                    <span class="radio-player-favorite-count" x-show="favoriteCount > 0" x-cloak x-text="favoriteCount"></span>
                                </div>
                            </div>

                            <div class="radio-player-controls radio-player-controls--dock">
                                <button type="button" class="radio-player-secondary radio-player-secondary--square" @click="toggleMute()" x-text="muted ? 'Unmute' : 'Mute'"></button>
                                <label class="radio-player-volume radio-player-volume--dock">
                                    <span>Vol</span>
                                    <input type="range" min="0" max="1" step="0.01" x-model.number="volume" @input="updateVolume()">
                                </label>
                            </div>

                            @if ($socialLinks->isNotEmpty())
                                <div class="player-expanded-socials player-expanded-socials--icons" aria-label="Redes sociales">
                                    @foreach ($socialLinks as $social)
                                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="radio-player-dock-social-link radio-player-dock-social-link--expanded radio-player-dock-social-link--icon" aria-label="Seguir en {{ $social['label'] }}" title="{{ $social['label'] }}">
                                            <span class="radio-player-dock-social-badge">{{ $social['badge'] }}</span>
                                            <span class="radio-player-dock-social-label">{{ $social['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <aside class="radio-player-side radio-player-side--dock">
                    <div class="player-expanded-chart-head">
                        <div class="radio-player-tabs radio-player-tabs--dock">
                            <button type="button" :class="{ 'is-active': activeTab === 'lyrics' }" @click="setTab('lyrics')">Letra</button>
                            <button type="button" :class="{ 'is-active': activeTab === 'band' }" @click="setTab('band')">Banda</button>
                            <button type="button" :class="{ 'is-active': activeTab === 'program' }" @click="setTab('program')">Programa</button>
                            <button type="button" :class="{ 'is-active': activeTab === 'history' }" @click="setTab('history')">Historial</button>
                        </div>
                        <button type="button" class="radio-player-chip radio-player-chip--ghost player-expanded-popup-button" @click="openPopout()">Popup</button>
                    </div>

                    <div class="radio-player-tab-body radio-player-tab-body--dock">
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
                                <div class="radio-player-queue radio-player-queue--dock">
                                    <h4>A continuación</h4>
                                    <div class="radio-player-queue-grid radio-player-queue-grid--dock">
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
                            <!-- RadioBOSS Cloud Recent Tracks Widget (Start) -->
                            <div class='rbcloud_recenttracks' id='rbcloud_recent378' data-cnt='7'>
                                <div class='rbcloud_recent_track' style='display: flex; align-items: center; margin-bottom: 5pt;'>
                                    <div class='rbcloud_recent_track_cover' data-size='65'></div>
                                    <div style='margin-left: 5pt;'><div class='rbcloud_recent_artist' style='font-weight: bold'></div><div class='rbcloud_recent_title'>...</div></div>
                                </div>
                            </div>
                            <script src='https://c30.radioboss.fm/w/recent.js?u=569&amp;wid=378&amp;v=2&amp;tf=1'></script>
                            <!-- RadioBOSS Cloud Recent Tracks Widget (End) -->
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
