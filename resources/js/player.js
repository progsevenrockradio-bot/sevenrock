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
        fallbackCover: options.fallbackCover || '',
        pollInterval: Number(options.pollInterval || 5),
        historyLimit: Number(options.historyLimit || 10),
        defaultArtist: options.defaultArtist || '',
        defaultTitle: options.defaultTitle || '',
        panelOpen: false,
        bandWindowOpen: false,
        bandWindowTab: 'lyrics',
        dockMinimized: false,
        activeTab: 'lyrics',
        playing: false,
        loading: true,
        muted: safeRead('sr-player-muted', '0') === '1',
        volume: Number(safeRead('sr-player-volume', '0.8')) || 0.8,
        favorites: (() => {
            try {
                return JSON.parse(safeRead('sr-player-favorites', '[]'));
            } catch (error) {
                return [];
            }
        })(),
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
            band_thumbnail: '',
            comment: '',
            band_members: [],
            social_links: [],
            audio_url: '',
            is_live: true,
            signature: '',
            program_name: '',
            program_host: '',
            program_schedule: '',
            program_id: null,
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
            cover: '',
            foundedLabel: '',
            facts: [],
        },
        streamCandidates: [],
        currentStreamIndex: 0,
        widgetIds: {
            cover: 'rbcloud_np_c1266',
            artist: 'rbcloud_np_a1266',
            title: 'rbcloud_np_t1266',
        },
        toast: {
            visible: false,
            message: '',
        },
        pollHandle: null,
        progressHandle: null,
        boundHotkeys: null,
        widgetObserver: null,
        widgetSyncHandle: null,
        widgetRetryHandle: null,
        toastHandle: null,
        statusInFlight: false,
        statusSyncHandle: null,

        init() {
            this.panelOpen = this.mode === 'page' ? true : false;
            this.bandWindowOpen = false;
            this.bandWindowTab = 'lyrics';
            this.dockMinimized = false;
            this.activeTab = safeRead('sr-player-tab', 'lyrics');
            this.toast.visible = false;
            this.toast.message = '';
            this.applyAudioPreferences();
            this.bindAudioEvents();
            this.bindNavigationGuards();
            this.watchNowPlayingWidget();
            this.queueStatusRefresh(0);

            this.pollHandle = setInterval(() => this.queueStatusRefresh(0), Math.max(3, this.pollInterval) * 1000);
            this.progressHandle = setInterval(() => this.tickProgress(), 1000);

            this.boundHotkeys = (event) => this.handleHotkeys(event);
            window.addEventListener('keydown', this.boundHotkeys);
        },

        destroy() {
            if (this.pollHandle) {
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
                if (event.persisted) {
                    this.closeTransientOverlays();
                }
            };

            this.beforeUnloadGuard = () => {
                this.closeTransientOverlays();
            };

            document.addEventListener('click', this.navigationGuard, true);
            window.addEventListener('pageshow', this.pageShowGuard);
            window.addEventListener('beforeunload', this.beforeUnloadGuard);
        },

        closeTransientOverlays() {
            this.bandWindowOpen = false;
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

                audio.addEventListener('play', () => {
                    this.playing = true;
                });

                audio.addEventListener('pause', () => {
                    this.playing = false;
                });

                audio.addEventListener('waiting', () => {
                    this.loading = true;
                });

                audio.addEventListener('canplay', () => {
                    this.loading = false;
                });

                audio.addEventListener('timeupdate', () => {
                    if (Number.isFinite(audio.currentTime)) {
                        this.progress.elapsed = Math.min(Math.round(audio.currentTime), this.progress.duration || Math.round(audio.currentTime));
                        this.syncProgress();
                    }
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

            if (this.mode === 'popup') {
                this.panelOpen = !this.panelOpen;
                safeWrite('sr-player-expanded', this.panelOpen ? '1' : '0');
                return;
            }

            this.bandWindowOpen = !this.bandWindowOpen;
            if (this.bandWindowOpen) {
                this.ensureBandInfo();
            }
        },

        async openBandWindow() {
            await this.refreshStatus(true);
            this.bandPanel = {
                title: this.track.title || '',
                artist: this.track.artist || '',
                info: this.track.band_info || this.track.comment || '',
                cover: this.track.band_thumbnail || this.track.cover || this.fallbackCover,
                foundedLabel: this.track.band_founded_label || '',
                facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : [],
            };
            this.bandWindowOpen = true;
            this.bandWindowTab = 'lyrics';
            this.queueWidgetSync(0);
            await this.ensureBandInfo();
            this.bandPanel = {
                title: this.track.title || this.bandPanel.title || '',
                artist: this.track.artist || this.bandPanel.artist || '',
                info: this.track.band_info || this.track.comment || this.bandPanel.info || '',
                cover: this.track.band_thumbnail || this.track.cover || this.bandPanel.cover || this.fallbackCover,
                foundedLabel: this.track.band_founded_label || this.bandPanel.foundedLabel || '',
                facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : (this.bandPanel.facts || []),
            };
        },

        closeBandWindow() {
            this.bandWindowOpen = false;
        },

        setBandWindowTab(tab) {
            this.bandWindowTab = tab;
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
                const cover = document.getElementById(this.widgetIds.cover);
                const artist = document.getElementById(this.widgetIds.artist);
                const title = document.getElementById(this.widgetIds.title);

                if (!cover && !artist && !title) {
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

                [cover, artist, title]
                    .filter(Boolean)
                    .forEach((element) => {
                        this.widgetObserver.observe(element, {
                            attributes: true,
                            attributeFilter: ['src'],
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
            const nextCover = widgetTrack.cover || this.fallbackCover;
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

            if (previousSignature !== nextSignature) {
                this.progress.elapsed = 0;
                this.progress.duration = 0;
                this.bandLookupArtist = '';
                this.bandPanel = {
                    title: this.track.title || '',
                    artist: this.track.artist || '',
                    info: this.track.band_info || this.track.comment || '',
                    cover: this.track.cover || this.fallbackCover,
                    foundedLabel: this.track.band_founded_label || '',
                    facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : [],
                };
            }

            this.syncProgress();
            this.ensureAudioSource();

            if ((this.normalizeBandArtist(this.track.artist) || this.normalizeTrackTitle(this.track.title)) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            return true;
        },

        async ensureBandInfo() {
            const artist = this.normalizeBandArtist(this.track.artist || '');
            const title = this.normalizeTrackTitle(this.track.title || '');

            if (!artist && !title) {
                return;
            }

            const lookupKey = [artist || '', title || ''].join('|');
            if (this.bandLookupArtist === lookupKey && !this.needsBandEnrichment()) {
                return;
            }

            this.bandLookupArtist = lookupKey;

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
                    band_thumbnail: data.thumbnail || this.track.band_thumbnail || this.track.cover || this.fallbackCover,
                    lyrics: data.lyrics || this.track.lyrics || '',
                    social_links: Array.isArray(data.social_links) ? data.social_links : this.track.social_links,
                    band_founded_year: data.formed_year ?? this.track.band_founded_year ?? null,
                    band_founded_label: data.formed_label || this.track.band_founded_label || '',
                    band_facts: Array.isArray(data.facts) ? data.facts : (this.track.band_facts || []),
                };
                this.bandPanel = {
                    title: this.bandPanel.title || this.track.title || '',
                    artist: this.bandPanel.artist || this.track.artist || '',
                    info: this.track.band_info || this.bandPanel.info || '',
                    cover: this.track.band_thumbnail || this.track.cover || this.bandPanel.cover || this.fallbackCover,
                    foundedLabel: this.track.band_founded_label || this.bandPanel.foundedLabel || '',
                    facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : (this.bandPanel.facts || []),
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

        async refreshStatus(silent = false) {
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
            const nextSignature = track.signature || this.buildSignature({
                title: widgetTitle || track.title || currentTitle || this.defaultTitle || '',
                artist: widgetArtist || track.artist || currentArtist || this.defaultArtist || '',
                cover: widgetCover || track.cover || this.track.cover || this.fallbackCover,
                program: track.program_name || '',
            });
            const trackChanged = Boolean(previousSignature && previousSignature !== nextSignature);
            const nextBandInfo = this.formatBandText(track.band_info || track.comment || '');
            const nextLyrics = typeof track.lyrics === 'string' ? track.lyrics : '';
            const nextThumbnail = track.band_thumbnail || '';
            const nextFacts = Array.isArray(track.band_facts) ? track.band_facts : [];
            const nextLinks = Array.isArray(track.social_links) ? track.social_links : [];

            this.track = {
                ...this.track,
                ...track,
                title: this.normalizeTrackTitle(widgetTitle || track.title || currentTitle || this.defaultTitle || ''),
                artist: this.normalizeBandArtist(widgetArtist || track.artist || currentArtist || this.defaultArtist || ''),
                cover: widgetCover || track.cover || this.track.cover || this.fallbackCover,
                lyrics: trackChanged ? nextLyrics : (nextLyrics || this.track.lyrics || ''),
                band_info: trackChanged ? nextBandInfo : (nextBandInfo || this.track.band_info || ''),
                band_thumbnail: trackChanged ? nextThumbnail : (nextThumbnail || this.track.band_thumbnail || ''),
                band_founded_year: track.band_founded_year ?? this.track.band_founded_year ?? null,
                band_founded_label: track.band_founded_label || this.track.band_founded_label || '',
                band_facts: trackChanged ? nextFacts : (nextFacts.length ? nextFacts : (this.track.band_facts || [])),
                comment: track.comment || '',
                band_members: Array.isArray(track.band_members) ? track.band_members : [],
                social_links: nextLinks.length ? nextLinks : [],
                audio_url: track.audio_url || this.track.audio_url || '',
                is_live: track.is_live ?? true,
                program_name: track.program_name || '',
                program_host: track.program_host || '',
                program_schedule: track.program_schedule || '',
                program_id: track.program_id || null,
            };

            if (trackChanged) {
                this.bandLookupArtist = '';
                this.bandPanel = {
                    title: '',
                    artist: '',
                    info: '',
                    cover: this.track.cover || this.fallbackCover,
                    foundedLabel: '',
                    facts: [],
                };
            }

            this.queueWidgetSync(trackChanged ? 0 : 200);

            if ((this.normalizeBandArtist(this.track.artist) || this.normalizeTrackTitle(this.track.title)) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            this.program = data.program || null;
            this.nextProgram = data.next_program || null;
            this.queue = (data.queue || []).slice(0, 5);
            this.notices = (data.notices || []).slice(0, 6);
            this.history = (data.history || []).slice(0, this.historyLimit);
            this.listeners = Number(data.listeners || this.track.listeners || 0);

            this.progress.duration = Number(track.duration_seconds || 0);
            this.progress.elapsed = trackChanged ? 0 : Number(track.elapsed_seconds || 0);
            if (this.progress.duration > 0 && this.progress.elapsed > this.progress.duration) {
                this.progress.elapsed = 0;
            }
            this.syncProgress();
            this.ensureAudioSource();

            if (!silent && previousSignature && previousSignature !== this.track.signature) {
                this.toastMessage(`Ahora suena: ${this.track.title}`);
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
            const cover = document.getElementById(this.widgetIds.cover);
            const artist = document.getElementById(this.widgetIds.artist);
            const title = document.getElementById(this.widgetIds.title);

            return {
                cover: cover?.getAttribute('src') || '',
                artist: this.cleanWidgetText(artist?.textContent || ''),
                title: this.cleanWidgetText(title?.textContent || ''),
            };
        },

        inferWidgetTrack(widgetTrack = {}) {
            const title = this.cleanWidgetText(widgetTrack.title || '');
            const artist = this.normalizeBandArtist(widgetTrack.artist || '');

            if (artist) {
                return { title, artist, cover: widgetTrack.cover || '' };
            }

            const parts = title.includes(' - ') ? title.split(' - ') : [];
            if (parts.length >= 2) {
                return {
                    title: this.cleanWidgetText(parts[0]),
                    artist: this.normalizeBandArtist(parts.slice(1).join(' - ')),
                    cover: widgetTrack.cover || '',
                };
            }

            return { title, artist: '', cover: widgetTrack.cover || '' };
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

        toggleFavorite() {
            if (!this.track.signature) {
                return;
            }

            if (this.isFavoriteCurrent()) {
                this.favorites = this.favorites.filter((item) => item.signature !== this.track.signature);
                this.toastMessage('Favorito eliminado');
            } else {
                this.favorites = [
                    ...this.favorites,
                    {
                        signature: this.track.signature,
                        title: this.track.title,
                        artist: this.track.artist,
                        cover: this.track.cover,
                    },
                ].slice(-50);
                this.toastMessage('Añadido a favoritos');
            }

            safeWrite('sr-player-favorites', JSON.stringify(this.favorites));
        },

        async shareCurrent() {
            const text = `${this.track.title || this.defaultTitle} - ${this.track.artist || this.defaultArtist}`;
            const url = window.location.href;

            if (navigator.share) {
                try {
                    await navigator.share({ title: text, text, url });
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

        bandLinks() {
            if (Array.isArray(this.track.social_links) && this.track.social_links.length) {
                return this.track.social_links
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
            const members = Array.isArray(this.bandPanel.lineup) && this.bandPanel.lineup.length
                ? this.bandPanel.lineup
                : (Array.isArray(this.track.band_members) ? this.track.band_members : []);

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
    }));
}
