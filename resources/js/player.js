export function registerRadioPlayer(Alpine) {
    const safeRead = (key, fallback) => {
        try {
            return localStorage.getItem(key) ?? fallback;
        } catch (error) {
            return fallback;
        }
    };

    const safeWrite = (key, value) => {
        try {
            localStorage.setItem(key, value);
        } catch (error) {
            return;
        }
    };

    const readFavoritesCache = () => {
        try {
            const raw = safeRead('sr-player-favorites', '[]');
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed)
                ? parsed.filter((item) => item && item.signature)
                : [];
        } catch (error) {
            return [];
        }
    };

    const writeFavoritesCache = (favorites) => {
        try {
            safeWrite('sr-player-favorites', JSON.stringify((Array.isArray(favorites) ? favorites : []).slice(-50)));
        } catch (error) {
            // ignore
        }
    };

    const toAbsoluteUrl = (url) => {
        if (!url) return '';
        try {
            return new URL(url, window.location.href).href;
        } catch (_) {
            return url;
        }
    };

    const isPlaceholderImage = (url) => {
        if (!url) return true;
        // Do not treat RadioBOSS artwork as a placeholder, because RadioBOSS Cloud
        // serves the actual track cover art at this URL when available, and only
        // serves the station logo as a fallback when no track cover is found.
        return false;
    };

    const isFallbackImage = (url, fallbackCover, logoUrl) => {
        if (!url) return true;
        try {
            const lowerUrl = url.toLowerCase();
            if (fallbackCover && lowerUrl.includes(fallbackCover.toLowerCase())) return true;
            if (logoUrl && lowerUrl.includes(logoUrl.toLowerCase())) return true;
            if (lowerUrl.includes('logo.png')) return true;
            if (lowerUrl.includes('album3.jpg')) return true;
            if (lowerUrl.includes('current-cover.jpg')) return true;
            if (lowerUrl.includes('stationlogo')) return true;
            if (lowerUrl.includes('station_logo')) return true;
        } catch (e) {
            // safe catch
        }
        return false;
    };

    Alpine.data('radioPlayer', (options = {}) => ({
        mode: options.mode || 'dock',
        statusUrl: options.statusUrl || '/api/player/status',
        streamUrl: options.streamUrl || '',
        altStreamUrl: options.altStreamUrl || '',
        listenUrl: options.listenUrl || '',
        playlistM3u: options.playlistM3u || '',
        playlistPls: options.playlistPls || '',
        popupUrl: options.popupUrl || '/player/popup',
        bandInfoUrl: options.bandInfoUrl || '/api/player/band-info',
        programInfoUrl: options.programInfoUrl || '/api/player/program-info',
        favoritesUrl: options.favoritesUrl || '/api/player/favorites',
        favoritesToggleUrl: options.favoritesToggleUrl || '/api/player/favorites/toggle',
        favoritesImportUrl: options.favoritesImportUrl || '/api/player/favorites/import',
        fallbackCover: options.fallbackCover || '',
        logoUrl: options.logoUrl || '/assets/lucille/logo.png',
        cleanCover(url, customFallback = null, cacheBuster = null) {
            if (isPlaceholderImage(url)) {
                return customFallback || this.logoUrl;
            }
            if (url && (url.includes('radioboss.fm/') || url.includes('/w/artwork/'))) {
                const cleanUrl = url.split('?')[0];
                const buster = cacheBuster || this.track?.signature || Date.now();
                return `${cleanUrl}?v=${encodeURIComponent(buster)}`;
            }
            return url || customFallback || this.logoUrl;
        },
        pollInterval: Number(options.pollInterval || 5),
        nextTrackThresholdSeconds: Number(options.nextTrackThresholdSeconds || 20),
        historyLimit: Number(options.historyLimit || 10),
        defaultArtist: options.defaultArtist || '',
        defaultTitle: options.defaultTitle || '',
        panelOpen: false,
        sharePanelOpen: false,
        shareKey: '',
        bandWindowOpen: false,
        programWindowOpen: false,
        bandWindowTab: 'bio',
        bandWindowSnapshot: null,
        dockMinimized: true,
        dockVisible: false,
        activeTab: 'lyrics',
        userHasChosenTab: false,
        playing: false,
        loading: true,
        bandInfoLoading: false,
        programInfoLoading: false,
        programInfo: null,
        isMobile: window.innerWidth < 640,
        muted: safeRead('sr-player-muted', '0') === '1',
        volume: Number(safeRead('sr-player-volume', '0.8')) || 0.8,
        favorites: readFavoritesCache(),
        favoriteCount: 0,
        favoriteSyncInFlight: false,
        favoriteSyncReady: false,
        fixedDuration: 0,
        currentTime: 0,
        syncCounter: 0,
        timeSample: null,
        progress: {
            elapsed: 0,
            duration: 0,
            ratio: 0,
        },
        track: {
            title: '',
            artist: '',
            cover: '',
            lyrics: '',
            band_info: '',
            band_biography: '',
            band_biography_source: '',
            band_thumbnail: '',
            comment: '',
            band_members: [],
            social_links: [],
            audio_url: '',
            is_live: true,
            signature: '',
            program_name: '',
            program_description: '',
            program_host: '',
            program_schedule: '',
            program_id: null,
            es_bloque_programa: false,
        },
        program: null,
        nextProgram: null,
        nextTrack: null,
        queue: [],
        notices: [],
        history: [],
        historyStale: false,
        historyExpanded: false,
        lastAppliedStatusTimestamp: 0,
        lastIsProgramState: null,
        listeners: 0,
        metaSource: 'default',
        metaSources: {},
        metaUpdatedAt: null,
        coverIsFallback: false,
        bandLookupArtist: '',
        bandPanel: {
            title: '',
            artist: '',
            info: '',
            biography: '',
            biographySource: '',
            cover: '',
            foundedLabel: '',
            facts: [],
        },
        streamCandidates: [],
        currentStreamIndex: 0,
        widgetIds: {
            root: 'rbcloud_nowplaying15715',
            cover: 'rbcloud_cover8795',
            timerElapsed: 'rbcloud_tracktimer_e9597',
            timerSeparator: 'rbcloud_tracktimer_sep9597',
            timerRemaining: 'rbcloud_tracktimer_r9597',
        },
        toast: {
            visible: false,
            message: '',
        },
        interactionReady: false,
        pollHandle: null,
        progressHandle: null,
        boundHotkeys: null,
        widgetObserver: null,
        widgetSyncHandle: null,
        widgetRetryHandle: null,
        toastHandle: null,
        statusInFlight: false,
        statusSyncHandle: null,
        viewportResizeHandle: null,

        init() {
            this.panelOpen = this.mode === 'page' ? true : false;
            this.sharePanelOpen = false;
            this.bandWindowOpen = false;
            this.bandWindowTab = 'bio';
            this.dockMinimized = true;
            this.dockVisible = false;
            this.activeTab = safeRead('sr-player-tab', 'lyrics');
            this.userHasChosenTab = Boolean(safeRead('sr-player-tab', null));
            this.isMobile = window.innerWidth < 640;
            this.toast.visible = false;
            this.toast.message = '';
            this.closeTransientOverlays();
            this.applyAudioPreferences();
            this.bindAudioEvents();
            this.bindNavigationGuards();
            this.viewportResizeHandle = () => {
                this.isMobile = window.innerWidth < 640;
            };
            window.addEventListener('resize', this.viewportResizeHandle);
            this.$nextTick(() => {
                this.closeTransientOverlays();
                window.setTimeout(() => {
                    this.interactionReady = true;
                }, 250);
            });
            window.addEventListener('sr-force-close-modals', () => {
                this.closeTransientOverlays();
            }, { once: true });

            window.MusicPlayer = this;

            if (this.mode === 'dock') {
                // En la página de simulación y pruebas, mostrar el dock de inmediato
                if (typeof window !== 'undefined' && window.location && window.location.pathname.includes('dev/player-scenario')) {
                    this.dockVisible = true;
                }
            }

            // Activar visibilidad al reproducir, pero mantener el dock abierto al pausar o ante errores.
            this.$watch('playing', (isPlaying) => {
                if (this.mode === 'dock' && isPlaying) {
                    this.dockVisible = true;
                }
            });

            // Listen for multimedia hub track requests
            window.addEventListener('play-multimedia-track', (e) => {
                this.handleMultimediaTrack(e.detail);
            });

            // Pause live stream when a podcast/local track is played on any page
            window.addEventListener('sr-podcast-playing', () => {
                if (this.playing) {
                    this.playing = false;
                    this.loading = false;
                    const audio = this.$refs?.audio;
                    if (audio) audio.pause();
                }
            });

            this.watchNowPlayingWidget();
            this.queueStatusRefresh(0);

            this.startPolling();
            this.progressHandle = setInterval(() => this.tickProgress(), 1000);

            this.boundHotkeys = (event) => this.handleHotkeys(event);
            window.addEventListener('keydown', this.boundHotkeys);

            // Control de visibilidad de pestaña para optimizar consumo y sondeos en segundo plano
            this.boundVisibilityChange = () => this.handleVisibilityChange();
            document.addEventListener('visibilitychange', this.boundVisibilityChange);

            // Sincronización multi-ventana (Popup y Dock) mediante BroadcastChannel y StorageEvent
            this.syncChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('sevenrock_player_sync') : null;
            if (this.syncChannel) {
                this.syncChannel.onmessage = (e) => {
                    if (e.data === 'refresh') {
                        void this.refreshStatus(true, false, true);
                    }
                };
            }
            this.storageSyncListener = (e) => {
                if (e.key === 'sevenrock_player_refresh_trigger') {
                    void this.refreshStatus(true, false, true);
                }
            };
            window.addEventListener('storage', this.storageSyncListener);

            // Media Session API integration
            this.$watch('track', (value) => this.updateMediaSession(value));
            this.$watch('playing', (value) => this.updateMediaSessionPlaybackState(value));
            this.$watch('track.signature', () => this.updateShareKey());
            this.updateMediaSession(this.track);
            this.updateMediaSessionPlaybackState(this.playing);
            this.updateShareKey();
        },

        hasUserGesture() {
            return Boolean(window.__srUserGesture);
        },

        destroy() {
            if (this.pollHandle) {
                clearTimeout(this.pollHandle);
                clearInterval(this.pollHandle);
            }
            if (this.progressHandle) {
                clearInterval(this.progressHandle);
            }
            if (this.widgetObserver) {
                this.widgetObserver.disconnect();
            }
            if (this.widgetRetryHandle) {
                clearTimeout(this.widgetRetryHandle);
            }
            if (this.widgetSyncHandle) {
                clearTimeout(this.widgetSyncHandle);
            }
            if (this.boundHotkeys) {
                window.removeEventListener('keydown', this.boundHotkeys);
            }
            if (this.boundVisibilityChange) {
                document.removeEventListener('visibilitychange', this.boundVisibilityChange);
            }
            if (this.syncChannel) {
                this.syncChannel.close();
            }
            if (this.storageSyncListener) {
                window.removeEventListener('storage', this.storageSyncListener);
            }
            if (this.toastHandle) {
                clearTimeout(this.toastHandle);
            }
            if (this.statusSyncHandle) {
                clearTimeout(this.statusSyncHandle);
            }
            if (this.navigationGuard) {
                document.removeEventListener('click', this.navigationGuard, true);
            }
            if (this.pageShowGuard) {
                window.removeEventListener('pageshow', this.pageShowGuard);
            }
            if (this.beforeUnloadGuard) {
                window.removeEventListener('beforeunload', this.beforeUnloadGuard);
            }
            if (this.viewportResizeHandle) {
                window.removeEventListener('resize', this.viewportResizeHandle);
            }
        },

        handleVisibilityChange() {
            if (document.hidden) {
                // En segundo plano: si no reproduce, pausa sondeos; si reproduce, extiende intervalo a 30s
                if (!this.playing) {
                    if (this.pollHandle) {
                        clearTimeout(this.pollHandle);
                        this.pollHandle = null;
                    }
                } else {
                    this.adjustPollingInterval();
                }
            } else {
                // Al volver a primer plano: sincroniza de inmediato y restablece cadencia activa
                this.statusFailureCount = 0;
                void this.refreshStatus(true);
                this.adjustPollingInterval();
            }
        },

        startPolling() {
            if (this.pollHandle) {
                clearTimeout(this.pollHandle);
            }
            const run = async () => {
                await this.refreshStatus(true);
                let baseInterval = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
                if (document.hidden) {
                    baseInterval = this.playing ? 30000 : 60000;
                }
                const backoff = (this.statusFailureCount && this.statusFailureCount > 0)
                    ? Math.min(60000, baseInterval * Math.pow(1.5, Math.min(4, this.statusFailureCount)))
                    : baseInterval;
                this.pollHandle = setTimeout(run, backoff);
            };
            const initialDelay = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
            this.pollHandle = setTimeout(run, initialDelay);
        },

        adjustPollingInterval() {
            if (this.pollHandle) {
                clearTimeout(this.pollHandle);
            }
            const run = async () => {
                await this.refreshStatus(true);
                let baseInterval = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
                if (document.hidden) {
                    baseInterval = this.playing ? 30000 : 60000;
                }
                const backoff = (this.statusFailureCount && this.statusFailureCount > 0)
                    ? Math.min(60000, baseInterval * Math.pow(1.5, Math.min(4, this.statusFailureCount)))
                    : baseInterval;
                this.pollHandle = setTimeout(run, backoff);
            };
            const initialDelay = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
            this.pollHandle = setTimeout(run, initialDelay);
        },

        updateDocumentTitle() {
            if (this.mode !== 'popup') {
                return;
            }
            const artist = this.track.artist || this.defaultArtist;
            const title = this.track.title || this.defaultTitle;
            const prefix = this.playing ? '▶ ' : '';
            const brand = 'Seven Rock Radio';
            if (artist && title) {
                document.title = `${prefix}${artist} - ${title} | ${brand}`;
            } else {
                document.title = `${prefix}${brand}`;
            }
        },

        bindNavigationGuards() {
            this.navigationGuard = (event) => {
                const anchor = event.target?.closest?.('a[href]');
                if (!anchor) {
                    return;
                }

                if (anchor.target && anchor.target !== '_self') {
                    return;
                }

                const href = anchor.getAttribute('href') || '';
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

                try {
                    const url = new URL(href, window.location.href);
                    if (url.origin !== window.location.origin) {
                        return;
                    }
                } catch (error) {
                    return;
                }

                this.closeTransientOverlays();
            };

            this.pageShowGuard = (event) => {
                this.closeTransientOverlays();
            };

            this.beforeUnloadGuard = () => {
                this.closeTransientOverlays();
            };

            document.addEventListener('click', this.navigationGuard, true);
            window.addEventListener('pageshow', this.pageShowGuard);
            window.addEventListener('beforeunload', this.beforeUnloadGuard);
        },

        closeTransientOverlays() {
            this.closeBandWindow();
            this.closeProgramWindow();
            this.sharePanelOpen = false;
            if (this.mode !== 'page') {
                this.panelOpen = false;
            }
        },

        applyAudioPreferences() {
            this.$nextTick(() => {
                const audio = this.$refs?.audio;
                if (!audio) {
                    return;
                }

                audio.volume = this.volume;
                audio.muted = this.muted;
                audio.preload = 'none';
                this.streamCandidates = [this.streamUrl, this.altStreamUrl, '/assets/dev/audio/sample.mp3'].filter((s) => typeof s === 'string' && s.trim() !== '');
            });
        },

        get playbackSource() {
            return this.track.audio_url || this.streamUrl;
        },

        bindAudioEvents() {
            this.$nextTick(() => {
                const audio = this.$refs?.audio;
                if (!audio) {
                    return;
                }

                const syncFromAudio = () => {
                    if (this.track && this.track.is_live) {
                        // En directo, audio.currentTime mide el tiempo de conexión al stream HTTP,
                        // NO la posición de la canción. La posición de directo la gobierna RadioBOSS.
                        return;
                    }

                    const duration = Number.isFinite(audio.duration) && audio.duration > 0
                        ? Math.round(audio.duration)
                        : 0;
                    const elapsed = Number.isFinite(audio.currentTime) && audio.currentTime >= 0
                        ? Math.round(audio.currentTime)
                        : 0;

                    if (duration > 0) {
                        this.progress.duration = duration;
                        this.fixedDuration = duration;
                    }

                    this.progress.elapsed = elapsed;
                    this.currentTime = elapsed;
                    this.syncProgress();
                };

                audio.addEventListener('play', () => {
                    this.playing = true;
                    this.updateDocumentTitle();
                    this.adjustPollingInterval();
                    this.updateMediaSession(this.track);
                    this.updateMediaSessionPlaybackState(true);
                });

                audio.addEventListener('playing', () => {
                    this.loading = false;
                    const timeToStart = this._listenClickTimestamp ? Math.round(performance.now() - this._listenClickTimestamp) : 0;
                    this._listenClickTimestamp = null;
                    if (typeof window !== 'undefined' && window.SevenRockAnalytics) {
                        window.SevenRockAnalytics.track('playback_started', {
                            stream_type: (this.track && !this.track.is_live) ? 'on_demand' : 'live',
                            time_to_start_ms: timeToStart,
                        });
                    }
                });

                audio.addEventListener('pause', () => {
                    this.playing = false;
                    this.updateDocumentTitle();
                    this.adjustPollingInterval();
                    this.updateMediaSessionPlaybackState(false);
                });

                audio.addEventListener('waiting', () => {
                    this.loading = true;
                });

                audio.addEventListener('canplay', () => {
                    this.loading = false;
                    syncFromAudio();
                });

                audio.addEventListener('loadedmetadata', () => {
                    syncFromAudio();
                });

                audio.addEventListener('durationchange', () => {
                    syncFromAudio();
                });

                audio.addEventListener('timeupdate', () => {
                    syncFromAudio();
                });

                audio.addEventListener('error', () => {
                    if (typeof window !== 'undefined' && window.SevenRockAnalytics) {
                        window.SevenRockAnalytics.track('playback_error', {
                            stream_type: (this.track && !this.track.is_live) ? 'on_demand' : 'live',
                            error_code: audio.error ? audio.error.code : 'stream_error',
                        });
                    }
                    if (this.currentStreamIndex + 1 < this.streamCandidates.length) {
                        this.currentStreamIndex += 1;
                        this.ensureAudioSource();
                        if (this.playing) {
                            audio.play().catch(() => {
                                this.playing = false;
                            });
                        }
                    }
                });
            });
        },

        updateVolume() {
            const audio = this.$refs?.audio;
            if (audio) {
                audio.volume = this.volume;
            }

            safeWrite('sr-player-volume', String(this.volume));

            if (this.volume > 0 && this.muted) {
                this.muted = false;
                if (audio) {
                    audio.muted = false;
                }
                safeWrite('sr-player-muted', '0');
            }
        },

        toggleMute() {
            this.muted = !this.muted;

            const audio = this.$refs?.audio;
            if (audio) {
                audio.muted = this.muted;
            }

            safeWrite('sr-player-muted', this.muted ? '1' : '0');
        },

        ensureAudioSource(forceReload = false) {
            const audio = this.$refs?.audio;
            if (!audio) {
                return;
            }

            const source = (this.track && (this.track.is_live || !this.track.audio_url))
                ? (this.streamCandidates[this.currentStreamIndex] || this.streamUrl)
                : (this.track?.audio_url || this.playbackSource || this.streamUrl);
            const currentSource = audio.currentSrc || (typeof audio.getAttribute === 'function' ? audio.getAttribute('src') : (audio.src || '')) || '';

            const absSource = toAbsoluteUrl(source);
            const absCurrent = toAbsoluteUrl(currentSource);

            if (forceReload || (absSource && absCurrent !== absSource) || audio.error) {
                const wasPlaying = this.playing;
                audio.pause();
                audio.removeAttribute('src');
                audio.src = source;
                audio.load();
                if (wasPlaying) {
                    audio.play().catch(() => {
                        this.playing = false;
                    });
                }
            }

            audio.volume = this.volume;
            audio.muted = this.muted;
        },

        async attemptPlayWithFallback() {
            const audio = this.$refs?.audio;
            if (!audio) {
                return;
            }

            if (this.playing) {
                this.playing = false;
                this.loading = false;
                audio.pause();
                this.toastMessage('Reproductor en pausa');
                return;
            }

            this._listenClickTimestamp = performance.now();
            if (typeof window !== 'undefined' && window.SevenRockAnalytics) {
                window.SevenRockAnalytics.track('listen_click', {
                    source: this.mode || 'dock',
                    stream_type: (this.track && !this.track.is_live) ? 'on_demand' : 'live',
                });
            }

            // Al reanudar en directo: conectar al borde en vivo y purgar buffer congelado
            this.ensureAudioSource(Boolean(this.track && this.track.is_live));

            document.querySelectorAll('audio').forEach((el) => {
                if (el !== audio) {
                    try { el.pause(); } catch (_) { }
                }
            });

            // Si es un audio bajo demanda (podcast o muestra local), reproducir directamente
            if (this.track && !this.track.is_live && this.track.audio_url) {
                try {
                    this.loading = true;
                    this.playing = true;
                    this.dockVisible = true;
                    await audio.play();
                    this.loading = false;
                    this.toastMessage('Reproduciendo archivo');
                    return;
                } catch (err) {
                    this.playing = false;
                    this.loading = false;
                    this.dockVisible = true;
                    let msg = 'No se pudo reproducir el archivo (' + (err?.name || 'Error') + ')';
                    if (err?.name === 'NotAllowedError') {
                        msg = 'Haz clic para permitir la reproducción de audio';
                    }
                    this.toastMessage(msg);
                    return;
                }
            }

            const candidates = (Array.isArray(this.streamCandidates) && this.streamCandidates.length > 0)
                ? this.streamCandidates
                : [this.streamUrl, this.altStreamUrl, '/assets/dev/audio/sample.mp3'].filter(Boolean);

            let lastError = null;

            for (let i = 0; i < candidates.length; i++) {
                this.currentStreamIndex = i;
                this.ensureAudioSource(true);
                try {
                    this.loading = true;
                    this.playing = true;
                    this.dockVisible = true;
                    window.dispatchEvent(new CustomEvent('sr-live-playing'));
                    await audio.play();
                    this.loading = false;
                    if (this.track && this.track.is_live) {
                        this.queueStatusRefresh(0);
                    }
                    this.toastMessage('Reproduciendo emisión en directo');
                    return;
                } catch (err) {
                    lastError = err;
                }
            }

            this.playing = false;
            this.loading = false;
            // Mantener el dock abierto ante errores para que el usuario vea el estado y reintente
            this.dockVisible = true;

            let errorMessage = 'No se pudo conectar a la emisión (' + (lastError?.name || 'Error') + ')';
            if (audio.src && audio.src.includes('archive.org/details/')) {
                errorMessage = 'Error: Enlace de archive.org incorrecto';
            } else if (lastError?.name === 'NotSupportedError') {
                errorMessage = 'Formato de audio no soportado o stream fuera de línea';
            } else if (lastError?.name === 'NotAllowedError') {
                errorMessage = 'Haz clic para permitir la reproducción de audio';
            } else if (lastError?.message) {
                errorMessage = 'Error: ' + lastError.message;
            }

            if (typeof this.toastMessage === 'function') {
                this.toastMessage(errorMessage);
            }
        },

        togglePlay() {
            return this.attemptPlayWithFallback();
        },

        startLiveFromFAB() {
            this.track.is_live = true;
            this.track.audio_url = '';
            this.streamCandidates = [this.streamUrl, this.altStreamUrl, 'https://radios.mipanel.stream:6796/;', '/assets/dev/audio/sample.mp3'].filter(Boolean);
            this.currentStreamIndex = 0;
            this.dockVisible = true;
            this.dockMinimized = false;
            this.ensureAudioSource(true);
            this.queueStatusRefresh(0);
            if (!this.playing) {
                this.attemptPlayWithFallback();
            }
        },

        hideDock() {
            const audio = this.$refs?.audio;
            if (audio) {
                audio.pause();
            }
            this.playing = false;
            this.dockVisible = false;
            this.panelOpen = false;
            this.closeTransientOverlays();
        },

        handleMultimediaTrack(detail) {
            if (!detail) return;
            // If it's a live stream request
            if (detail.src === 'live' || detail.type === 'live') {
                window.dispatchEvent(new CustomEvent('sr-catalog-stopped'));
                this.startLiveFromFAB();
                return;
            }

            const srcStr = String(detail.src || '').trim();
            const hasValidAudio = srcStr !== '' && !srcStr.includes('youtube.com') && !srcStr.includes('youtu.be');

            // For podcasts/songs from multimedia hub
            this.dockVisible = true;
            this.dockMinimized = false;

            const audio = this.$refs?.audio;
            if (audio && hasValidAudio) {
                audio.pause();
            }

            this.track = {
                title: detail.title || '',
                artist: detail.subtitle || '',
                cover: detail.image || this.fallbackCover || this.logoUrl,
                lyrics: detail.lyrics || '',
                band_info: detail.bandInfo || '',
                band_biography: detail.bandInfo || '',
                band_biography_source: '',
                band_thumbnail: detail.image || '',
                comment: '',
                band_members: detail.bandMembers || [],
                social_links: detail.socialLinks || [],
                audio_url: hasValidAudio ? srcStr : '',
                is_live: false,
                signature: srcStr || Date.now().toString(),
                program_name: detail.type === 'podcast' ? detail.subtitle : '',
                program_description: detail.bandInfo || '',
                program_host: '',
                program_schedule: '',
                program_id: null,
                es_bloque_programa: false,
            };

            if (hasValidAudio) {
                // Notify the catalog list which song is now active
                if (detail.type === 'song') {
                    window.dispatchEvent(new CustomEvent('sr-catalog-playing', { detail: { audioUrl: srcStr } }));
                }

                this.streamCandidates = [srcStr].filter(Boolean);
                this.currentStreamIndex = 0;

                // Force play instead of toggling pause if something was already playing
                this.playing = false;

                this.attemptPlayWithFallback();

                // Open info window automatically to show details
                setTimeout(() => {
                    if (this.interactionReady) {
                        this.toggleInfoWindow({ isTrusted: true });
                    }
                }, 300);
            } else {
                if (audio) audio.pause();
                this.playing = false;
                this.loading = false;

                if (typeof this.toastMessage === 'function') {
                    this.toastMessage('Audio no disponible. Mostrando info de banda.');
                }

                // Open info window automatically to show links/bio
                setTimeout(() => {
                    if (this.interactionReady) {
                        this.toggleInfoWindow({ isTrusted: true });
                    }
                }, 400);
            }
        },

        togglePanel() {
            if (this.mode === 'page') {
                return;
            }

            if (!this.interactionReady) {
                return;
            }

            if (this.mode === 'popup') {
                this.panelOpen = !this.panelOpen;
                safeWrite('sr-player-expanded', this.panelOpen ? '1' : '0');
                return;
            }

            if (this.bandWindowOpen || this.programWindowOpen) {
                this.closeBandWindow();
                this.closeProgramWindow();
                return;
            }

            this.openBandWindow();
        },

        toggleInfoWindow(event = null) {
            const isTrusted = event && event.isTrusted;
            if (!this.interactionReady || (!isTrusted && !this.hasUserGesture())) {
                return;
            }

            const esBloqueDePrograma = Boolean(this.track.es_bloque_programa);

            if (esBloqueDePrograma) {
                if (this.programWindowOpen) {
                    this.closeProgramWindow();
                    return;
                }

                this.closeBandWindow();
                this.openProgramWindow(this.track.program_id);
                return;
            }

            if (this.bandWindowOpen) {
                this.closeBandWindow();
                return;
            }

            this.closeProgramWindow();
            this.openBandWindow();
        },

        toggleProgramWindow() {
            if (!this.interactionReady) {
                return;
            }
            if (this.programWindowOpen) {
                this.closeProgramWindow();
                return;
            }

            this.closeBandWindow();
            const progId = this.program?.id || this.track?.program_id || this.nextProgram?.id || null;
            void this.openProgramWindow(progId);
        },

        hasProgramAvailable() {
            if (this.mode === 'dock') {
                return Boolean(this.track?.es_bloque_programa || this.track?.es_programa_anticipo);
            }
            return Boolean(
                this.track?.es_bloque_programa ||
                this.track?.es_programa_anticipo ||
                (this.program && (this.program.id || this.program.name)) ||
                (this.nextProgram && (this.nextProgram.id || this.nextProgram.name)) ||
                (this.track && (this.track.program_id || this.track.program_name))
            );
        },

        hasRealSong() {
            const title = (this.track?.title || '').trim();
            const artist = (this.track?.artist || '').trim();
            const defaultTitle = (this.defaultTitle || '').trim();
            if (!title || !artist) {
                return false;
            }
            if (defaultTitle && title.toLowerCase() === defaultTitle.toLowerCase()) {
                return false;
            }
            const lower = title.toLowerCase();
            if (lower === 'transmisión oficial' || lower === 'transmision oficial' || lower === 'seven rock radio') {
                return false;
            }
            if (artist.toLowerCase() === (this.defaultArtist || '').trim().toLowerCase() || artist.toLowerCase() === 'en vivo') {
                return false;
            }
            return true;
        },

        async openBandWindow() {
            if (!this.interactionReady) {
                return;
            }

            this.bandInfoLoading = true;
            this.bandWindowOpen = true;
            this.bandWindowTab = 'bio';
            try {
                const sourceTrack = { ...this.track };
                try {
                    await this.ensureBandInfo(true, sourceTrack);
                } catch (error) {
                    // ignore band info lookup failures
                }

                if (!this.bandWindowOpen) {
                    return;
                }

                const snapshot = this.buildBandWindowSnapshot();
                await this.preloadBandWindowImages(snapshot);

                if (this.bandWindowOpen) {
                    this.bandWindowSnapshot = snapshot;
                    this.bandPanel = { ...snapshot };
                }
            } finally {
                this.bandInfoLoading = false;
            }
        },

        closeBandWindow() {
            this.bandWindowOpen = false;
            this.bandInfoLoading = false;
            this.bandWindowSnapshot = null;
        },

        async openProgramWindow(programId = null) {
            if (!this.interactionReady) {
                return;
            }

            this.programInfoLoading = true;
            this.programWindowOpen = true;
            this.programInfo = {
                id: this.track.program_id || null,
                name: this.track.program_name || '',
                description: this.track.program_description || '',
                host: this.track.program_host || '',
                schedule: this.track.program_schedule || '',
                cover: this.track.cover || this.fallbackCover,
                country: this.track.program_country || null,
                genre: '',
                social_links: {},
                episode: null,
            };

            try {
                const id = Number(programId || this.track.program_id || this.program?.id || 0);
                const name = this.track.program_name ? encodeURIComponent(this.track.program_name) : '';
                let url = this.programInfoUrl;
                const params = [];
                if (id > 0) params.push(`program_id=${id}`);
                if (name) params.push(`program_name=${name}`);
                if (params.length) url += (url.includes('?') ? '&' : '?') + params.join('&');

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (payload?.success && payload?.data) {
                    let nextCoverCandidate = String(payload.data.cover || '').trim();
                    if (isFallbackImage(nextCoverCandidate, this.fallbackCover, this.logoUrl)) {
                        nextCoverCandidate = '';
                    }

                    const finalCover = nextCoverCandidate || this.programInfo.cover || this.track.cover || this.fallbackCover;

                    const nextProgramInfo = {
                        ...this.programInfo,
                        ...payload.data,
                        country: payload.data.country || this.track.program_country || this.programInfo?.country || null,
                        cover: finalCover,
                        social_links: {
                            ...(this.programInfo?.social_links || {}),
                            ...(payload.data.social_links || {}),
                        },
                        episode: payload.data.episode ?? this.programInfo?.episode ?? null,
                    };

                    this.programInfo = nextProgramInfo;
                }
            } catch (error) {
                // ignore program info lookup failures
            } finally {
                this.programInfoLoading = false;
            }
        },

        closeProgramWindow() {
            this.programWindowOpen = false;
            this.programInfoLoading = false;
            this.programInfo = null;
        },

        setBandWindowTab(tab) {
            this.bandWindowTab = tab;
        },

        async preloadBandWindowImages(snapshot) {
            const urls = [snapshot?.bioCover, snapshot?.trackCover]
                .map((value) => String(value || '').trim())
                .filter(Boolean);

            if (urls.length === 0) {
                return;
            }

            await Promise.all(urls.map((url) => new Promise((resolve) => {
                const image = new Image();
                image.onload = () => resolve();
                image.onerror = () => resolve();
                image.src = url;
            })));
        },

        buildBandWindowSnapshot(sourceTrack = null) {
            const track = sourceTrack || this.track;

            let bioCoverCandidate = track.band_thumbnail;
            if (isFallbackImage(bioCoverCandidate, this.fallbackCover, this.logoUrl)) {
                bioCoverCandidate = '';
            }

            let panelCoverCandidate = this.bandPanel.cover;
            if (isFallbackImage(panelCoverCandidate, this.fallbackCover, this.logoUrl)) {
                panelCoverCandidate = '';
            }

            const effectiveCover = this.coverVisible && !isFallbackImage(this.coverVisible, this.fallbackCover, this.logoUrl)
                ? this.coverVisible
                : (track.cover && !isFallbackImage(track.cover, this.fallbackCover, this.logoUrl) ? track.cover : this.fallbackCover);

            const unifiedBandCover = bioCoverCandidate || panelCoverCandidate || effectiveCover;

            return {
                title: track.title || this.bandPanel.title || '',
                artist: track.artist || this.bandPanel.artist || '',
                info: track.band_info || this.bandPanel.info || track.comment || '',
                biography: track.band_biography || this.bandPanel.biography || '',
                biographySource: track.band_biography_source || this.bandPanel.biographySource || '',
                bioCover: unifiedBandCover,
                trackCover: unifiedBandCover,
                foundedLabel: track.band_founded_label || this.bandPanel.foundedLabel || '',
                facts: Array.isArray(track.band_facts) ? track.band_facts : (this.bandPanel.facts || []),
                logo: track.band_logo || this.bandPanel.logo || '',
                country: track.band_country || this.bandPanel.country || '',
                genre: track.band_genre || this.bandPanel.genre || '',
                membersCount: track.band_members_count ?? this.bandPanel.membersCount ?? null,
                status: track.band_status || this.bandPanel.status || '',
                labels: track.band_labels || this.bandPanel.labels || '',
                socialLinks: Array.isArray(track.social_links) ? track.social_links : [],
                bandMembers: Array.isArray(track.band_members) ? track.band_members : [],
                lyrics: typeof track.lyrics === 'string' ? track.lyrics : '',
            };
        },

        get bandWindowBioCover() {
            return this.cleanCover(this.bandWindowView.bioCover, this.logoUrl);
        },

        get bandWindowTrackCover() {
            return this.bandWindowBioCover;
        },

        get bandWindowView() {
            return this.bandWindowSnapshot || this.buildBandWindowSnapshot();
        },

        get biographySourceLabel() {
            return this.formatBiographySourceLabel(this.bandWindowView.biographySource || '');
        },

        get hasValidNextTrack() {
            if (!this.nextTrack || typeof this.nextTrack !== 'object') {
                return false;
            }
            const nextTitle = String(this.nextTrack.title || '').trim();
            const nextArtist = String(this.nextTrack.artist || '').trim();

            if (!nextTitle || nextTitle === this.defaultTitle) {
                return false;
            }

            // Comparar por IDENTIDAD COMPLETA (artista y título, o identificador fiable)
            // Dos artistas pueden interpretar canciones con el mismo nombre (ej: "Changes" o "Hallowed Be Thy Name")
            if (this.track) {
                // 1. Identificador fiable si ambos lo tienen
                if (this.track.id && this.nextTrack.id && String(this.track.id) === String(this.nextTrack.id)) {
                    return false;
                }

                const curTitle = this.normalizeTrackTitle(this.track.title || '').toLowerCase();
                const curArtist = this.normalizeTrackTitle(this.track.artist || '').toLowerCase();
                const nxtTitle = this.normalizeTrackTitle(nextTitle).toLowerCase();
                const nxtArtist = this.normalizeTrackTitle(nextArtist).toLowerCase();

                // Si los títulos coinciden
                if (curTitle && nxtTitle && curTitle === nxtTitle) {
                    if (curArtist && nxtArtist) {
                        // Coinciden título Y artista -> es la misma pista que ya comenzó, retirarla
                        if (curArtist === nxtArtist) {
                            return false;
                        }
                        // Artistas distintos con el mismo título -> canción diferente, es válida y se conserva
                    } else {
                        // Si falta el artista en alguno, se retira como medida de seguridad
                        return false;
                    }
                }
            }

            // Si coincide con el próximo programa, no confundirla con una canción
            if (this.nextProgram && this.nextProgram.name) {
                const nextProg = String(this.nextProgram.name).trim().toLowerCase();
                if (nextProg && nextProg === nextTitle.toLowerCase()) {
                    return false;
                }
            }
            return true;
        },

        get visibleHistory() {
            const list = Array.isArray(this.history) ? this.history : [];
            if (this.historyExpanded) {
                return list.slice(0, this.historyLimit || 10);
            }
            return list.slice(0, 3);
        },

        toggleHistoryExpanded() {
            this.historyExpanded = !this.historyExpanded;
        },

        formatPlayedAt(timestamp) {
            if (!timestamp) return '';
            try {
                const d = new Date(timestamp);
                if (isNaN(d.getTime())) return String(timestamp);
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (_) {
                return String(timestamp);
            }
        },

        get nextTrackRemainingSeconds() {
            if (this.track.is_live || this.progress.duration <= 0) {
                return Number.POSITIVE_INFINITY;
            }

            return Math.max(0, Math.round(this.progress.duration - this.progress.elapsed));
        },

        get showNextTrackWidget() {
            return this.hasValidNextTrack;
        },

        formatBiographySourceLabel(source) {
            switch (String(source || '').trim()) {
                case 'real':
                    return 'Biografía real';
                case 'summary':
                    return 'Resumen editorial';
                case 'fallback':
                    return 'Texto de respaldo';
                default:
                    return '';
            }
        },

        closePanel() {
            if (this.mode === 'popup') {
                this.panelOpen = false;
                safeWrite('sr-player-expanded', '0');
                return;
            }

            this.closeTransientOverlays();
            this.dockMinimized = !this.dockMinimized;
        },

        queueWidgetSync(delay = 900) {
            if (this.widgetSyncHandle) {
                clearTimeout(this.widgetSyncHandle);
            }

            this.widgetSyncHandle = setTimeout(() => {
                this.syncTrackFromWidget();
                if (this.needsBandEnrichment()) {
                    this.ensureBandInfo();
                }
            }, Math.max(0, Number(delay) || 0));
        },

        queueStatusRefresh(delay = 0) {
            if (this.statusSyncHandle) {
                clearTimeout(this.statusSyncHandle);
            }

            this.statusSyncHandle = setTimeout(() => {
                this.refreshStatus(true);
            }, Math.max(0, Number(delay) || 0));
        },

        watchNowPlayingWidget() {
            let retries = 0;
            const maxRetries = 10;
            const bind = () => {
                const root = document.getElementById(this.widgetIds.root);
                const cover = document.getElementById(this.widgetIds.cover);
                const timerElapsed = document.getElementById(this.widgetIds.timerElapsed);
                const timerRemaining = document.getElementById(this.widgetIds.timerRemaining);

                if (!root && !cover && !timerElapsed && !timerRemaining) {
                    if (this.widgetRetryHandle) {
                        clearTimeout(this.widgetRetryHandle);
                    }

                    if (++retries <= maxRetries) {
                        this.widgetRetryHandle = setTimeout(bind, 500);
                    } else {
                        this.widgetRetryHandle = null;
                    }
                    return;
                }

                if (this.widgetObserver) {
                    this.widgetObserver.disconnect();
                }

                this.widgetObserver = new MutationObserver(() => {
                    this.syncTrackFromWidget();
                });

                [root, cover]
                    .concat([timerElapsed, timerRemaining])
                    .filter(Boolean)
                    .forEach((element) => {
                        this.widgetObserver.observe(element, {
                            attributes: true,
                            attributeFilter: ['src', 'data-track', 'data-nowplaying'],
                            childList: true,
                            characterData: true,
                            subtree: true,
                        });
                    });

                if (this.widgetRetryHandle) {
                    clearTimeout(this.widgetRetryHandle);
                    this.widgetRetryHandle = null;
                }

                this.syncTrackFromWidget(true);
            };

            bind();
        },

        resolveWidgetTrack() {
            const widgetTrack = this.inferWidgetTrack(this.readNowPlayingWidget());

            if (!widgetTrack.title && !widgetTrack.artist && !widgetTrack.cover) {
                return null;
            }

            return {
                title: widgetTrack.title || '',
                artist: widgetTrack.artist || '',
                cover: widgetTrack.cover || '',
                elapsed: widgetTrack.elapsed || 0,
                duration: widgetTrack.duration || 0,
            };
        },

        isWidgetMatchingCurrentTrack(widgetTrack) {
            if (!widgetTrack || !this.track || this.track.es_bloque_programa || !this.track.is_live) {
                return false;
            }
            const cleanCurrentTitle = this.normalizeTrackTitle(this.track.title || '').toLowerCase();
            const cleanCurrentArtist = this.normalizeBandArtist(this.track.artist || '').toLowerCase();
            const cleanWidgetTitle = this.normalizeTrackTitle(widgetTrack.title || '').toLowerCase();
            const cleanWidgetArtist = this.normalizeBandArtist(widgetTrack.artist || '').toLowerCase();

            if (!cleanCurrentTitle || !cleanWidgetTitle) {
                return false;
            }

            // Validación estricta de identidad: no inferir pertenencia por similitud de duración
            const titleMatches = cleanCurrentTitle === cleanWidgetTitle;
            const artistMatches = !cleanCurrentArtist || !cleanWidgetArtist || cleanCurrentArtist === cleanWidgetArtist;

            return titleMatches && artistMatches;
        },

        syncTrackFromWidget(force = false) {
            if (this.track && !this.track.is_live) {
                return false;
            }

            // El servidor (RadioPlayerService) es el único decisor.
            // Los widgets de RadioBOSS (rbcloud_*) son fuente de último recurso
            // que el cliente solo mira si el servidor devuelve meta_source = "default".
            const currentMetaSource = this.track?.meta_source || this.metaSource || 'default';
            if (currentMetaSource !== 'default' && !force) {
                return false;
            }

            const widgetTrack = this.resolveWidgetTrack();
            if (!widgetTrack) {
                return false;
            }

            const nextTitle = this.normalizeTrackTitle(widgetTrack.title || this.defaultTitle || '');
            const nextArtist = this.normalizeBandArtist(widgetTrack.artist || this.defaultArtist || '');
            const tempBuster = nextArtist + '|' + nextTitle;

            let nextCoverCandidate = widgetTrack.cover || this.fallbackCover;
            if (this.track && this.track.cover && !isFallbackImage(this.track.cover, this.fallbackCover, this.logoUrl)) {
                if (nextArtist === this.track.artist && nextTitle === this.track.title) {
                    if (isFallbackImage(nextCoverCandidate, this.fallbackCover, this.logoUrl)) {
                        nextCoverCandidate = this.track.cover;
                    }
                }
            }
            const nextCover = this.cleanCover(nextCoverCandidate, this.fallbackCover, tempBuster);

            const nextSignature = this.buildSignature({
                title: nextTitle || this.defaultTitle || '',
                artist: nextArtist || this.defaultArtist || '',
                cover: nextCover,
                program: '',
            });

            if (!force && nextSignature === this.track.signature) {
                return false;
            }

            const previousSignature = this.track.signature;

            this.track = {
                ...this.track,
                title: nextTitle || this.defaultTitle || '',
                artist: nextArtist || this.defaultArtist || '',
                cover: nextCover,
                signature: nextSignature,
            };

            if (widgetTrack.elapsed > 0) {
                this.currentTime = widgetTrack.elapsed;
            }

            if (widgetTrack.duration > 0) {
                this.fixedDuration = widgetTrack.duration;
            }

            if (previousSignature !== nextSignature) {
                this.currentTime = widgetTrack.elapsed > 0 ? widgetTrack.elapsed : 0;
                this.fixedDuration = widgetTrack.duration > 0 ? widgetTrack.duration : 0;
                this.syncCounter = 0;
                if (this.fixedDuration > 0) {
                    this.timeSample = {
                        trackSignature: nextSignature,
                        duration: this.fixedDuration,
                        sampleElapsed: this.currentTime,
                        sampledAt: Date.now(),
                        source: 'widget'
                    };
                } else {
                    this.timeSample = null;
                }
                this.bandLookupArtist = '';
                this.bandPanel = this.bandWindowOpen
                    ? {
                        ...this.bandPanel,
                        title: this.track.title || this.bandPanel.title || '',
                        artist: this.track.artist || this.bandPanel.artist || '',
                        info: this.track.band_info || this.track.comment || this.bandPanel.info || '',
                        biography: this.track.band_biography || this.bandPanel.biography || '',
                        cover: this.track.band_thumbnail || this.track.cover || this.bandPanel.cover || this.fallbackCover,
                        foundedLabel: this.track.band_founded_label || this.bandPanel.foundedLabel || '',
                        facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : (this.bandPanel.facts || []),
                    }
                    : {
                        title: this.track.title || '',
                        artist: this.track.artist || '',
                        info: this.track.band_info || this.track.comment || '',
                        biography: this.track.band_biography || '',
                        cover: this.track.cover || this.fallbackCover,
                        foundedLabel: this.track.band_founded_label || '',
                        facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : [],
                    };
            }

            this.syncProgress();
            this.ensureAudioSource();

            if (previousSignature !== nextSignature) {
                this.updateMediaSession(this.track);
            }

            if ((this.normalizeBandArtist(this.track.artist) || this.normalizeTrackTitle(this.track.title)) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            return true;
        },

        async ensureBandInfo(forceRefresh = false, sourceTrack = null) {
            const track = sourceTrack || this.track;
            const artist = this.normalizeBandArtist(track.artist || '');
            const title = this.normalizeTrackTitle(track.title || '');

            if (!artist && !title) {
                return;
            }

            const lookupKey = [artist || '', title || ''].join('|');
            if (!forceRefresh && this.bandLookupArtist === lookupKey && !this.needsBandEnrichment()) {
                return;
            }

            try {
                const response = await fetch(`${this.bandInfoUrl}?artist=${encodeURIComponent(artist)}&title=${encodeURIComponent(title)}`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const payload = await response.json();
                const data = payload?.data || {};

                if (!payload?.success) {
                    return;
                }

                this.track = {
                    ...this.track,
                    band_info: this.formatBandText(data.summary || this.track.band_info || this.track.comment || ''),
                    band_biography: this.formatBandText(data.biography || this.track.band_biography || ''),
                    band_biography_source: String(data.biography_source || this.track.band_biography_source || ''),
                    band_thumbnail: data.thumbnail || this.track.band_thumbnail || this.track.cover || this.fallbackCover,
                    lyrics: data.lyrics || this.track.lyrics || '',
                    social_links: Array.isArray(data.social_links) ? data.social_links : this.track.social_links,
                    band_founded_year: data.formed_year ?? this.track.band_founded_year ?? null,
                    band_founded_label: data.formed_label || this.track.band_founded_label || '',
                    band_facts: Array.isArray(data.facts) ? data.facts : (this.track.band_facts || []),
                };
                this.bandLookupArtist = lookupKey;
                this.bandPanel = {
                    title: this.bandPanel.title || track.title || this.track.title || '',
                    artist: this.bandPanel.artist || track.artist || this.track.artist || '',
                    info: this.track.band_info || this.bandPanel.info || '',
                    biography: this.track.band_biography || this.bandPanel.biography || '',
                    biographySource: this.track.band_biography_source || this.bandPanel.biographySource || '',
                    cover: this.track.band_thumbnail || this.track.cover || this.bandPanel.cover || this.fallbackCover,
                    foundedLabel: this.track.band_founded_label || this.bandPanel.foundedLabel || '',
                    facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : (this.bandPanel.facts || []),
                    logo: this.track.band_logo || this.bandPanel.logo || '',
                    country: this.track.band_country || this.bandPanel.country || '',
                    genre: this.track.band_genre || this.bandPanel.genre || '',
                    membersCount: this.track.band_members_count ?? this.bandPanel.membersCount ?? null,
                    status: this.track.band_status || this.bandPanel.status || '',
                    labels: this.track.band_labels || this.bandPanel.labels || '',
                };
            } catch (error) {
                // ignore band info lookup failures
            }
        },

        needsBandEnrichment() {
            const hasInfo = typeof this.track.band_info === 'string' && this.track.band_info.trim() !== '';
            const hasFounded = typeof this.track.band_founded_label === 'string' && this.track.band_founded_label.trim() !== '';
            const hasFacts = Array.isArray(this.track.band_facts) && this.track.band_facts.length > 0;
            const hasThumb = typeof this.track.band_thumbnail === 'string' && this.track.band_thumbnail.trim() !== '';
            const hasLyrics = typeof this.track.lyrics === 'string' && this.track.lyrics.trim() !== '';

            return !hasInfo || !hasFounded || !hasFacts || !hasThumb || !hasLyrics;
        },

        openPopout() {
            const width = 480;
            const height = 760;
            const left = Math.max(0, (screen.width - width) / 2);
            const top = Math.max(0, (screen.height - height) / 2);

            window.open(
                this.popupUrl,
                'sevenrockradio-player',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );
        },

        async refreshStatus(silent = false, skipBandEnrichment = false, force = false) {
            if (!this.track.is_live && !force) {
                return;
            }

            if (this.statusInFlight && !force) {
                return;
            }

            this.statusInFlight = true;

            try {
                const requestTime = Date.now();
                const response = await fetch(`${this.statusUrl}?t=${requestTime}`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (!payload?.success || !payload?.data) {
                    throw new Error('Invalid status payload');
                }

                // Discard stale responses that arrived out of order
                if (payload.data.updated_at || payload.data.meta_updated_at) {
                    const payloadTime = new Date(payload.data.updated_at || payload.data.meta_updated_at).getTime();
                    if (this.lastAppliedStatusTimestamp && payloadTime < this.lastAppliedStatusTimestamp) {
                        return;
                    }
                    this.lastAppliedStatusTimestamp = payloadTime;
                }

                this.applyStatus(payload.data, silent, this.resolveWidgetTrack());
                this.currentStreamIndex = 0;
                this.loading = false;
                this.statusFailureCount = 0;
            } catch (error) {
                this.statusFailureCount = Math.min(6, (this.statusFailureCount || 0) + 1);
                if (!silent) {
                    this.toastMessage('No se pudo actualizar el reproductor');
                }
                this.loading = false;
            } finally {
                this.statusInFlight = false;
            }
        },

        applyStatus(data, silent = false, widgetTrack = null, skipBandEnrichment = false) {
            const previousSignature = this.track.signature;
            const track = data.track || {};
            const isServerDefault = (data.meta_source === 'default' || track.meta_source === 'default');

            // El servidor (RadioPlayerService) es el único decisor.
            // Los widgets de RadioBOSS (rbcloud_*) pasan a ser fuente de último recurso
            // que el cliente solo mira si el servidor devuelve meta_source = "default".
            const widgetTitle = isServerDefault ? (widgetTrack?.title || '') : '';
            const widgetArtist = isServerDefault ? (widgetTrack?.artist || '') : '';
            const widgetCover = isServerDefault ? (widgetTrack?.cover || '') : '';

            const currentTitle = this.track.title && this.track.title !== this.defaultTitle ? this.track.title : '';
            const currentArtist = this.track.artist && this.track.artist !== this.defaultArtist ? this.track.artist : '';
            const nextTitle = this.normalizeTrackTitle(track.title || widgetTitle || currentTitle || this.defaultTitle || 'Transmisión Oficial');
            const nextArtist = this.normalizeBandArtist(track.artist || widgetArtist || currentArtist || this.defaultArtist || 'Seven Rock Radio');
            const tempBuster = nextArtist + '|' + nextTitle;

            // Si es bloque de programa activo y ya tenemos una portada no-fallback, la conservamos
            const currentIsPrograma = Boolean(this.track?.es_bloque_programa);
            const nowIsPrograma = Boolean(track.es_bloque_programa ?? data.es_bloque_programa ?? (track.program_id || data.program_id || false));
            const currentCoverIsReal = this.track?.cover && !isFallbackImage(this.track.cover, this.fallbackCover, this.logoUrl);

            let nextCoverCandidate = this.fallbackCover;
            if (track.cover && (track.cover_is_fallback === false || !isFallbackImage(track.cover, this.fallbackCover, this.logoUrl))) {
                nextCoverCandidate = track.cover;
            } else if (currentIsPrograma && nowIsPrograma && currentCoverIsReal) {
                // Programa activo que continúa: NO degradar la portada al fallback aunque el poll no traiga cover
                nextCoverCandidate = this.track.cover;
            } else if (this.track && this.track.cover && !isFallbackImage(this.track.cover, this.fallbackCover, this.logoUrl) && nextArtist === this.track.artist && nextTitle === this.track.title) {
                nextCoverCandidate = this.track.cover;
            } else if (widgetCover && !isFallbackImage(widgetCover, this.fallbackCover, this.logoUrl)) {
                nextCoverCandidate = widgetCover;
            } else {
                nextCoverCandidate = widgetCover || track.cover || (nowIsPrograma ? this.track.cover : '') || this.fallbackCover;
            }
            const nextCover = this.cleanCover(nextCoverCandidate, this.fallbackCover, tempBuster);

            const nextSignature = track.signature || this.buildSignature({
                title: nextTitle,
                artist: nextArtist,
                cover: nextCover,
                program: track.program_name || '',
            });
            const trackChanged = Boolean(previousSignature && previousSignature !== nextSignature);
            const nextBandInfo = this.formatBandText(track.band_info || track.comment || '');
            const nextBandBiography = this.formatBandText(track.band_biography || '');
            const nextBiographySource = String(track.band_biography_source || '');
            const nextLyrics = typeof track.lyrics === 'string' ? track.lyrics : '';
            const nextThumbnail = track.band_thumbnail || '';
            const nextFacts = Array.isArray(track.band_facts) ? track.band_facts : [];
            const nextLinks = Array.isArray(track.social_links) ? track.social_links : [];

            this.metaSource = data.meta_source || track.meta_source || 'default';
            this.metaSources = data.meta_sources || track.meta_sources || {};
            this.metaUpdatedAt = data.meta_updated_at || track.meta_updated_at || null;
            this.coverIsFallback = Boolean(data.cover_is_fallback ?? track.cover_is_fallback);

            const isNextBloquePrograma = Boolean(track.es_bloque_programa ?? data.es_bloque_programa ?? (track.program_id || data.program_id || false));
            const isNextProgramaAnticipo = Boolean(track.es_programa_anticipo ?? data.es_programa_anticipo ?? false);

            if (Array.isArray(data.stream_candidates) && data.stream_candidates.length > 0) {
                this.streamCandidates = data.stream_candidates;
            }
            if (data.stream_url) {
                this.streamUrl = data.stream_url;
            }

            this.track = {
                ...this.track,
                ...track,
                title: nextTitle,
                artist: nextArtist,
                cover: nextCover,
                meta_source: this.metaSource,
                meta_sources: this.metaSources,
                meta_updated_at: this.metaUpdatedAt,
                cover_is_fallback: this.coverIsFallback,
                lyrics: trackChanged ? nextLyrics : (nextLyrics || this.track.lyrics || ''),
                band_info: trackChanged ? nextBandInfo : (nextBandInfo || this.track.band_info || ''),
                band_biography: trackChanged ? nextBandBiography : (nextBandBiography || this.track.band_biography || ''),
                band_biography_source: trackChanged ? nextBiographySource : (nextBiographySource || this.track.band_biography_source || ''),
                band_thumbnail: trackChanged ? nextThumbnail : (nextThumbnail || this.track.band_thumbnail || ''),
                band_founded_year: track.band_founded_year ?? this.track.band_founded_year ?? null,
                band_founded_label: track.band_founded_label || this.track.band_founded_label || '',
                band_facts: trackChanged ? nextFacts : (nextFacts.length ? nextFacts : (this.track.band_facts || [])),
                comment: track.comment || '',
                band_members: Array.isArray(track.band_members) ? track.band_members : [],
                social_links: nextLinks.length ? nextLinks : [],
                audio_url: (track.is_live ?? true) ? '' : (track.audio_url || ''),
                is_live: track.is_live ?? true,
                program_name: track.program_name ?? data.program_name ?? (isNextBloquePrograma ? this.track.program_name : '') ?? '',
                program_description: track.program_description ?? data.program_description ?? data.program?.description ?? (isNextBloquePrograma ? this.track.program_description : '') ?? '',
                program_host: track.program_host || (isNextBloquePrograma ? (data.program?.conductor || data.program?.host || '') : ''),
                program_schedule: track.program_schedule || (isNextBloquePrograma ? (data.program?.horario || data.program?.schedule || '') : ''),
                program_id: track.program_id ?? data.program_id ?? (isNextBloquePrograma ? (data.program?.id ?? null) : null),
                program_country: track.program_country ?? data.program_country ?? (isNextBloquePrograma ? (data.program?.country ?? data.program?.pais ?? null) : null),
                es_bloque_programa: isNextBloquePrograma,
                es_programa_anticipo: isNextProgramaAnticipo,
            };

            // Gestión reactiva de pestaña activa en el panel/drawer:
            // 1. Cuando entra un programa: la pestaña debe ser 'program'
            // 2. Cuando estamos en canción: si la pestaña era 'program' y ya no hay programa disponible, vuelve a 'lyrics'
            if (this.track.es_bloque_programa) {
                if (this.activeTab !== 'program' && !this.userHasChosenTab) {
                    this.activeTab = 'program';
                }
            } else if (!this.track.es_programa_anticipo && !this.hasProgramAvailable() && this.activeTab === 'program') {
                this.activeTab = 'lyrics';
            }

            if ((trackChanged || !this.favoriteSyncReady) && this.track.signature) {
                void this.syncFavorites(this.track.signature || '', !this.favoriteSyncReady);
            }

            // Transición automática de ventanas informativas abiertas al cambiar de canción a programa o viceversa
            const wasProgram = this.lastIsProgramState !== null ? this.lastIsProgramState : Boolean(previousSignature && Boolean(this.track?.es_bloque_programa));
            const nowProgram = Boolean(this.track.es_bloque_programa);
            const programStatusTransitioned = (this.lastIsProgramState !== null && this.lastIsProgramState !== nowProgram);
            this.lastIsProgramState = nowProgram;

            if (programStatusTransitioned) {
                if (this.bandWindowOpen && nowProgram) {
                    // Sonaba canción con ventana de banda abierta y empezó un programa: pasar a ventana de programa
                    this.closeBandWindow();
                    this.openProgramWindow(this.track.program_id);
                } else if (this.programWindowOpen && !nowProgram && !this.track.es_programa_anticipo) {
                    // Sonaba programa con ventana de programa abierta y terminó: pasar a ventana de banda/canción
                    this.closeProgramWindow();
                    this.openBandWindow();
                }
            } else if (this.programWindowOpen && nowProgram) {
                // Programa sigue activo pero pudo cambiar la emisión o show
                this.programInfo = {
                    ...(this.programInfo || {}),
                    id: this.track.program_id,
                    name: this.track.program_name,
                    description: this.track.program_description,
                    host: this.track.program_host,
                    schedule: this.track.program_schedule,
                    country: this.track.program_country || this.programInfo?.country || null,
                    cover: this.track.cover || this.fallbackCover,
                };
            }

            if (!nowProgram && !this.track.es_programa_anticipo) {
                this.programInfo = null;
            }

            if (trackChanged) {
                this.bandLookupArtist = '';
                if (this.bandWindowOpen) {
                    this.bandWindowSnapshot = this.buildBandWindowSnapshot();
                    this.bandPanel = { ...this.bandWindowSnapshot };
                } else {
                    this.bandPanel = {
                        title: '',
                        artist: '',
                        info: '',
                        biography: '',
                        biographySource: '',
                        cover: this.track.cover || this.fallbackCover,
                        foundedLabel: '',
                        facts: [],
                    };
                }
            }

            this.queueWidgetSync(trackChanged ? 0 : 200);

            if (!skipBandEnrichment && (this.normalizeBandArtist(this.track.artist) || this.normalizeTrackTitle(this.track.title)) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            this.program = data.program || null;
            this.nextProgram = data.next_program || null;

            // Próxima canción: validar y retirar si ya comenzó a sonar
            const candidateNext = data.next_track || null;
            if (candidateNext && candidateNext.title) {
                const nxtTitle = this.normalizeTrackTitle(candidateNext.title).toLowerCase();
                const curTitle = this.normalizeTrackTitle(this.track.title || '').toLowerCase();
                const nxtArtist = this.normalizeTrackTitle(candidateNext.artist || '').toLowerCase();
                const curArtist = this.normalizeTrackTitle(this.track.artist || '').toLowerCase();

                let isSameTrack = false;
                if (this.track.id && candidateNext.id && String(this.track.id) === String(candidateNext.id)) {
                    isSameTrack = true;
                } else if (nxtTitle && curTitle && nxtTitle === curTitle) {
                    if (nxtArtist && curArtist) {
                        isSameTrack = (nxtArtist === curArtist);
                    } else {
                        isSameTrack = true;
                    }
                }

                if (isSameTrack) {
                    this.nextTrack = null;
                } else {
                    this.nextTrack = candidateNext;
                }
            } else {
                this.nextTrack = null;
            }

            this.queue = (data.queue || []).slice(0, 5);
            this.notices = (data.notices || []).slice(0, 6);

            // Historial: deduplicación de emisiones por identificador o inicio real
            const deduplicateHistory = (items) => {
                if (!Array.isArray(items)) return [];
                const result = [];
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (!item || !item.title) continue;
                    const prev = result[result.length - 1];
                    if (prev) {
                        const sameTitle = String(prev.title).trim().toLowerCase() === String(item.title).trim().toLowerCase();
                        const sameArtist = String(prev.artist || '').trim().toLowerCase() === String(item.artist || '').trim().toLowerCase();
                        if (sameTitle && sameArtist) {
                            // 1. Identificador fiable: si ambos poseen ID y difieren, son emisiones distintas en BD
                            if (prev.id && item.id && String(prev.id) !== String(item.id)) {
                                result.push(item);
                                continue;
                            }
                            // Si tienen el mismo ID, es un duplicado inequívoco
                            if (prev.id && item.id && String(prev.id) === String(item.id)) {
                                continue;
                            }

                            // 2. Inicio real de reproducción:
                            if (prev.played_at && item.played_at) {
                                const diffSecs = Math.abs(new Date(prev.played_at).getTime() - new Date(item.played_at).getTime()) / 1000;
                                // Sondeo idéntico inmediato (< 15 segundos)
                                if (diffSecs < 15) {
                                    continue;
                                }

                                // Si la canción tiene duración y la separación la supera, es una repetición auténtica
                                const dur = Number(item.duration_seconds || prev.duration_seconds || 0);
                                if (dur > 0 && diffSecs < Math.max(20, dur - 10)) {
                                    continue; // Pertenece al mismo pase en curso
                                }

                                // Política conservadora para fuentes sin duración: descartar solo sondeos inmediatos (< 60s)
                                if (dur <= 0 && diffSecs < 60) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                    result.push(item);
                }
                return result;
            };

            if (Array.isArray(data.history) && data.history.length > 0) {
                const clean = deduplicateHistory(data.history);
                this.history = clean.slice(0, this.historyLimit || 10);
                this.historyStale = Boolean(data.history_stale);
            } else if (data.history_stale) {
                this.historyStale = true;
            } else if (!data.history && this.history.length > 0) {
                // Conservar el último historial válido si la fuente cae
                this.historyStale = true;
            } else if (Array.isArray(data.history) && data.history.length === 0) {
                this.history = [];
                this.historyStale = false;
            }

            this.listeners = Number(data.listeners || this.track.listeners || 0);

            const widgetDuration = Number(widgetTrack?.duration || 0);
            const widgetElapsed = Number(widgetTrack?.elapsed || 0);
            const audio = this.$refs?.audio;
            const audioDuration = audio && Number.isFinite(audio.duration) && audio.duration > 0
                ? Math.round(audio.duration)
                : 0;
            const audioElapsed = audio && Number.isFinite(audio.currentTime) && audio.currentTime >= 0
                ? Math.round(audio.currentTime)
                : 0;

            const serverHasDuration = track.duration_seconds !== null && track.duration_seconds !== undefined && Number(track.duration_seconds) > 0;
            const serverDuration = serverHasDuration ? Math.round(Number(track.duration_seconds)) : 0;
            const serverHasElapsed = track.elapsed_seconds !== null && track.elapsed_seconds !== undefined && Number(track.elapsed_seconds) >= 0;
            const serverElapsed = serverHasElapsed ? Math.max(0, Math.round(Number(track.elapsed_seconds))) : 0;

            if (trackChanged) {
                this.timeSample = null;
                if (this.track && this.track.is_live) {
                    if (serverHasDuration) {
                        this.fixedDuration = serverDuration;
                        this.currentTime = serverHasElapsed ? serverElapsed : 0;
                        this.timeSample = {
                            trackSignature: this.track.signature,
                            duration: serverDuration,
                            sampleElapsed: this.currentTime,
                            sampledAt: Date.now(),
                            source: 'api',
                        };
                    } else if (isServerDefault && widgetTrack && this.isWidgetMatchingCurrentTrack(widgetTrack) && widgetTrack.duration > 0) {
                        this.fixedDuration = widgetTrack.duration;
                        this.currentTime = widgetTrack.elapsed >= 0 ? widgetTrack.elapsed : 0;
                        this.timeSample = {
                            trackSignature: this.track.signature,
                            duration: widgetTrack.duration,
                            sampleElapsed: this.currentTime,
                            sampledAt: Date.now(),
                            source: 'widget',
                        };
                    } else {
                        // Limpieza total: sin duración fiable, mostrar «EN DIRECTO»
                        this.fixedDuration = 0;
                        this.currentTime = 0;
                    }
                } else {
                    // En podcast bajo demanda o archivo local
                    this.fixedDuration = serverHasDuration ? serverDuration : (audioDuration || 0);
                    this.currentTime = serverHasElapsed ? serverElapsed : (audioElapsed || 0);
                }
                this.syncCounter = 0;
            } else {
                if (this.track && this.track.is_live) {
                    if (serverHasDuration) {
                        this.fixedDuration = serverDuration;
                        if (serverHasElapsed) {
                            const drift = Math.abs(this.currentTime - serverElapsed);
                            if (drift >= 2 || !this.timeSample) {
                                this.currentTime = serverElapsed;
                            }
                            this.timeSample = {
                                trackSignature: this.track.signature,
                                duration: serverDuration,
                                sampleElapsed: this.currentTime,
                                sampledAt: Date.now(),
                                source: 'api',
                            };
                        }
                    } else if (isServerDefault && widgetTrack && this.isWidgetMatchingCurrentTrack(widgetTrack) && widgetTrack.duration > 0) {
                        this.fixedDuration = widgetTrack.duration;
                        if (widgetTrack.elapsed >= 0) {
                            const drift = Math.abs(this.currentTime - widgetTrack.elapsed);
                            if (drift >= 2 || !this.timeSample) {
                                this.currentTime = widgetTrack.elapsed;
                            }
                            this.timeSample = {
                                trackSignature: this.track.signature,
                                duration: widgetTrack.duration,
                                sampleElapsed: this.currentTime,
                                sampledAt: Date.now(),
                                source: 'widget',
                            };
                        }
                    } else {
                        // La pista actual perdió sus datos de tiempo sin cambiar de canción.
                        // Si no existe una muestra vigente válida, limpiar tiempo y mostrar «EN DIRECTO».
                        const hasValidRecentSample = this.timeSample
                            && this.timeSample.trackSignature === this.track.signature
                            && this.fixedDuration > 0
                            && (Date.now() - this.timeSample.sampledAt) < 15000;

                        if (!hasValidRecentSample) {
                            this.fixedDuration = 0;
                            this.currentTime = 0;
                            this.timeSample = null;
                        }
                    }
                } else {
                    if (serverHasDuration) {
                        this.fixedDuration = serverDuration;
                    } else if (audioDuration > 0) {
                        this.fixedDuration = audioDuration;
                    }
                    if (audioElapsed > 0) {
                        this.currentTime = audioElapsed;
                    }
                }
            }

            if (!this.track.is_live && this.fixedDuration > 0 && this.currentTime > this.fixedDuration) {
                this.currentTime = 0;
            }
            this.syncProgress();
            this.ensureAudioSource();

            if (!silent && previousSignature && previousSignature !== this.track.signature) {
                this.toastMessage(`Ahora suena: ${this.track.title}`);
            }
            this.updateDocumentTitle();
            if (trackChanged) {
                this.updateMediaSession(this.track);
            }

            // Notificar a observadores locales (panel de depuración / dev-scenario)
            try {
                window.dispatchEvent(new CustomEvent('sr-player-status-applied', {
                    detail: {
                        mode: this.mode,
                        track: this.track,
                        program: this.program,
                        nextProgram: this.nextProgram,
                        nextTrack: this.nextTrack,
                        listeners: this.listeners,
                        metaSource: this.metaSource,
                    }
                }));
            } catch (e) {
                // ignore
            }
        },

        formatBandText(value) {
            const text = String(value || '').trim();
            if (!text) {
                return '';
            }

            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            const decoded = textarea.value
                .replace(/\[(?:[A-Za-z]{1,3}\d+|\d+)\]/g, '')
                .replace(/\r\n?/g, '\n')
                .replace(/\n{3,}/g, '\n\n')
                .replace(/ *\n */g, '\n')
                .replace(/\b(Contexto de catalogo|Contexto de catálogo|Miembros relacionados|Alias \/ variaciones)\b/gi, '\n\n$1')
                .replace(/[ \t]{2,}/g, ' ')
                .trim();

            return decoded;
        },

        buildSignature({ title = '', artist = '', cover = '', program = '' } = {}) {
            return [title, artist, cover, program].join('|');
        },

        readNowPlayingWidget() {
            const root = document.getElementById(this.widgetIds.root);
            const cover = document.getElementById(this.widgetIds.cover);
            const timerElapsed = document.getElementById(this.widgetIds.timerElapsed);
            const timerRemaining = document.getElementById(this.widgetIds.timerRemaining);
            const timerRoot = timerElapsed?.closest?.('.rbcloud_tracktimer') || timerRemaining?.closest?.('.rbcloud_tracktimer');
            const rawText = this.cleanWidgetText(root?.innerText || root?.textContent || '');
            const parsed = this.parseWidgetNowPlaying(rawText);
            const timer = this.readTrackTimerWidget(timerRoot, timerElapsed, timerRemaining);

            return {
                cover: cover?.getAttribute('src') || cover?.currentSrc || '',
                artist: parsed.artist,
                title: parsed.title,
                elapsed: timer.elapsed || parsed.elapsed,
                duration: (timer.elapsed > 0 && timer.remaining > 0) ? (timer.elapsed + timer.remaining) : parsed.duration,
                text: rawText,
            };
        },

        readTrackTimerWidget(timerRoot = null, timerElapsed = null, timerRemaining = null) {
            const elapsedText = this.cleanWidgetText(
                timerElapsed?.innerText
                || timerElapsed?.textContent
                || timerRoot?.querySelector?.(`#${this.widgetIds.timerElapsed}`)?.innerText
                || ''
            );
            const durationText = this.cleanWidgetText(
                timerRemaining?.innerText
                || timerRemaining?.textContent
                || timerRoot?.querySelector?.(`#${this.widgetIds.timerRemaining}`)?.innerText
                || ''
            );

            const elapsed = this.parseWidgetDuration(elapsedText);
            const remaining = this.parseWidgetDuration(durationText);

            if (elapsed > 0 || remaining > 0) {
                return { elapsed, remaining };
            }

            const rawText = this.cleanWidgetText(timerRoot?.innerText || timerRoot?.textContent || '');
            const matches = Array.from(rawText.matchAll(/\b(\d{1,3}:\d{2})\b/g), (match) => match[1]);
            const fallbackElapsed = this.parseWidgetDuration(matches[0] || '');
            const fallbackRemaining = this.parseWidgetDuration(matches[1] || '');
            return {
                elapsed: fallbackElapsed,
                remaining: fallbackRemaining,
            };
        },

        inferWidgetTrack(widgetTrack = {}) {
            const title = this.cleanWidgetText(widgetTrack.title || '');
            const artist = this.normalizeBandArtist(widgetTrack.artist || '');

            if (artist) {
                return {
                    title,
                    artist,
                    cover: widgetTrack.cover || '',
                    elapsed: Number(widgetTrack.elapsed || 0),
                    duration: Number(widgetTrack.duration || 0),
                };
            }

            const parts = title.includes(' - ') ? title.split(' - ') : [];
            if (parts.length >= 2) {
                const lastPart = parts[parts.length - 1] || '';
                return {
                    title: this.cleanWidgetText(parts.slice(0, -1).join(' - ')),
                    artist: this.normalizeBandArtist(lastPart),
                    cover: widgetTrack.cover || '',
                    elapsed: Number(widgetTrack.elapsed || 0),
                    duration: Number(widgetTrack.duration || 0),
                };
            }

            return {
                title,
                artist: '',
                cover: widgetTrack.cover || '',
                elapsed: Number(widgetTrack.elapsed || 0),
                duration: Number(widgetTrack.duration || 0),
            };
        },

        parseWidgetNowPlaying(value) {
            const text = this.cleanWidgetText(value);
            if (!text) {
                return { title: '', artist: '', elapsed: 0, duration: 0 };
            }

            const normalized = text
                .replace(/\u00A0/g, ' ')
                .replace(/\s{2,}/g, ' ')
                .trim();

            const timeMatches = Array.from(normalized.matchAll(/\b(\d{1,3}:\d{2})\b/g), (match) => match[1]);
            const duration = this.parseWidgetDuration(timeMatches[timeMatches.length - 1] || '');
            const elapsed = this.parseWidgetDuration(timeMatches[timeMatches.length - 2] || '');

            const withoutTimes = normalized.replace(/\b\d{1,3}:\d{2}\b/g, ' ').replace(/\s{2,}/g, ' ').trim();
            const withoutPrefix = withoutTimes.replace(/^(?:reproduciendo ahora|now playing|nowplaying|reproduciendo|playing now)\s*/i, '').trim();
            const candidate = withoutPrefix || normalized;

            const split = this.splitWidgetArtistTitle(candidate);

            return {
                title: this.normalizeTrackTitle(split.title || candidate),
                artist: this.normalizeBandArtist(split.artist || ''),
                elapsed,
                duration,
            };
        },

        parseWidgetDuration(value) {
            const strValue = String(value || '');
            if (strValue.toLowerCase() === 'undefined') {
                return 0;
            }

            const text = strValue.replace(/[^\d:]/g, '');
            if (!text) {
                return 0;
            }

            const parts = text.split(':').map((part) => Number(part));
            if (parts.some((p) => !Number.isFinite(p))) {
                return 0;
            }

            if (parts.length === 3) {
                return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
            } else if (parts.length === 2) {
                return (parts[0] * 60) + parts[1];
            }

            return parts.length === 1 ? parts[0] : 0;
        },

        splitWidgetArtistTitle(value) {
            const text = this.cleanWidgetText(value);
            if (!text) {
                return { title: '', artist: '' };
            }

            const parts = text.includes(' - ') ? text.split(' - ') : [];
            if (parts.length >= 2) {
                // RadioBOSS stream standard format: "Artist - Title"
                const artist = this.cleanWidgetText(parts[0] || '');
                const title = this.cleanWidgetText(parts.slice(1).join(' - '));
                return { title, artist };
            }

            return { title: text, artist: '' };
        },

        normalizeBandArtist(value) {
            const text = this.cleanWidgetText(value);
            if (!text) {
                return '';
            }

            const cleaned = text
                .replace(/^\s*(?:remasterizado|remastered)\s*\d{0,4}\s*[-:]\s*/i, '')
                .replace(/\s*\((?:feat\.?|ft\.?|with)[^)]+\)\s*$/i, '')
                .replace(/\s+(?:feat\.?|ft\.?|featuring|with)\b.*$/i, '')
                .replace(/\s{2,}/g, ' ')
                .trim();

            if (!cleaned) {
                return '';
            }

            return cleaned.toLowerCase() === 'transmisión oficial' || cleaned.toLowerCase() === 'transmisiÃ³n oficial'
                ? ''
                : cleaned;
        },

        normalizeTrackTitle(value) {
            const text = this.cleanWidgetText(value);
            if (!text) {
                return '';
            }

            if (text.toLowerCase() === 'transmisión oficial') {
                return '';
            }

            const parts = text.includes(' - ') ? text.split(' - ') : [];
            return parts.length >= 2 ? this.cleanWidgetText(parts[0]) : text;
        },

        cleanWidgetText(value) {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            if (!text || text === '...' || text === '&nbsp;' || text.toLowerCase() === 'cargando...' || text.toLowerCase() === 'undefined') {
                return '';
            }

            return text;
        },

        tickProgress() {
            if (!this.playing) {
                return;
            }

            const audio = this.$refs?.audio;
            if (audio && Number.isFinite(audio.currentTime) && audio.currentTime > 0 && !this.track.is_live) {
                this.currentTime = Math.round(audio.currentTime);
            } else if (this.track && this.track.is_live) {
                if (this.fixedDuration > 0) {
                    // Caducidad de datos al alcanzar el final:
                    // 1. Si currentTime sobrepasa fixedDuration + 5s
                    // 2. O si el avance proyectado en tiempo real desde la muestra sobrepasa fixedDuration + 8s
                    const secondsSinceSample = (this.timeSample && this.timeSample.sampledAt > 0)
                        ? Math.floor((Date.now() - this.timeSample.sampledAt) / 1000)
                        : 0;
                    const estimatedElapsed = (this.timeSample && this.timeSample.sampledAt > 0)
                        ? (this.timeSample.sampleElapsed + secondsSinceSample)
                        : this.currentTime;

                    if (this.currentTime >= (this.fixedDuration + 5) || estimatedElapsed >= (this.fixedDuration + 8)) {
                        // Limpiar tiempos expirados y mostrar «EN DIRECTO»
                        this.fixedDuration = 0;
                        this.currentTime = 0;
                        this.timeSample = null;
                        this.syncProgress();
                        return;
                    }

                    this.currentTime += 1;
                } else {
                    this.currentTime = 0;
                }

                this.syncCounter += 1;

                if (this.syncCounter >= 5) {
                    this.syncCounter = 0;
                    // Sincronización con widget RadioBOSS Cloud:
                    // CONDICIONES ESTRICTAS:
                    // 1. NO estar en bloque de programa
                    // 2. El widget debe coincidir estrictamente con el título y artista de la pista actual.
                    //    Una duración parecida no demuestra pertenencia.
                    if (!this.track.es_bloque_programa) {
                        const widgetTrack = this.resolveWidgetTrack();
                        if (widgetTrack && this.isWidgetMatchingCurrentTrack(widgetTrack)) {
                            const timerElapsedEl = document.getElementById(this.widgetIds.timerElapsed);
                            const timerRemainingEl = document.getElementById(this.widgetIds.timerRemaining);
                            const timerRoot = timerElapsedEl?.closest?.('.rbcloud_tracktimer') || timerRemainingEl?.closest?.('.rbcloud_tracktimer');
                            if (timerRoot) {
                                const timer = this.readTrackTimerWidget(timerRoot, timerElapsedEl, timerRemainingEl);
                                if (timer.elapsed >= 0 && (timer.elapsed > 0 || timer.remaining > 0)) {
                                    const diff = Math.abs(this.currentTime - timer.elapsed);
                                    if (diff >= 2) {
                                        this.currentTime = timer.elapsed;
                                    }
                                    if (timer.remaining > 0) {
                                        this.fixedDuration = timer.elapsed + timer.remaining;
                                    }
                                    this.timeSample = {
                                        trackSignature: this.track.signature,
                                        duration: this.fixedDuration,
                                        sampleElapsed: this.currentTime,
                                        sampledAt: Date.now(),
                                        source: 'widget',
                                    };
                                }
                            }
                        }
                    }
                }
            } else {
                this.currentTime += 1;
            }

            this.syncProgress();
        },

        syncProgress() {
            this.progress.elapsed = this.currentTime;
            this.progress.duration = this.fixedDuration;

            if (this.fixedDuration <= 0) {
                this.progress.ratio = 0;
                return;
            }

            this.progress.ratio = Math.max(0, Math.min(100, Math.round((this.currentTime / this.fixedDuration) * 100)));
        },

        seek(event) {
            // La emisión en directo es estrictamente informativa: no admite salto temporal
            if (this.track && this.track.is_live) {
                return;
            }

            const audio = this.$refs?.audio;
            if (!audio || this.fixedDuration <= 0) {
                return;
            }

            const rect = event.currentTarget.getBoundingClientRect();
            const ratio = (event.clientX - rect.left) / rect.width;
            const clamped = Math.max(0, Math.min(1, ratio));
            this.currentTime = Math.round(this.fixedDuration * clamped);
            this.syncProgress();

            if (this.track.audio_url && Number.isFinite(audio.duration) && audio.duration > 0) {
                audio.currentTime = audio.duration * clamped;
            }
        },

        hasReliableTime() {
            if (!this.track) {
                return false;
            }
            if (!this.track.is_live) {
                return (this.fixedDuration > 0 || this.progress.duration > 0);
            }
            if (this.fixedDuration <= 0) {
                return false;
            }
            // 0 es un valor inicial válido (canción recién iniciada)
            if (this.currentTime < 0 || this.currentTime > (this.fixedDuration + 5)) {
                return false;
            }
            // Caducidad de la muestra en tiempo real
            if (this.timeSample && this.timeSample.sampledAt > 0) {
                const elapsedSinceSample = Math.floor((Date.now() - this.timeSample.sampledAt) / 1000);
                const projectedElapsed = this.timeSample.sampleElapsed + elapsedSinceSample;
                if (projectedElapsed > (this.fixedDuration + 8)) {
                    return false;
                }
            }
            return true;
        },

        formatTime(seconds) {
            const safe = Math.max(0, Number(seconds) || 0);
            const minutes = String(Math.floor(safe / 60)).padStart(2, '0');
            const secs = String(Math.floor(safe % 60)).padStart(2, '0');
            return `${minutes}:${secs}`;
        },

        setTab(tab) {
            this.userHasChosenTab = true;
            this.activeTab = tab;
            safeWrite('sr-player-tab', tab);
            if (tab === 'program' && this.track.es_bloque_programa && !this.hasRealSong() && !this.programInfo) {
                void this.openProgramWindow(this.track.program_id);
            }
        },

        programText() {
            if (this.program) {
                const parts = [
                    this.program.name,
                    this.program.host,
                    this.program.schedule,
                    this.program.description,
                ].filter(Boolean);

                if (parts.length > 0) {
                    return parts.join(' · ');
                }
            }

            if (this.track.es_bloque_programa) {
                const parts = [
                    this.track.program_name,
                    this.track.program_host,
                    this.track.program_schedule,
                    this.track.program_description,
                ].filter(Boolean);

                if (parts.length > 0) {
                    return parts.join(' · ');
                }
            }

            return 'No hay programa activo.';
        },

        isFavoriteCurrent() {
            return this.favorites.some((item) => item.signature && item.signature === this.track.signature);
        },

        async syncFavorites(signature = '', allowMigration = true) {
            if (!this.favoritesUrl || this.favoriteSyncInFlight) {
                return;
            }

            this.favoriteSyncInFlight = true;

            try {
                const url = new URL(this.favoritesUrl, window.location.origin);
                if (signature) {
                    url.searchParams.set('signature', signature);
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (!payload?.success) {
                    throw new Error('Invalid favorites payload');
                }

                const data = payload.data || {};
                const favorites = Array.isArray(data.favorites) ? data.favorites : [];

                if (allowMigration && favorites.length === 0) {
                    const legacyFavorites = Array.isArray(this.favorites) && this.favorites.length > 0
                        ? [...this.favorites]
                        : readFavoritesCache();

                    await this.importLegacyFavorites(legacyFavorites);
                    this.favoriteSyncInFlight = false;
                    await this.syncFavorites(signature, false);
                    return;
                }

                this.favorites = favorites;
                this.favoriteCount = Number(data.track_count ?? 0);
                writeFavoritesCache(this.favorites);
                this.favoriteSyncReady = true;
            } catch (error) {
                this.favorites = readFavoritesCache();
            } finally {
                this.favoriteSyncInFlight = false;
            }
        },

        async importLegacyFavorites(legacyFavorites = null) {
            if (safeRead('sr-player-favorites-migrated-v1', '0') === '1') {
                return;
            }

            const favorites = Array.isArray(legacyFavorites) ? legacyFavorites : readFavoritesCache();
            if (!favorites.length || !this.favoritesImportUrl) {
                return;
            }

            try {
                const response = await fetch(this.favoritesImportUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ favorites }),
                });
                const payload = await response.json();

                if (!payload?.success) {
                    throw new Error('Invalid favorites import payload');
                }

                const data = payload.data || {};
                this.favorites = Array.isArray(data.favorites) ? data.favorites : favorites;
                writeFavoritesCache(this.favorites);
                safeWrite('sr-player-favorites-migrated-v1', '1');
            } catch (error) {
                // Keep local cache as fallback.
            }
        },

        async toggleFavorite() {
            if (!this.track.signature) {
                return;
            }

            if (!this.favoritesToggleUrl) {
                return;
            }

            const payload = {
                signature: this.track.signature,
                title: this.track.title,
                artist: this.track.artist,
                cover: this.track.cover,
            };
            const previousFavorites = [...this.favorites];
            const previousCount = this.favoriteCount;
            const wasFavorite = this.isFavoriteCurrent();

            if (wasFavorite) {
                this.favorites = this.favorites.filter((item) => item.signature !== this.track.signature);
                this.favoriteCount = Math.max(0, this.favoriteCount - 1);
            } else {
                this.favorites = [
                    ...this.favorites,
                    payload,
                ].slice(-50);
                this.favoriteCount += 1;
            }

            writeFavoritesCache(this.favorites);

            try {
                const response = await fetch(this.favoritesToggleUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!result?.success) {
                    throw new Error('Invalid favorites toggle payload');
                }

                const data = result.data || {};
                this.favorites = Array.isArray(data.favorites) ? data.favorites : this.favorites;
                this.favoriteCount = Number(data.track_count ?? this.favoriteCount);
                writeFavoritesCache(this.favorites);
                this.toastMessage(data.is_favorite ? 'Añadido a favoritos' : 'Favorito eliminado');
            } catch (error) {
                this.favorites = previousFavorites;
                this.favoriteCount = previousCount;
                writeFavoritesCache(this.favorites);
                this.toastMessage('No se pudo sincronizar');
            }
        },

        toggleSharePanel() {
            this.sharePanelOpen = !this.sharePanelOpen;
        },

        async updateShareKey() {
            const title = this.tituloVisible;
            const artist = this.subtituloVisible;
            const program = this.track.program_name || '';
            const cover = this.track.cover || this.fallbackCover || '';

            try {
                const response = await fetch('/api/player/share-track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ title, artist, cover, program }),
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data && data.key) {
                        this.shareKey = data.key;
                        return;
                    }
                }
            } catch (err) {
                // Fail silently, fallback is used
            }
            this.shareKey = '';
        },

        shareContext() {
            const title = this.tituloVisible;
            const artist = this.subtituloVisible;
            const program = this.track.program_name || '';
            const cover = this.track.cover || this.fallbackCover || '';
            const baseUrl = window.location.origin + '/';

            let url = baseUrl;
            if (this.shareKey) {
                url = `${baseUrl}?v=${encodeURIComponent(String(this.shareKey))}`;
            } else {
                const version = this.track.signature || this.track.audio_url || this.track.program_id || '';
                if (version) {
                    url = `${baseUrl}?v=${encodeURIComponent(String(version))}`;
                }
            }

            const text = `¡Todas las épocas del rock, están aquí! en Seven Rock Radio 🤘\nEscuchanos!!: ${url}`;

            return {
                title,
                artist,
                program,
                cover,
                url,
                text,
                encodedUrl: encodeURIComponent(url),
                encodedTitle: encodeURIComponent(title),
                encodedText: encodeURIComponent(text),
            };
        },

        shareTargets() {
            const share = this.shareContext();

            return {
                twitter: `https://twitter.com/intent/tweet?text=${share.encodedTitle}&url=${share.encodedUrl}`,
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${share.encodedUrl}`,
                whatsapp: `https://api.whatsapp.com/send?text=${share.encodedText}`,
                telegram: `https://t.me/share/url?url=${share.encodedUrl}&text=${share.encodedTitle}`,
                linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${share.encodedUrl}`,
                pinterest: `https://pinterest.com/pin/create/button/?url=${share.encodedUrl}${share.cover ? `&media=${encodeURIComponent(share.cover)}` : ''}&description=${share.encodedTitle}`,
            };
        },

        async shareCurrent() {
            const { text, url } = this.shareContext();

            if (navigator.share) {
                try {
                    await navigator.share({ title: 'Seven Rock Radio', text, url });
                    this.toastMessage('Compartido');
                    return;
                } catch (error) {
                    // ignore and fall back to clipboard
                }
            }

            try {
                await navigator.clipboard.writeText(`${text} ${url}`.trim());
                this.toastMessage('Copiado al portapapeles');
            } catch (error) {
                this.toastMessage('No se pudo compartir');
            }
        },

        async handleHotkeys(event) {
            const tag = (event.target?.tagName || '').toLowerCase();
            if (['input', 'textarea', 'select'].includes(tag)) {
                return;
            }

            if (event.code === 'Space') {
                event.preventDefault();
                this.togglePlay();
                return;
            }

            if (event.code === 'ArrowUp') {
                event.preventDefault();
                this.volume = Math.min(1, Math.round((this.volume + 0.05) * 100) / 100);
                this.updateVolume();
                return;
            }

            if (event.code === 'ArrowDown') {
                event.preventDefault();
                this.volume = Math.max(0, Math.round((this.volume - 0.05) * 100) / 100);
                this.updateVolume();
                return;
            }

            if (event.code === 'KeyM') {
                event.preventDefault();
                this.toggleMute();
                return;
            }

            if (event.code === 'KeyL') {
                this.setTab('lyrics');
                return;
            }

            if (event.code === 'KeyB') {
                this.setTab('band');
                return;
            }

            if (event.code === 'KeyP') {
                this.setTab('program');
                return;
            }

            if (event.code === 'KeyH') {
                this.setTab('history');
                return;
            }

            if (event.code === 'Escape' && this.mode !== 'page') {
                this.closeTransientOverlays();
                safeWrite('sr-player-expanded', '0');
            }
        },

        toastMessage(message) {
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toastHandle) {
                clearTimeout(this.toastHandle);
            }

            this.toastHandle = setTimeout(() => {
                this.toast.visible = false;
                this.toast.message = '';
                this.toastHandle = null;
            }, 2200);
        },

        updateMediaSession(track) {
            if (!('mediaSession' in navigator) || !window.MediaMetadata) {
                return;
            }

            const title = this.tituloVisible || 'Seven Rock Radio';
            const artist = this.subtituloVisible || 'En vivo';
            const coverUrl = track.cover || track.band_thumbnail || this.bandPanel.cover || this.fallbackCover;
            const absoluteCoverUrl = coverUrl ? (coverUrl.startsWith('http') ? coverUrl : window.location.origin + coverUrl) : '';

            navigator.mediaSession.metadata = new MediaMetadata({
                title: title,
                artist: artist,
                album: 'Seven Rock Radio',
                artwork: absoluteCoverUrl ? [
                    { src: absoluteCoverUrl, sizes: '96x96' },
                    { src: absoluteCoverUrl, sizes: '128x128' },
                    { src: absoluteCoverUrl, sizes: '192x192' },
                    { src: absoluteCoverUrl, sizes: '256x256' },
                    { src: absoluteCoverUrl, sizes: '384x384' },
                    { src: absoluteCoverUrl, sizes: '512x512' }
                ] : []
            });

            this.setupMediaSessionHandlers();
        },

        setupMediaSessionHandlers() {
            if (!('mediaSession' in navigator)) {
                return;
            }

            try {
                navigator.mediaSession.setActionHandler('play', () => {
                    if (!this.playing) {
                        this.attemptPlayWithFallback();
                    }
                });
                navigator.mediaSession.setActionHandler('pause', () => {
                    if (this.playing) {
                        this.attemptPlayWithFallback();
                    }
                });
                // Explicitly disable prev/next track skip buttons on OS lock screen/notification widgets
                navigator.mediaSession.setActionHandler('previoustrack', null);
                navigator.mediaSession.setActionHandler('nexttrack', null);
            } catch (error) {
                // ignore
            }
        },

        updateMediaSessionPlaybackState(playing) {
            if (!('mediaSession' in navigator)) {
                return;
            }
            navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
        },

        bandLinks() {
            const links = Array.isArray(this.bandWindowView.socialLinks) ? this.bandWindowView.socialLinks : [];

            if (links.length) {
                return links
                    .map((item) => {
                        if (typeof item === 'string') {
                            return { label: item, url: item };
                        }

                        if (item && typeof item === 'object') {
                            return {
                                label: item.label || item.name || item.title || item.url || 'Enlace',
                                url: item.url || item.href || item.link || '',
                            };
                        }

                        return null;
                    })
                    .filter((item) => item && item.url);
            }

            return [];
        },

        countryFlag(countryName) {
            if (!countryName || typeof countryName !== 'string') {
                return '';
            }
            const normalized = countryName.trim().toLowerCase();
            const map = {
                'argentina': '🇦🇷',
                'españa': '🇪🇸',
                'espana': '🇪🇸',
                'spain': '🇪🇸',
                'chile': '🇨🇱',
                'venezuela': '🇻🇪',
                'méxico': '🇲🇽',
                'mexico': '🇲🇽',
                'estados unidos': '🇺🇸',
                'eeuu': '🇺🇸',
                'usa': '🇺🇸',
                'reino unido': '🇬🇧',
                'uk': '🇬🇧',
                'gran bretaña': '🇬🇧',
                'gran bretana': '🇬🇧',
                'colombia': '🇨🇴',
                'perú': '🇵🇪',
                'peru': '🇵🇪',
                'uruguay': '🇺🇾',
                'brasil': '🇧🇷',
                'brazil': '🇧🇷',
                'alemania': '🇩🇪',
                'germany': '🇩🇪',
                'francia': '🇫🇷',
                'france': '🇫🇷',
                'italia': '🇮🇹',
                'italy': '🇮🇹',
                'australia': '🇦🇺',
                'canadá': '🇨🇦',
                'canada': '🇨🇦',
            };
            return map[normalized] || '🌍';
        },

        bandLineup() {
            const members = Array.isArray(this.bandWindowView.bandMembers) ? this.bandWindowView.bandMembers : [];

            return members
                .map((item) => {
                    if (typeof item === 'string') {
                        const name = this.cleanWidgetText(item);
                        return name ? { name, role: '' } : null;
                    }

                    if (item && typeof item === 'object') {
                        const name = this.cleanWidgetText(item.name || item.member || item.title || '');
                        const role = this.cleanWidgetText(item.role || item.instrument || item.position || '');

                        if (!name) {
                            return null;
                        }

                        return { name, role };
                    }

                    return null;
                })
                .filter(Boolean);
        },

        get resumenBio() {
            const texto = this.bandWindowView.info || '';
            if (!texto) return '';
            if (texto.length <= 180) return texto;
            const cortado = texto.substring(0, 177);
            const ultimoEspacio = cortado.lastIndexOf(' ');
            return (ultimoEspacio > 0 ? cortado.substring(0, ultimoEspacio) : cortado) + '...';
        },

        get biographyExpanded() {
            return this.bandWindowView.biography || '';
        },

        get tituloVisible() {
            const esPrograma = Boolean(this.track?.es_bloque_programa);
            if (esPrograma) {
                const t = (this.track?.title ?? '').trim();
                const def = (this.defaultTitle ?? '').trim().toLowerCase();
                const isGeneric = !t || t.toLowerCase() === def || t.toLowerCase() === 'transmisión oficial' || t.toLowerCase() === 'transmision oficial' || t.toLowerCase() === 'seven rock radio';
                if (!isGeneric) {
                    return this.track.title;
                }
                return this.track?.program_name || this.track?.title || this.defaultTitle;
            }
            return (this.track?.title ?? '') !== '' ? this.track.title : this.defaultTitle;
        },

        get subtituloVisible() {
            const esPrograma = Boolean(this.track?.es_bloque_programa);
            if (esPrograma) {
                return this.track?.program_host || this.track?.program_schedule || this.track?.artist || this.defaultArtist;
            }
            return (this.track?.artist ?? '') !== '' ? this.track.artist : this.defaultArtist;
        },

        /**
         * coverVisible — única fuente de verdad para la portada.
         * Prioridad: portada del programa > portada de la pista > fallbackCover.
         * Nunca devuelve el logo/fallback cuando hay un bloque de programa activo
         * con una imagen real disponible.
         */
        get coverVisible() {
            const esPrograma = Boolean(this.track?.es_bloque_programa);
            const coverPrograma = this.programInfo?.cover
                || this.track?.program_cover
                || '';

            if (esPrograma && coverPrograma
                && !isFallbackImage(coverPrograma, this.fallbackCover, this.logoUrl)) {
                return coverPrograma;
            }

            const actual = this.track?.cover || '';
            if (actual) {
                if (this.track?.cover_is_fallback === false) {
                    return actual;
                }
                if (!isFallbackImage(actual, this.fallbackCover, this.logoUrl)) {
                    return actual;
                }
            }

            return this.fallbackCover;
        },
    }));
}
