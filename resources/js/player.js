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
            if (lowerUrl.includes('artwork/569')) return true;
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
        bandWindowOpen: false,
        programWindowOpen: false,
        bandWindowTab: 'bio',
        bandWindowSnapshot: null,
        dockMinimized: true,
        activeTab: 'lyrics',
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
        queue: [],
        notices: [],
        history: [],
        listeners: 0,
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
            timerElapsed: 'rbcloud_tracktimer_e11096',
            timerSeparator: 'rbcloud_tracktimer_sep11096',
            timerRemaining: 'rbcloud_tracktimer_r11096',
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
            this.activeTab = safeRead('sr-player-tab', 'lyrics');
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
            this.watchNowPlayingWidget();
            this.queueStatusRefresh(0);

            this.startPolling();
            this.progressHandle = setInterval(() => this.tickProgress(), 1000);

            this.boundHotkeys = (event) => this.handleHotkeys(event);
            window.addEventListener('keydown', this.boundHotkeys);

            // Media Session API integration
            this.$watch('track', (value) => this.updateMediaSession(value));
            this.$watch('playing', (value) => this.updateMediaSessionPlaybackState(value));
            this.updateMediaSession(this.track);
            this.updateMediaSessionPlaybackState(this.playing);
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

        startPolling() {
            if (this.pollHandle) {
                clearTimeout(this.pollHandle);
                clearInterval(this.pollHandle);
            }
            const run = async () => {
                await this.refreshStatus(true);
                const interval = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
                this.pollHandle = setTimeout(run, interval);
            };
            this.pollHandle = setTimeout(run, Math.max(3, this.pollInterval) * 1000);
        },

        adjustPollingInterval() {
            if (this.pollHandle) {
                clearTimeout(this.pollHandle);
                clearInterval(this.pollHandle);
            }
            const run = async () => {
                await this.refreshStatus(true);
                const interval = this.playing ? Math.max(3, this.pollInterval) * 1000 : 15000;
                this.pollHandle = setTimeout(run, interval);
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
                const audio = this.$refs.audio;
                if (!audio) {
                    return;
                }

                audio.volume = this.volume;
                audio.muted = this.muted;
                audio.preload = 'none';
                this.streamCandidates = [this.streamUrl, this.altStreamUrl].filter(Boolean);
            });
        },

        get playbackSource() {
            return this.track.audio_url || this.streamUrl;
        },

        bindAudioEvents() {
            this.$nextTick(() => {
                const audio = this.$refs.audio;
                if (!audio) {
                    return;
                }

                const syncFromAudio = () => {
                    const duration = Number.isFinite(audio.duration) && audio.duration > 0
                        ? Math.round(audio.duration)
                        : 0;
                    const elapsed = Number.isFinite(audio.currentTime) && audio.currentTime >= 0
                        ? Math.round(audio.currentTime)
                        : 0;

                    if (duration > 0) {
                        this.progress.duration = duration;
                    } else if (this.track.is_live) {
                        this.progress.duration = 0;
                    }

                    this.progress.elapsed = elapsed;
                    this.syncProgress();
                };

                audio.addEventListener('play', () => {
                    this.playing = true;
                    this.updateDocumentTitle();
                    this.adjustPollingInterval();
                    this.updateMediaSession(this.track);
                    this.updateMediaSessionPlaybackState(true);
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
            const audio = this.$refs.audio;
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

            const audio = this.$refs.audio;
            if (audio) {
                audio.muted = this.muted;
            }

            safeWrite('sr-player-muted', this.muted ? '1' : '0');
        },

        ensureAudioSource() {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            const source = this.streamCandidates[this.currentStreamIndex] || this.playbackSource || this.streamUrl;
            const currentSource = audio.currentSrc || audio.getAttribute('src') || '';

            if (currentSource !== source || audio.error) {
                audio.pause();
                audio.removeAttribute('src');
                audio.src = source;
                audio.load();
            }

            audio.volume = this.volume;
            audio.muted = this.muted;
        },

        async attemptPlayWithFallback() {
            const audio = this.$refs.audio;
            if (!audio) {
                return;
            }

            this.ensureAudioSource();

            if (this.playing) {
                this.playing = false;
                this.loading = false;
                audio.pause();
                this.toastMessage('Reproductor en pausa');
                this.queueStatusRefresh(0);
                return;
            }

            try {
                this.loading = true;
                this.playing = true;
                audio.load();
                await audio.play();
                this.loading = false;
                this.queueStatusRefresh(0);
                this.toastMessage('Reproduciendo');
            } catch (error) {
                if (this.currentStreamIndex + 1 < this.streamCandidates.length) {
                    this.currentStreamIndex += 1;
                    this.ensureAudioSource();
                    try {
                        this.loading = true;
                        this.playing = true;
                        audio.load();
                        await audio.play();
                        this.loading = false;
                        this.queueStatusRefresh(0);
                        this.toastMessage('Reproduciendo');
                        return;
                    } catch (fallbackError) {
                        // fall through
                    }
                }

                this.playing = false;
                this.loading = false;
                this.queueStatusRefresh(0);
                this.toastMessage('No se pudo iniciar el audio');
            }
        },

        togglePlay() {
            return this.attemptPlayWithFallback();
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
            if (!this.interactionReady || !this.hasUserGesture() || (event && event.isTrusted === false)) {
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

        async openBandWindow() {
            if (!this.interactionReady || !this.hasUserGesture()) {
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
            if (!this.interactionReady || !this.hasUserGesture()) {
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
                genre: '',
                social_links: {},
                episode: null,
            };

            try {
                const id = Number(programId || this.track.program_id || this.program?.id || 0);
                const url = id > 0
                    ? `${this.programInfoUrl}?program_id=${encodeURIComponent(id)}`
                    : this.programInfoUrl;

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (payload?.success && payload?.data) {
                    const nextProgramInfo = {
                        ...this.programInfo,
                        ...payload.data,
                    };

                    nextProgramInfo.cover = String(payload.data.cover || '').trim() || nextProgramInfo.cover || this.track.cover || this.fallbackCover;
                    nextProgramInfo.social_links = {
                        ...(this.programInfo?.social_links || {}),
                        ...(payload.data.social_links || {}),
                    };
                    nextProgramInfo.episode = payload.data.episode ?? this.programInfo?.episode ?? null;

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

            return {
                title: track.title || this.bandPanel.title || '',
                artist: track.artist || this.bandPanel.artist || '',
                info: track.band_info || this.bandPanel.info || track.comment || '',
                biography: track.band_biography || this.bandPanel.biography || '',
                biographySource: track.band_biography_source || this.bandPanel.biographySource || '',
                bioCover: track.band_thumbnail || this.bandPanel.cover || track.cover || this.fallbackCover,
                trackCover: track.cover || this.fallbackCover,
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
            return this.cleanCover(this.bandWindowView.trackCover, this.logoUrl);
        },

        get bandWindowView() {
            return this.bandWindowSnapshot || this.buildBandWindowSnapshot();
        },

        get biographySourceLabel() {
            return this.formatBiographySourceLabel(this.bandWindowView.biographySource || '');
        },

        get nextTrackRemainingSeconds() {
            if (this.track.is_live || this.progress.duration <= 0) {
                return Number.POSITIVE_INFINITY;
            }

            return Math.max(0, Math.round(this.progress.duration - this.progress.elapsed));
        },

        get showNextTrackWidget() {
            return !this.track.is_live
                && this.progress.duration > 0
                && this.nextTrackRemainingSeconds > 0
                && this.nextTrackRemainingSeconds <= this.nextTrackThresholdSeconds;
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
            const bind = () => {
                const root = document.getElementById(this.widgetIds.root);
                const cover = document.getElementById(this.widgetIds.cover);
                const timerElapsed = document.getElementById(this.widgetIds.timerElapsed);
                const timerRemaining = document.getElementById(this.widgetIds.timerRemaining);

                if (!root && !cover && !timerElapsed && !timerRemaining) {
                    if (this.widgetRetryHandle) {
                        clearTimeout(this.widgetRetryHandle);
                    }

                    this.widgetRetryHandle = setTimeout(bind, 500);
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
            };
        },

        syncTrackFromWidget(force = false) {
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
                this.progress.elapsed = widgetTrack.elapsed;
            }

            if (widgetTrack.duration > 0) {
                this.progress.duration = widgetTrack.duration;
            }

            if (previousSignature !== nextSignature) {
                this.progress.elapsed = 0;
                if (widgetTrack.duration > 0) {
                    this.progress.duration = widgetTrack.duration;
                } else {
                    this.progress.duration = 0;
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

        async refreshStatus(silent = false, skipBandEnrichment = false) {
            if (this.statusInFlight) {
                return;
            }

            this.statusInFlight = true;

            try {
                const response = await fetch(`${this.statusUrl}?t=${Date.now()}`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (!payload?.success || !payload?.data) {
                    throw new Error('Invalid status payload');
                }

                this.applyStatus(payload.data, silent, this.resolveWidgetTrack());
                this.currentStreamIndex = 0;
                this.loading = false;
            } catch (error) {
                if (!silent) {
                    this.toastMessage('No se pudo actualizar el reproductor');
                }
                this.loading = false;
            } finally {
                this.statusInFlight = false;
            }
        },

        applyStatus(data, silent = false, widgetTrack = null) {
            const previousSignature = this.track.signature;
            const track = data.track || {};
            const widgetTitle = widgetTrack?.title || '';
            const widgetArtist = widgetTrack?.artist || '';
            const widgetCover = widgetTrack?.cover || '';
            const currentTitle = this.track.title && this.track.title !== this.defaultTitle ? this.track.title : '';
            const currentArtist = this.track.artist && this.track.artist !== this.defaultArtist ? this.track.artist : '';
            const nextTitle = this.normalizeTrackTitle(widgetTitle || track.title || currentTitle || this.defaultTitle || '');
            const nextArtist = this.normalizeBandArtist(widgetArtist || track.artist || currentArtist || this.defaultArtist || '');
            const tempBuster = nextArtist + '|' + nextTitle;

            let nextCoverCandidate = this.fallbackCover;
            if (track.cover && !isFallbackImage(track.cover, this.fallbackCover, this.logoUrl)) {
                nextCoverCandidate = track.cover;
            } else if (this.track && this.track.cover && !isFallbackImage(this.track.cover, this.fallbackCover, this.logoUrl) && nextArtist === this.track.artist && nextTitle === this.track.title) {
                nextCoverCandidate = this.track.cover;
            } else if (widgetCover && !isFallbackImage(widgetCover, this.fallbackCover, this.logoUrl)) {
                nextCoverCandidate = widgetCover;
            } else {
                nextCoverCandidate = widgetCover || track.cover || this.track.cover || this.fallbackCover;
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

            this.track = {
                ...this.track,
                ...track,
                title: nextTitle,
                artist: nextArtist,
                cover: nextCover,
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
                audio_url: track.audio_url || this.track.audio_url || '',
                is_live: track.is_live ?? true,
                program_name: track.program_name || data.program_name || this.track.program_name || '',
                program_description: track.program_description || data.program_description || data.program?.description || this.track.program_description || '',
                program_host: track.program_host || '',
                program_schedule: track.program_schedule || '',
                program_id: track.program_id || data.program_id || null,
                es_bloque_programa: track.es_bloque_programa ?? data.es_bloque_programa ?? false,
            };

            if ((trackChanged || !this.favoriteSyncReady) && this.track.signature) {
                void this.syncFavorites(this.track.signature || '', !this.favoriteSyncReady);
            }

            if (trackChanged) {
                this.bandLookupArtist = '';
                this.bandPanel = this.bandWindowOpen
                    ? {
                        ...this.bandPanel,
                        title: this.track.title || this.bandPanel.title || '',
                        artist: this.track.artist || this.bandPanel.artist || '',
                        info: this.track.band_info || this.track.comment || this.bandPanel.info || '',
                        biography: this.track.band_biography || this.bandPanel.biography || '',
                        biographySource: this.track.band_biography_source || this.bandPanel.biographySource || '',
                        cover: this.track.band_thumbnail || this.track.cover || this.bandPanel.cover || this.fallbackCover,
                        foundedLabel: this.track.band_founded_label || this.bandPanel.foundedLabel || '',
                        facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : (this.bandPanel.facts || []),
                    }
                    : {
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

            this.queueWidgetSync(trackChanged ? 0 : 200);

            if (!skipBandEnrichment && (this.normalizeBandArtist(this.track.artist) || this.normalizeTrackTitle(this.track.title)) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            this.program = data.program || null;
            this.nextProgram = data.next_program || null;
            this.queue = (data.queue || []).slice(0, 5);
            this.notices = (data.notices || []).slice(0, 6);
            this.history = (data.history || []).slice(0, this.historyLimit);
            this.listeners = Number(data.listeners || this.track.listeners || 0);

            const widgetDuration = Number(widgetTrack?.duration || 0);
            const widgetElapsed = Number(widgetTrack?.elapsed || 0);
            const audio = this.$refs.audio;
            const audioDuration = audio && Number.isFinite(audio.duration) && audio.duration > 0
                ? Math.round(audio.duration)
                : 0;
            const audioElapsed = audio && Number.isFinite(audio.currentTime) && audio.currentTime >= 0
                ? Math.round(audio.currentTime)
                : 0;

            this.progress.duration = this.track.is_live
                ? 0
                : (audioDuration || widgetDuration || Number(track.duration_seconds || 0));
            this.progress.elapsed = audioElapsed || (widgetElapsed > 0
                ? widgetElapsed
                : (trackChanged ? 0 : Number(track.elapsed_seconds || 0)));

            if (!this.track.is_live && this.progress.duration > 0 && this.progress.elapsed > this.progress.duration) {
                this.progress.elapsed = 0;
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
                duration: timer.duration || parsed.duration,
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
            const duration = this.parseWidgetDuration(durationText);

            if (elapsed > 0 || duration > 0) {
                return { elapsed, duration };
            }

            const rawText = this.cleanWidgetText(timerRoot?.innerText || timerRoot?.textContent || '');
            const matches = Array.from(rawText.matchAll(/\b(\d{1,3}:\d{2})\b/g), (match) => match[1]);
            return {
                elapsed: this.parseWidgetDuration(matches[0] || ''),
                duration: this.parseWidgetDuration(matches[1] || ''),
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
            const text = this.cleanWidgetText(value);
            if (!text || !/^\d{1,3}:\d{2}$/.test(text)) {
                return 0;
            }

            const [minutes, seconds] = text.split(':').map((part) => Number(part));
            if (!Number.isFinite(minutes) || !Number.isFinite(seconds)) {
                return 0;
            }

            return (minutes * 60) + seconds;
        },

        splitWidgetArtistTitle(value) {
            const text = this.cleanWidgetText(value);
            if (!text) {
                return { title: '', artist: '' };
            }

            const parts = text.includes(' - ') ? text.split(' - ') : [];
            if (parts.length >= 2) {
                const artist = this.cleanWidgetText(parts[parts.length - 1] || '');
                const title = this.cleanWidgetText(parts.slice(0, -1).join(' - '));
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

            return ['Seven Rock Radio', 'Transmisión oficial']
                .some((needle) => cleaned.toLowerCase() === needle.toLowerCase())
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
            if (!text || text === '...' || text === '&nbsp;') {
                return '';
            }

            return text;
        },

        tickProgress() {
            if (!this.playing) {
                return;
            }

            const audio = this.$refs.audio;
            if (audio && Number.isFinite(audio.currentTime) && audio.currentTime > 0) {
                this.progress.elapsed = Math.round(audio.currentTime);
            } else {
                this.progress.elapsed = Math.max(0, Math.round(this.progress.elapsed) + 1);
            }

            this.syncProgress();
        },

        syncProgress() {
            if (this.progress.duration <= 0) {
                this.progress.ratio = 0;
                return;
            }

            this.progress.ratio = Math.max(0, Math.min(100, Math.round((this.progress.elapsed / this.progress.duration) * 100)));
        },

        seek(event) {
            const audio = this.$refs.audio;
            if (!audio || this.progress.duration <= 0) {
                return;
            }

            const rect = event.currentTarget.getBoundingClientRect();
            const ratio = (event.clientX - rect.left) / rect.width;
            const clamped = Math.max(0, Math.min(1, ratio));
            this.progress.elapsed = Math.round(this.progress.duration * clamped);
            this.syncProgress();

            if (this.track.audio_url && Number.isFinite(audio.duration) && audio.duration > 0) {
                audio.currentTime = audio.duration * clamped;
            }
        },

        formatTime(seconds) {
            const safe = Math.max(0, Number(seconds) || 0);
            const minutes = String(Math.floor(safe / 60)).padStart(2, '0');
            const secs = String(Math.floor(safe % 60)).padStart(2, '0');
            return `${minutes}:${secs}`;
        },

        setTab(tab) {
            this.activeTab = tab;
            safeWrite('sr-player-tab', tab);
        },

        programText() {
            if (!this.program) {
                return 'No hay programa activo.';
            }

            const parts = [
                this.program.name,
                this.program.host,
                this.program.schedule,
                this.program.description,
            ].filter(Boolean);

            return parts.join(' · ');
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

        shareContext() {
            const title = this.track.title || this.defaultTitle || 'Seven Rock Radio';
            const artist = this.track.artist || this.defaultArtist || '';
            const program = this.track.program_name || '';
            const cover = this.track.cover || this.fallbackCover || '';
            const baseUrl = document.referrer || window.location.href;
            const version = this.track.signature || this.track.audio_url || this.track.program_id || '';
            const url = version ? `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}v=${encodeURIComponent(String(version))}` : baseUrl;
            const textParts = [
                `Estoy escuchando "${title}"${artist ? ` de ${artist}` : ''} en Seven Rock Radio.`,
            ];

            if (program) {
                textParts.push(`Programa: ${program}.`);
            }

            if (cover) {
                textParts.push(`Carátula: ${cover}.`);
            }

            textParts.push(url);
            const text = textParts.join(' ').replace(/\s{2,}/g, ' ').trim();

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

            const title = track.title && track.title !== this.defaultTitle ? track.title : 'Seven Rock Radio';
            const artist = track.artist && track.artist !== this.defaultArtist ? track.artist : 'En vivo';
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
    }));
}
