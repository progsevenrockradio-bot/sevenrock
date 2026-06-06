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
                        <button type="button" class="radio-player-popup-chip" @click="shareCurrent()">Share</button>
                        <button type="button" class="radio-player-popup-chip" @click="window.close()">Cerrar</button>
                    </div>
                </header>

                @if ($socialLinks->isNotEmpty())
                    <div class="radio-player-popup-socials">
                        <span class="radio-player-popup-socials-label">Síguenos</span>
                        @foreach ($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="radio-player-popup-social-link" aria-label="Seguir en {{ $social['label'] }}">
                                <span class="radio-player-popup-social-badge">{{ $social['badge'] }}</span>
                                <span class="radio-player-popup-social-label">{{ $social['label'] }}</span>
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
                                <div class="radio-player-popup-wavebars">
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
                            <div class="radio-player-popup-time-action">
                                <button type="button" class="radio-player-popup-control radio-player-popup-fav-button" @click="toggleFavorite()" :aria-label="isFavoriteCurrent() ? 'Quitar de favoritos' : 'Me gusta'" :aria-pressed="isFavoriteCurrent()">
                                    <span x-text="isFavoriteCurrent() ? '♥' : '♡'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="radio-player-popup-progress">
                            <progress class="radio-player-popup-progress-meter" :value="progress.ratio" max="100" @click="seek($event)" aria-label="Progreso"></progress>
                        </div>

                        <template x-if="showNextTrackWidget">
                            <x-radio.next-track-widget variant="popup" />
                        </template>
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
                            <template x-if="history.length">
                                <div class="radio-player-popup-stack">
                                    <template x-for="item in history" :key="`${item.title}-${item.played_at}`">
                                        <article class="radio-player-popup-history-card">
                                            <img :src="item.cover || fallbackCover" alt="" loading="lazy" class="radio-player-popup-history-cover">
                                            <div class="radio-player-popup-history-copy">
                                                <strong class="radio-player-popup-card-title" x-text="item.title"></strong>
                                                <p class="radio-player-popup-card-text" x-text="item.artist || defaultArtist"></p>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </template>
                            <p x-show="!history.length" class="radio-player-popup-section-text">Sin historial todavía.</p>
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
                <div class="radio-player-share-popout radio-player-dock-share-popout-desktop" x-show="!dockMinimized" x-cloak>
                    <button type="button" class="radio-player-chip radio-player-dock-share-button" @click="shareCurrent()">Share</button>
                    <button type="button" class="radio-player-chip radio-player-dock-share-button" @click="openPopout()">Popup</button>
                </div>
                <div class="radio-player-mobile-share-popout" aria-label="Acciones móviles extra" x-show="!dockMinimized" x-cloak>
                    <button type="button" class="radio-player-chip radio-player-mobile-share" @click="shareCurrent()">Share</button>
                    <button type="button" class="radio-player-chip radio-player-mobile-popout" @click="openPopout()">Pop-out</button>
                </div>
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
                <button type="button" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" class="radio-player-dock-icon radio-player-dock-icon--favorite">
                    <span data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                </button>
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
                                        <p data-player-band-info class="radio-modal-text" x-text="biographyExpanded || 'No hay una biografía ampliada disponible para esta banda.'"></p>

                                        <div x-show="bandWindowView.logo || bandLinks().length" class="radio-modal-links">
                                            <template x-for="link in bandLinks()" :key="link.url">
                                                <a :href="link.url" target="_blank" rel="noopener" class="radio-modal-link" x-text="link.label"></a>
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
                <button type="button" class="radio-modal-close-btn radio-modal-close-btn--program" @click="closeProgramWindow()" aria-label="Cerrar">×</button>

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
                            <img class="radio-modal-cover radio-modal-cover--program" :src="programInfo.cover || fallbackCover" :alt="programInfo.name || ''" onerror="this.src=fallbackCover; this.onerror=null;" loading="lazy">
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
                                    <a x-show="programInfo.social_links?.facebook" :href="programInfo.social_links.facebook" target="_blank" rel="noopener" class="radio-modal-program-social">Facebook</a>
                                    <a x-show="programInfo.social_links?.instagram" :href="programInfo.social_links.instagram" target="_blank" rel="noopener" class="radio-modal-program-social">Instagram</a>
                                </div>
                            </div>
                        </div>

                        <div class="radio-modal-divider"></div>

                        <div class="radio-modal-body radio-modal-body--program">
                            <template x-if="programInfo.episode && (programInfo.episode.guest_bio || programInfo.episode.guest_image)">
                                <div>
                                    <div x-show="programInfo.episode.guest_image" class="radio-modal-guest-image-wrap">
                                        <img class="radio-modal-guest-image" :src="programInfo.episode.guest_image" :alt="programInfo.episode.title || ''" onerror="this.hidden = true; this.onerror = null;" loading="lazy">
                                    </div>
                                    <h3 class="radio-modal-episode-title" x-text="programInfo.episode.title || ''"></h3>
                                    <p class="radio-modal-episode-text" x-text="programInfo.episode.guest_bio || ''"></p>
                                    <small x-show="programInfo.episode.episode_number" class="radio-modal-episode-meta" x-text="'Episodio ' + programInfo.episode.episode_number"></small>
                                </div>
                            </template>

                            <template x-if="!(programInfo.episode && (programInfo.episode.guest_bio || programInfo.episode.guest_image))">
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

                            <template x-if="showNextTrackWidget">
                                <x-radio.next-track-widget variant="dock" />
                            </template>

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
                                <button type="button" class="player-expanded-control" data-player-action="favorite" @click="toggleFavorite()" aria-label="Like o favorito" :aria-pressed="isFavoriteCurrent()" title="Me gusta">
                                    <span class="player-expanded-control__icon" data-player-favorite-icon x-text="isFavoriteCurrent() ? '♥' : '♡'">♡</span>
                                </button>
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
</div>
