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
        pollInterval: Number(options.pollInterval || 10),
        historyLimit: Number(options.historyLimit || 10),
        defaultArtist: '',
        defaultTitle: '',
        panelOpen: false,
        bandWindowOpen: false,
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

        init() {
            this.panelOpen = this.mode === 'page' ? true : false;
            this.activeTab = safeRead('sr-player-tab', 'lyrics');
            this.toast.visible = false;
            this.toast.message = '';
            this.applyAudioPreferences();
            this.bindAudioEvents();
            this.watchNowPlayingWidget();
            this.refreshStatus(true);
            this.queueWidgetSync(1200);

            this.pollHandle = setInterval(() => this.refreshStatus(true), Math.max(5, this.pollInterval) * 1000);
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
            if (this.widgetSyncHandle) {
                clearTimeout(this.widgetSyncHandle);
            }
            if (this.boundHotkeys) {
                window.removeEventListener('keydown', this.boundHotkeys);
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
            const currentSource = audio.getAttribute('src') || '';

            if (currentSource !== source) {
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
                audio.pause();
                this.playing = false;
                this.toastMessage('Reproductor en pausa');
                return;
            }

            try {
                await audio.play();
                this.playing = true;
                this.toastMessage('Reproduciendo');
            } catch (error) {
                if (this.currentStreamIndex + 1 < this.streamCandidates.length) {
                    this.currentStreamIndex += 1;
                    this.ensureAudioSource();
                    try {
                        await audio.play();
                        this.playing = true;
                        this.toastMessage('Reproduciendo');
                        return;
                    } catch (fallbackError) {
                        // fall through
                    }
                }

                this.playing = false;
                this.toastMessage('No se pudo iniciar el audio');
            }
        },

        togglePlay() {
            this.attemptPlayWithFallback();
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

            this.syncTrackFromWidget(true);
            this.bandWindowOpen = !this.bandWindowOpen;
            if (this.bandWindowOpen) {
                this.ensureBandInfo();
            }
        },

        async openBandWindow() {
            this.syncTrackFromWidget(true);
            await this.refreshStatus(true);
            this.syncTrackFromWidget(true);
            const widgetTrack = this.inferWidgetTrack(this.readNowPlayingWidget());
            this.bandPanel = {
                title: this.track.title || widgetTrack.title || '',
                artist: this.track.artist || widgetTrack.artist || '',
                info: this.track.band_info || this.track.comment || '',
                cover: this.track.band_thumbnail || this.track.cover || widgetTrack.cover || this.fallbackCover,
                foundedLabel: this.track.band_founded_label || '',
                facts: Array.isArray(this.track.band_facts) ? this.track.band_facts : [],
            };
            this.bandWindowOpen = true;
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

        closePanel() {
            if (this.mode === 'popup') {
                this.panelOpen = false;
                safeWrite('sr-player-expanded', '0');
                return;
            }

            this.bandWindowOpen = false;
            this.dockMinimized = !this.dockMinimized;
        },

        queueWidgetSync(delay = 900) {
            if (this.widgetSyncHandle) {
                clearTimeout(this.widgetSyncHandle);
            }

            this.widgetSyncHandle = setTimeout(() => {
                this.syncTrackFromWidget(true);
                if (this.needsBandEnrichment()) {
                    this.ensureBandInfo();
                }
            }, Math.max(0, Number(delay) || 0));
        },

        watchNowPlayingWidget() {
            this.$nextTick(() => {
                const cover = document.getElementById(this.widgetIds.cover);
                const artist = document.getElementById(this.widgetIds.artist);
                const title = document.getElementById(this.widgetIds.title);

                if (!cover && !artist && !title) {
                    return;
                }

                const observer = new MutationObserver(() => {
                    this.queueWidgetSync(0);
                });

                [cover, artist, title].forEach((node) => {
                    if (node) {
                        observer.observe(node, {
                            characterData: true,
                            childList: true,
                            subtree: true,
                            attributes: true,
                        });
                    }
                });

                this.widgetObserver = observer;
            });
        },

        syncTrackFromWidget(force = false) {
            const widgetTrack = this.inferWidgetTrack(this.readNowPlayingWidget());
            const hasWidgetData = Boolean(widgetTrack.title || widgetTrack.artist || widgetTrack.cover);

            if (!hasWidgetData && !force) {
                return;
            }

            const currentTitle = this.track.title && this.track.title !== this.defaultTitle ? this.track.title : '';
            const currentArtist = this.track.artist && this.track.artist !== this.defaultArtist ? this.track.artist : '';
            const nextTitle = this.normalizeTrackTitle(widgetTrack.title || currentTitle || '');
            const nextArtist = this.normalizeBandArtist(widgetTrack.artist || currentArtist || '');
            const nextCover = widgetTrack.cover || this.track.cover || this.fallbackCover;

            this.track = {
                ...this.track,
                title: nextTitle || this.track.title || '',
                artist: nextArtist || this.track.artist || '',
                cover: nextCover,
            };
        },

        async ensureBandInfo() {
            const widgetTrack = this.readNowPlayingWidget();
            const inferredWidget = this.inferWidgetTrack(widgetTrack);
            const artist = this.normalizeBandArtist(
                (this.track.artist && this.track.artist !== this.defaultArtist ? this.track.artist : '') ||
                inferredWidget.artist ||
                widgetTrack.artist ||
                ''
            );
            if (!artist) {
                return;
            }

            if (this.bandLookupArtist === artist && !this.needsBandEnrichment()) {
                return;
            }

            this.bandLookupArtist = artist;

            try {
                const response = await fetch(`${this.bandInfoUrl}?artist=${encodeURIComponent(artist)}&title=${encodeURIComponent(this.track.title || '')}`, {
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
                    band_info: data.summary || this.track.band_info || this.track.comment || '',
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
            } finally {
                this.bandLookupArtist = artist;
            }
        },

        needsBandEnrichment() {
            const hasInfo = typeof this.track.band_info === 'string' && this.track.band_info.trim() !== '';
            const hasFounded = typeof this.track.band_founded_label === 'string' && this.track.band_founded_label.trim() !== '';
            const hasFacts = Array.isArray(this.track.band_facts) && this.track.band_facts.length > 0;
            const hasThumb = typeof this.track.band_thumbnail === 'string' && this.track.band_thumbnail.trim() !== '';

            return !hasInfo || !hasFounded || !hasFacts || !hasThumb;
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

                this.applyStatus(payload.data, silent);
                this.currentStreamIndex = 0;
                this.loading = false;
            } catch (error) {
                if (!silent) {
                    this.toastMessage('No se pudo actualizar el reproductor');
                }
                this.loading = false;
            }
        },

        applyStatus(data, silent = false) {
            const previousSignature = this.track.signature;
            const track = data.track || {};
            const widgetTrack = this.readNowPlayingWidget();
            const inferredWidget = this.inferWidgetTrack(widgetTrack);
            const currentTitle = this.track.title && this.track.title !== this.defaultTitle ? this.track.title : '';
            const currentArtist = this.track.artist && this.track.artist !== this.defaultArtist ? this.track.artist : '';

            this.track = {
                ...this.track,
                ...track,
                title: this.normalizeTrackTitle(track.title || inferredWidget.title || widgetTrack.title || currentTitle || ''),
                artist: this.normalizeBandArtist(track.artist || inferredWidget.artist || widgetTrack.artist || currentArtist || ''),
                cover: track.cover || widgetTrack.cover || this.fallbackCover,
                lyrics: track.lyrics || this.track.lyrics || '',
                band_info: track.band_info || track.comment || this.track.band_info || '',
                band_thumbnail: track.band_thumbnail || this.track.band_thumbnail || '',
                band_founded_year: track.band_founded_year ?? this.track.band_founded_year ?? null,
                band_founded_label: track.band_founded_label || this.track.band_founded_label || '',
                band_facts: Array.isArray(track.band_facts) ? track.band_facts : (this.track.band_facts || []),
                comment: track.comment || this.track.comment || '',
                band_members: track.band_members || this.track.band_members || [],
                social_links: track.social_links || this.track.social_links || [],
                audio_url: track.audio_url || this.track.audio_url || '',
                is_live: track.is_live ?? true,
                program_name: track.program_name || '',
                program_host: track.program_host || '',
                program_schedule: track.program_schedule || '',
                program_id: track.program_id || null,
            };

            this.syncTrackFromWidget();
            this.queueWidgetSync(1200);

            if (this.normalizeBandArtist(this.track.artist) && this.needsBandEnrichment()) {
                this.ensureBandInfo();
            }

            this.program = data.program || null;
            this.nextProgram = data.next_program || null;
            this.queue = (data.queue || []).slice(0, 5);
            this.notices = (data.notices || []).slice(0, 6);
            this.history = (data.history || []).slice(0, this.historyLimit);
            this.listeners = Number(data.listeners || this.track.listeners || 0);

            this.progress.duration = Number(track.duration_seconds || 0);
            this.progress.elapsed = Number(track.elapsed_seconds || 0);
            this.syncProgress();
            this.ensureAudioSource();

            if (!silent && previousSignature && previousSignature !== this.track.signature) {
                this.toastMessage(`Ahora suena: ${this.track.title}`);
            }
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

            return ['Seven Rock Radio', 'Transmisión oficial']
                .some((needle) => text.toLowerCase() === needle.toLowerCase())
                ? ''
                : text;
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
            if (!this.playing || this.progress.duration <= 0) {
                return;
            }

            const audio = this.$refs.audio;
            if (audio && Number.isFinite(audio.currentTime) && audio.currentTime > 0) {
                this.progress.elapsed = Math.min(Math.round(audio.currentTime), this.progress.duration);
            } else {
                this.progress.elapsed = Math.min(this.progress.elapsed + 1, this.progress.duration);
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
                this.panelOpen = false;
                safeWrite('sr-player-expanded', '0');
            }
        },

        toastMessage(message) {
            return;
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
    }));
}
