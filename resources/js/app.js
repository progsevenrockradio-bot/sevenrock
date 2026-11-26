import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import Uppy from '@uppy/core';
import AwsS3 from '@uppy/aws-s3';
import '@uppy/core/dist/style.css';
import { registerRadioPlayer } from './player.js';
import './analytics.js';

Alpine.plugin(focus);
Alpine.plugin(collapse);
window.Alpine = Alpine;
window.__srUserGesture = false;

const markUserGesture = () => {
    window.__srUserGesture = true;
};

window.addEventListener('pointerdown', markUserGesture, { once: true, passive: true });
window.addEventListener('keydown', markUserGesture, { once: true });

const DAY_MAP = {
    LUNES: 1,
    MARTES: 2,
    MIERCOLES: 3,
    JUEVES: 4,
    VIERNES: 5,
    SABADO: 6,
    DOMINGO: 7,
};

Alpine.data('rocksHero', (slides = []) => ({
    active: 0,
    slides,
    interval: null,
    init() {
        if (this.slides.length < 2) {
            return;
        }

        this.interval = setInterval(() => this.next(), 3000);
    },
    next() {
        this.active = (this.active + 1) % this.slides.length;
    },
    go(index) {
        this.active = index;
    },
}));

Alpine.data('rocksNav', () => ({
    open: false,
    searchOpen: false,
    sticky: false,
    init() {
        this.sticky = window.scrollY > 100;
        window.addEventListener('scroll', () => {
            this.sticky = window.scrollY > 100;
        }, { passive: true });
    },
}));

Alpine.data('backToTopButton', () => ({
    visible: false,
    threshold: 420,
    onScroll: null,
    init() {
        this.onScroll = () => {
            this.visible = window.scrollY > this.threshold;
        };

        this.onScroll();
        window.addEventListener('scroll', this.onScroll, { passive: true });
    },
    destroy() {
        if (this.onScroll) {
            window.removeEventListener('scroll', this.onScroll);
        }
    },
    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
}));

Alpine.data('galleryLightbox', (images = []) => ({
    images,
    active: 0,
    open: false,
    touchStartX: 0,
    get current() {
        return this.images[this.active] ?? { src: '', caption: '' };
    },
    show(index) {
        this.active = index;
        this.open = true;
        document.body.classList.add('lb-disable-scrolling');
        this.preloadAround();
    },
    close() {
        this.open = false;
        document.body.classList.remove('lb-disable-scrolling');
    },
    next() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active + 1) % this.images.length;
        this.preloadAround();
    },
    prev() {
        if (!this.images.length) {
            return;
        }

        this.active = (this.active - 1 + this.images.length) % this.images.length;
        this.preloadAround();
    },
    preloadAround() {
        [this.active - 1, this.active + 1].forEach((index) => {
            const image = this.images[(index + this.images.length) % this.images.length];
            if (image?.src) {
                const preload = new Image();
                preload.src = image.src;
            }
        });
    },
    swipeEnd(event) {
        const delta = event.changedTouches[0].clientX - this.touchStartX;
        if (Math.abs(delta) < 40) {
            return;
        }

        delta < 0 ? this.next() : this.prev();
    },
}));

Alpine.data('bandProfilePicker', (options = {}) => ({
    searchUrl: options.searchUrl ?? '',
    selectedId: options.selectedId ? String(options.selectedId) : '',
    selectedLabel: options.selectedLabel ?? '',
    minLength: Number.isFinite(Number(options.minLength)) ? Math.max(1, Number(options.minLength)) : 3,
    query: options.selectedLabel ?? '',
    results: [],
    activeIndex: -1,
    open: false,
    loading: false,
    timer: null,
    requestToken: 0,
    init() {
        this.query = this.selectedLabel || '';
    },
    onInput() {
        this.selectedId = '';
        this.selectedLabel = '';
        this.activeIndex = -1;
        this.scheduleSearch();
    },
    scheduleSearch() {
        clearTimeout(this.timer);

        if (this.query.trim().length < this.minLength) {
            this.results = [];
            this.open = false;
            this.activeIndex = -1;
            return;
        }

        this.timer = setTimeout(() => {
            this.search();
        }, 160);
    },
    async search() {
        if (!this.searchUrl) {
            return;
        }

        const token = ++this.requestToken;
        this.loading = true;

        try {
            const url = new URL(this.searchUrl, window.location.origin);
            url.searchParams.set('q', this.query.trim());

            this.open = true;
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();
            if (token !== this.requestToken) {
                return;
            }

            this.results = Array.isArray(payload?.data?.results) ? payload.data.results : [];
            this.activeIndex = this.results.length ? 0 : -1;
            this.open = true;
        } catch (error) {
            if (token === this.requestToken) {
                this.results = [];
                this.open = false;
                this.activeIndex = -1;
            }
        } finally {
            if (token === this.requestToken) {
                this.loading = false;
            }
        }
    },
    choose(result) {
        this.selectedId = String(result.id ?? '');
        this.selectedLabel = result.text ?? '';
        this.query = this.selectedLabel;
        this.results = [];
        this.open = false;
        this.activeIndex = -1;
    },
    clear() {
        this.selectedId = '';
        this.selectedLabel = '';
        this.query = '';
        this.results = [];
        this.open = false;
        this.activeIndex = -1;
    },
    focus() {
        if (this.results.length > 0) {
            this.open = true;
        }
    },
    closeSoon() {
        window.setTimeout(() => {
            this.open = false;
        }, 120);
    },
    move(delta) {
        if (!this.results.length) {
            return;
        }

        this.open = true;
        this.activeIndex = (this.activeIndex + delta + this.results.length) % this.results.length;
    },
    commitActive(event) {
        if (this.activeIndex < 0 || this.activeIndex >= this.results.length) {
            return;
        }

        event?.preventDefault?.();
        this.choose(this.results[this.activeIndex]);
    },
    handleEnter(event) {
        if (this.open && this.activeIndex >= 0 && this.activeIndex < this.results.length) {
            this.commitActive(event);
        }
    },
}));

Alpine.data('podcastUploadForm', (options = {}) => ({
    activeDay: options.initialDay ?? 'LUNES',
    activeTab: options.initialTab ?? 'multimedia',
    fecha_emision: options.initialDate ?? '',
    fechaEmisionManual: Boolean(options.initialDateManual ?? false),
    lastSuggestedDate: '',
    lastSuggestedLabel: '',
    lastSuggestedType: '',
    dateSuggestion: '',
    dateSuggestionType: '',
    uploading: false,
    canRetry: false,
    progress: 0,
    progressLabel: '0%',
    statusMessage: '',
    phaseLabel: 'Listo',
    phaseDetailLabel: '',
    errorMessages: [],
    downloading: false,
    uploadEtaLabel: '',
    fileSizeLabel: '',
    originalR2Error: null,
    attemptId: null,
    attemptStartTime: null,
    stallWatchdogTimer: null,
    activeXhr: null,
    activeAbortController: null,
    selectedFile: null,
    formToSubmit: null,
    pipelineAction: 'process',
    estimatedUploadRateBytesPerSec: Number(options.estimatedUploadRateBytesPerSec ?? 8 * 1024 * 1024),
    fieldLabelMap: {
        master_program_id: 'Programa maestro',
        live_title: 'Título del episodio',
        fecha_emision: 'Fecha de emisión',
        numero_episodio: 'Capítulo / episodio',
        archivo_mp3: 'Archivo MP3',
        imagen_episodio_url: 'URL de imagen',
        imagen_episodio_file: 'Archivo de imagen',
        biografia_invitado: 'Invitado',
        resena: 'Descripción / resumen',
    },
    logEvent(phase, event, extra = {}) {
        const now = new Date().toISOString();
        const duration = this.attemptStartTime ? Math.round(performance.now() - this.attemptStartTime) : 0;
        console.log(`[AudioUpload] [${now}] [${this.attemptId || 'none'}] Phase: ${phase} | Event: ${event} | Duration: ${duration}ms`, extra);
    },
    setPhase(phase, detail = '') {
        this.phaseLabel = phase;
        this.phaseDetailLabel = detail;
        this.logEvent(phase, 'phase_change', { detail });
    },
    startStallWatchdog(onStall) {
        this.clearStallWatchdog();
        this.stallWatchdogTimer = setTimeout(() => {
            this.logEvent(this.phaseLabel, 'stall_detected', { timeout_ms: 45000 });
            onStall();
        }, 45000);
    },
    touchProgress() {
        if (this.stallWatchdogTimer) {
            clearTimeout(this.stallWatchdogTimer);
            this.stallWatchdogTimer = setTimeout(() => {
                this.logEvent(this.phaseLabel, 'stall_detected', { timeout_ms: 45000 });
                this.handleStall();
            }, 45000);
        }
    },
    clearStallWatchdog() {
        if (this.stallWatchdogTimer) {
            clearTimeout(this.stallWatchdogTimer);
            this.stallWatchdogTimer = null;
        }
    },
    handleStall() {
        this.clearStallWatchdog();
        if (!this.uploading) return;

        if (this.phaseLabel === 'Transfiriendo' && this.uppy && !this.originalR2Error) {
            this.logEvent('Transfiriendo', 'r2_stalled_falling_back_to_chunks');
            this.originalR2Error = {
                message: 'Falta de progreso en Cloudflare R2 (más de 45s sin avance en la transferencia).',
                phase: 'r2_transfer',
                timestamp: new Date().toISOString(),
            };
            try {
                this.uppy.cancelAll();
            } catch (e) { }

            const file = this.selectedFile;
            if (file) {
                this.uploadByChunks(file);
            }
        } else {
            this.uploading = false;
            this.canRetry = true;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = 'La transferencia se detuvo por falta de respuesta del servidor (tiempo de espera agotado).';
            this.statusMessage = 'La conexión se interrumpió por falta de progreso. Puedes pulsar «Reintentar» para reanudar.';
            this.errorMessages = ['Falta de respuesta del servidor durante la transferencia (45s sin datos).'];
            this.uploadEtaLabel = '';
        }
    },
    cancelUpload() {
        if (!this.uploading) return;
        this.logEvent(this.phaseLabel, 'upload_cancelled_by_user');
        this.clearStallWatchdog();

        if (this.activeAbortController) {
            try { this.activeAbortController.abort(); } catch (e) { }
            this.activeAbortController = null;
        }
        if (this.activeXhr) {
            try { this.activeXhr.abort(); } catch (e) { }
            this.activeXhr = null;
        }
        if (this.uppy) {
            try { this.uppy.cancelAll(); } catch (e) { }
        }

        this.uploading = false;
        this.canRetry = true;
        this.phaseLabel = 'Cancelado';
        this.phaseDetailLabel = 'Subida cancelada por el usuario.';
        this.statusMessage = 'Subida cancelada. El archivo seleccionado se conserva. Pulsa «Reintentar» cuando desees reanudar.';
        this.uploadEtaLabel = '';
    },
    retryUpload() {
        if (this.uploading) return;
        this.logEvent('Retry', 'upload_retried_by_user');
        const form = this.formToSubmit || document.querySelector('form[action*="podcast-uploads"]');
        if (!form) return;

        this.errorMessages = [];
        this.originalR2Error = null;
        this.canRetry = false;
        this.progress = 0;
        this.progressLabel = '0%';

        this.submitFormProcess(form, this.pipelineAction || 'process');
    },
    engancharFichero(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        this.selectedFile = file;
        this.canRetry = false;
        this.errorMessages = [];
        this.originalR2Error = null;

        if (this.uppy) {
            this.uppy.cancelAll();
            try {
                this.uppy.addFile({
                    name: file.name,
                    type: file.type || 'audio/mpeg',
                    data: file,
                });
            } catch (e) {
                /* duplicado: ignorar */
            }
        }
        this.updateUploadEstimate(file);
    },
    init() {
        this.$nextTick(() => {
            this.syncDateFromActiveProgram();
        });

        this.$watch('activeDay', () => {
            this.$nextTick(() => {
                this.syncDateFromActiveProgram();
            });
        });

        try {
            this.uppy = new Uppy({
                autoProceed: false,
                restrictions: {
                    maxNumberOfFiles: 1,
                    allowedFileTypes: ['audio/mpeg', 'audio/mp3', 'audio/x-mp3', 'audio/mpg', 'audio/mpeg3', '.mp3'],
                }
            });

            this.uppy.use(AwsS3, {
                limit: 1,
                timeout: 0,
                getUploadParameters: (file) => {
                    const csrfToken = document.querySelector('input[name="_token"]')?.value
                        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || '';

                    this.activeAbortController = new AbortController();
                    const presignTimeout = setTimeout(() => {
                        this.activeAbortController?.abort();
                    }, 30000);

                    return fetch('/admin/podcast-uploads/presign', {
                        method: 'POST',
                        signal: this.activeAbortController.signal,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            filename: file.name,
                            contentType: file.type || 'audio/mpeg',
                            attempt_id: this.attemptId,
                        }),
                    })
                        .then((response) => {
                            clearTimeout(presignTimeout);
                            if (!response.ok) {
                                return response.json().then(errData => {
                                    throw new Error(errData?.error || `Error HTTP ${response.status} en autorización`);
                                }).catch(() => {
                                    throw new Error(`Fallo de autorización HTTP ${response.status}`);
                                });
                            }
                            return response.json();
                        })
                        .then((data) => {
                            this.uppy.setFileMeta(file.id, { r2Key: data.key });
                            const safeHeaders = {};
                            if (data.headers && typeof data.headers === 'object') {
                                for (const [hk, hv] of Object.entries(data.headers)) {
                                    if (hk.toLowerCase() !== 'host') {
                                        safeHeaders[hk] = hv;
                                    }
                                }
                            }
                            safeHeaders['Content-Type'] = data.headers?.['Content-Type'] || data.headers?.['content-type'] || file.type || 'audio/mpeg';

                            this.setPhase('Transfiriendo', `Subiendo archivo directamente a Cloudflare R2 (~${this.formatBytes(file.size)})...`);
                            this.startStallWatchdog(() => this.handleStall());

                            return {
                                url: data.url,
                                method: data.method || 'PUT',
                                headers: safeHeaders,
                                fields: data.fields || {},
                            };
                        })
                        .catch((err) => {
                            clearTimeout(presignTimeout);
                            this.logEvent('Preparando subida', 'presign_failed', { message: err.message });
                            throw err;
                        });
                },
            });

            this.uppy.on('upload-progress', (file, progress) => {
                this.touchProgress();
                const percent = Math.min(100, Math.max(0, Math.round((progress.bytesUploaded / progress.bytesTotal) * 100)));
                this.progress = percent;
                this.progressLabel = `${percent}%`;

                if (progress.bytesUploaded > 0) {
                    if (progress.eta) {
                        this.uploadEtaLabel = `Restan ~${this.formatDuration(progress.eta)}`;
                    } else if (percent >= 99) {
                        this.uploadEtaLabel = 'Finalizando transferencia...';
                    }
                }
            });

            this.uppy.on('upload-success', (file, response) => {
                this.clearStallWatchdog();
                this.logEvent('Transfiriendo', 'r2_upload_success', { r2Key: file.meta?.r2Key });
                const r2Key = file.meta?.r2Key;
                this.setPhase('Confirmando archivo', 'Registrando episodio y verificando archivo en el servidor...');
                this.submitFormWithR2Key(r2Key);
            });

            this.uppy.on('upload-error', (file, error, response) => {
                this.clearStallWatchdog();
                this.logEvent('Transfiriendo', 'r2_upload_error', { message: error?.message });
                console.warn('Uppy R2 upload error, falling back to chunked upload:', error);

                if (!this.originalR2Error) {
                    this.originalR2Error = {
                        message: error?.message || 'Error de conexión con Cloudflare R2.',
                        phase: 'r2_upload',
                        timestamp: new Date().toISOString(),
                    };
                }

                const rawFile = file?.data || this.selectedFile;
                if (rawFile && rawFile instanceof Blob) {
                    this.statusMessage = 'Subida directa a R2 interrumpida. Iniciando subida de seguridad por partes de 4 MB...';
                    this.setPhase('Transfiriendo', `Subida por fragmentos de 4 MB (Fallo R2: ${this.originalR2Error.message})`);
                    this.uploadByChunks(rawFile);
                } else {
                    this.uploading = false;
                    this.canRetry = true;
                    this.phaseLabel = 'Error';
                    this.phaseDetailLabel = 'La subida a Cloudflare R2 falló.';
                    this.statusMessage = 'La subida falló: ' + (error?.message || 'Error de red.');
                    this.errorMessages = [error?.message || 'Error de red en Cloudflare R2.'];
                    this.uploadEtaLabel = '';
                }
            });
        } catch (err) {
            console.error('Error al inicializar Uppy:', err);
            this.uppy = null;
        }
    },
    markFechaEmisionManual() {
        if (this.fecha_emision && String(this.fecha_emision).trim() !== '') {
            this.fechaEmisionManual = true;
        }
    },
    restoreSuggestedDate() {
        if (this.lastSuggestedDate) {
            this.fechaEmisionManual = false;
            this.fecha_emision = this.lastSuggestedDate;
            this.dateSuggestion = this.lastSuggestedLabel;
            this.dateSuggestionType = this.lastSuggestedType;
        }
    },
    syncDateFromActiveProgram() {
        const form = this.$el?.tagName === 'FORM' ? this.$el : document.querySelector('form[action*="podcast-uploads"]');
        const select = this.getActiveProgramSelect(form);
        const selectedOption = select?.options?.[select.selectedIndex] ?? null;
        if (selectedOption && selectedOption.value) {
            this.applyProgramNextEmission(selectedOption);
        } else if (!this.fechaEmisionManual && !this.fecha_emision) {
            this.lastSuggestedDate = '';
            this.lastSuggestedLabel = '';
            this.lastSuggestedType = '';
            this.dateSuggestion = 'Selecciona un programa maestro para proponer su próxima emisión.';
            this.dateSuggestionType = 'future';
        }
    },
    applyProgramNextEmission(selectedOption) {
        if (!selectedOption || !selectedOption.value) {
            return;
        }

        const nextDate = selectedOption.getAttribute('data-next-emission-date') || '';
        const nextStatus = selectedOption.getAttribute('data-next-emission-status') || '';
        const nextLabel = selectedOption.getAttribute('data-next-emission-label') || '';
        const nextType = selectedOption.getAttribute('data-next-emission-type') || 'future';

        this.lastSuggestedDate = nextDate;
        this.lastSuggestedLabel = nextLabel;
        this.lastSuggestedType = nextType;
        this.dateSuggestion = nextLabel;
        this.dateSuggestionType = nextType;

        if (this.fechaEmisionManual) {
            // Nunca sobrescribir una fecha elegida manualmente por el usuario
            return;
        }

        if (nextStatus === 'ok' && nextDate) {
            this.fecha_emision = nextDate;
        } else {
            // Si falta configuración suficiente, no sustituir silenciosamente por hoy
            this.fecha_emision = '';
        }
    },
    updateEpisodeNumber(event) {
        const selectedOption = event.target.options[event.target.selectedIndex];
        const nextEpisode = selectedOption?.getAttribute('data-next-episode');
        const input = document.querySelector('input[name="numero_episodio"]');
        if (input) {
            input.value = nextEpisode || '';
        }
        this.applyProgramNextEmission(selectedOption);
    },
    getActiveProgramSelect(form) {
        if (!form) return null;
        if (this.activeDay) {
            const panel = form.querySelector(`[data-day-panel="${this.activeDay}"]`);
            const select = panel?.querySelector('select[name="master_program_id"]');
            if (select) {
                return select;
            }
        }
        return form.querySelector('select[name="master_program_id"]:not(:disabled)')
            || form.querySelector('[name="master_program_id"]:not(:disabled)')
            || form.querySelector('[name="master_program_id"]');
    },
    tabForField(field) {
        if (['archivo_mp3', 'imagen_episodio_url', 'imagen_episodio_file'].includes(field)) {
            return 'multimedia';
        }

        if (['sync_archive_org', 'download_processed_mp3'].includes(field)) {
            return 'distribution';
        }

        return 'editorial';
    },
    focusField(form, field) {
        if (!field) {
            return;
        }

        this.activeTab = this.tabForField(field);

        window.setTimeout(() => {
            let element;
            if (field === 'master_program_id') {
                element = this.getActiveProgramSelect(form);
            } else {
                const selector = `[name="${CSS.escape(field)}"]`;
                element = form.querySelector(selector);
            }

            if (element?.scrollIntoView) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (element?.focus) {
                element.focus({ preventScroll: true });
            }
        }, 50);
    },
    showValidationIssues(form, validationErrors = {}) {
        const messages = this.formatValidationErrors(validationErrors);
        const firstField = Object.keys(validationErrors ?? {})[0] ?? '';

        this.uploading = false;
        this.canRetry = true;
        this.progress = 0;
        this.progressLabel = '0%';
        this.phaseLabel = 'Validando';
        this.phaseDetailLabel = this.phaseDetailText();
        this.statusMessage = 'Faltan campos obligatorios. Revisa el formulario.';
        this.errorMessages = messages.length > 0 ? messages : ['Revisa los campos marcados.'];
        this.uploadEtaLabel = '';

        this.focusField(form, firstField);

        if (!firstField) {
            window.setTimeout(() => {
                form?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
    },
    phaseClass() {
        const label = String(this.phaseLabel ?? '').toLowerCase();

        if (label.includes('valid')) {
            return 'border-[#3b3b3b] bg-[rgba(255,255,255,.03)] text-[#c7c7c7]';
        }
        if (label.includes('preparando')) {
            return 'border-sky-800/50 bg-sky-950/30 text-sky-300';
        }
        if (label.includes('transf') || label.includes('subiend')) {
            return 'border-[#5a4315] bg-[rgba(118,86,22,.18)] text-[#f2d89b]';
        }
        if (label.includes('confirmando') || label.includes('guardando') || label.includes('uniendo')) {
            return 'border-indigo-800/50 bg-indigo-950/30 text-indigo-300';
        }
        if (label.includes('sincronizando')) {
            return 'border-[#31553f] bg-[rgba(30,76,49,.2)] text-[#b8e2c7]';
        }
        if (label.includes('cancel')) {
            return 'border-zinc-700 bg-zinc-800/50 text-zinc-300';
        }
        if (label.includes('error') || label.includes('fall')) {
            return 'border-[#5d2b2b] bg-[rgba(92,35,35,.28)] text-[#ffd0d0]';
        }
        if (label.includes('listo') || label.includes('complet')) {
            return 'border-[#2d5440] bg-[rgba(29,72,45,.18)] text-[#c8ead6]';
        }

        return 'border-[#2b2b2b] bg-[rgba(255,255,255,.03)] text-[#bdbdbd]';
    },
    phaseDetailText() {
        const phase = String(this.phaseLabel ?? '').toLowerCase();

        if (phase.includes('valid')) {
            return 'Validando campos obligatorios antes de iniciar la carga.';
        }
        if (phase.includes('preparando')) {
            return 'Preparando la subida y autorizando con el servidor.';
        }
        if (phase.includes('transf') || phase.includes('subiend')) {
            return 'Transfiriendo el archivo de audio MP3.';
        }
        if (phase.includes('confirmando') || phase.includes('guardando')) {
            return 'Confirmando archivo y registrando en la base de datos.';
        }
        if (phase.includes('procesando')) {
            return 'Escribiendo metadatos y enviando a RadioBOSS.';
        }
        if (phase.includes('sincronizando')) {
            return 'Sincronizando el episodio con Archive.org.';
        }
        if (phase.includes('cancel')) {
            return 'La transferencia fue detenida por el usuario.';
        }

        return '';
    },
    formatValidationErrors(validationErrors = {}) {
        return Object.entries(validationErrors).flatMap(([field, errors]) => {
            const label = this.fieldLabelMap[field] ?? field;

            return (Array.isArray(errors) ? errors : [errors])
                .filter(Boolean)
                .map((message) => {
                    const text = String(message).trim();

                    if (field === 'master_program_id' && /required/i.test(text)) {
                        return `${label}: debes seleccionar un programa maestro.`;
                    }

                    if (field === 'live_title' && /required/i.test(text)) {
                        return `${label}: este campo es obligatorio.`;
                    }

                    if (field === 'archivo_mp3' && /required/i.test(text)) {
                        return `${label}: debes seleccionar un MP3 para continuar.`;
                    }

                    return text
                        .replace(/^The\s+/i, '')
                        .replace(/\s+field is required\.?$/i, '')
                        ? `${label}: ${text}`
                        : `${label}: revisión pendiente`;
                });
        });
    },
    validateBeforeSubmit(form) {
        const issues = {};

        const programSelect = this.getActiveProgramSelect(form);
        const programId = programSelect?.value?.trim?.() ?? '';
        if (!programId) {
            issues.master_program_id = ['Debes seleccionar un programa maestro.'];
        }

        const liveTitle = form.querySelector('[name="live_title"]')?.value?.trim?.() ?? '';
        if (!liveTitle) {
            issues.live_title = ['El título del episodio es obligatorio.'];
        }

        const fechaEmision = form.querySelector('[name="fecha_emision"]')?.value?.trim?.() ?? '';
        if (!fechaEmision) {
            issues.fecha_emision = ['La fecha de emisión es obligatoria.'];
        }

        const input = form.querySelector('input[name="archivo_mp3"]');
        const file = input?.files?.[0] ?? this.selectedFile;
        if (!file) {
            issues.archivo_mp3 = ['Debes adjuntar el archivo MP3 del programa.'];
        }

        if (Object.keys(issues).length > 0) {
            this.showValidationIssues(form, issues);
            return null;
        }

        return file;
    },
    submit(event) {
        const form = event?.target;
        if (!form || this.uploading) {
            return;
        }

        event.preventDefault();
        const submitter = event?.submitter ?? null;
        const action = String(submitter?.value ?? 'process').toLowerCase();
        const pipelineAction = action === 'save' ? 'save' : 'process';

        this.submitFormProcess(form, pipelineAction);
    },
    submitFormProcess(form, pipelineAction) {
        this.phaseLabel = 'Validando';
        const audioFile = this.validateBeforeSubmit(form);
        if (!audioFile) {
            return;
        }
        this.selectedFile = audioFile;

        const pipelineInput = form.querySelector('input[name="pipeline_action"]');
        if (pipelineInput) {
            pipelineInput.value = pipelineAction;
        } else {
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="pipeline_action" value="${pipelineAction}" data-pipeline-action-field="1">`);
        }

        this.uploading = true;
        this.canRetry = false;
        this.downloading = false;
        this.progress = 0;
        this.progressLabel = '0%';
        this.attemptId = 'att_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        this.attemptStartTime = performance.now();
        this.originalR2Error = null;

        this.setPhase('Preparando subida', 'Verificando archivo y solicitando autorización...');
        this.statusMessage = 'Preparando subida...';
        this.errorMessages = [];
        this.uploadEtaLabel = this.uploadEtaLabel || 'Calculando tiempo estimado...';

        this.pipelineAction = pipelineAction;
        this.formToSubmit = form;

        // Asegurar que el archivo seleccionado esté cargado en Uppy
        if (this.uppy) {
            const input = form.querySelector('input[name="archivo_mp3"]');
            const file = input?.files?.[0] ?? this.selectedFile;
            if (file && this.uppy.getFiles().length === 0) {
                try {
                    this.uppy.addFile({ name: file.name, type: file.type || 'audio/mpeg', data: file });
                } catch (e) {
                    /* duplicado: ignorar */
                }
            }
        }

        if (this.uppy && this.uppy.getFiles().length > 0) {
            this.statusMessage = 'Subiendo directamente a Cloudflare R2...';
            this.setPhase('Preparando subida', 'Conectando con Cloudflare R2...');

            this.uppy.upload().catch((err) => {
                console.warn('Fallo al iniciar uppy.upload(), probando subida por partes:', err);
                if (!this.originalR2Error) {
                    this.originalR2Error = {
                        message: err?.message || 'Error al iniciar subida a R2',
                        phase: 'r2_init',
                        timestamp: new Date().toISOString(),
                    };
                }
                const targetFile = this.selectedFile || form.querySelector('input[name="archivo_mp3"]')?.files?.[0];
                if (targetFile) {
                    this.uploadByChunks(targetFile);
                }
            });
            return;
        }

        // Si Uppy no está activo, subida por fragmentos de seguridad directamente
        this.uploadByChunks(audioFile);
    },
    formatDuration(seconds) {
        const total = Math.max(0, Math.round(Number(seconds ?? 0)));
        const mins = Math.floor(total / 60);
        const secs = total % 60;

        if (mins <= 0) {
            return `${secs}s`;
        }

        return `${mins}m ${String(secs).padStart(2, '0')}s`;
    },
    formatBytes(bytes) {
        const value = Number(bytes ?? 0);
        if (value <= 0) {
            return '0 B';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        const exponent = Math.min(units.length - 1, Math.floor(Math.log(value) / Math.log(1024)));
        const amount = (value / Math.pow(1024, exponent)).toFixed(1);

        return `${amount} ${units[exponent]}`;
    },
    updateUploadEstimate(file) {
        if (!file?.size) {
            this.fileSizeLabel = '';
            this.uploadEtaLabel = '';
            return;
        }

        this.fileSizeLabel = this.formatBytes(file.size);
        const rate = Math.max(1024 * 1024, this.estimatedUploadRateBytesPerSec);
        const estimatedSeconds = Math.ceil(file.size / rate);
        this.uploadEtaLabel = `Tiempo estimado: ~${this.formatDuration(estimatedSeconds)}`;
    },
    async uploadByChunks(file) {
        if (!file) {
            file = this.selectedFile;
        }
        if (!file) {
            this.uploading = false;
            this.canRetry = true;
            return;
        }

        // Fragmentos de 4 MB respetan el límite de request body del servidor web de Hostinger (~6-7 MB)
        const CHUNK_SIZE = 4 * 1024 * 1024; // 4 MB
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const uploadId = 'chunk_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        const csrfToken = document.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '';

        this.uploading = true;
        this.canRetry = false;
        this.setPhase('Transfiriendo', this.originalR2Error
            ? `Subida alternativa por fragmentos de 4 MB (1/${totalChunks})`
            : `Subiendo por fragmentos de 4 MB (1/${totalChunks})...`);
        this.statusMessage = `Subiendo por partes de seguridad (1/${totalChunks})...`;

        try {
            for (let i = 0; i < totalChunks; i++) {
                if (!this.uploading) {
                    throw new Error('Subida cancelada por el usuario.');
                }

                const start = i * CHUNK_SIZE;
                const end = Math.min(file.size, start + CHUNK_SIZE);
                const chunkBlob = file.slice(start, end);

                const formData = new FormData();
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', i);
                formData.append('total_chunks', totalChunks);
                formData.append('file', chunkBlob, file.name);

                this.setPhase('Transfiriendo', `Enviando fragmento ${i + 1} de ${totalChunks} (~${this.formatBytes(chunkBlob.size)})...`);
                this.statusMessage = `Subiendo fragmento ${i + 1} de ${totalChunks}...`;

                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    this.activeXhr = xhr;

                    this.startStallWatchdog(() => {
                        xhr.abort();
                        reject(new Error(`Falta de respuesta del servidor durante el fragmento ${i + 1} (45s sin avance).`));
                    });

                    xhr.open('POST', '/admin/podcast-uploads/chunk', true);
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');

                    xhr.upload.onprogress = (e) => {
                        this.touchProgress();
                        if (e.lengthComputable) {
                            const chunkPercent = e.loaded / e.total;
                            const overallPercent = Math.min(99, Math.round(((i + chunkPercent) / totalChunks) * 100));
                            this.progress = overallPercent;
                            this.progressLabel = `${overallPercent}%`;
                        }
                    };

                    xhr.onload = () => {
                        this.clearStallWatchdog();
                        this.activeXhr = null;
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(xhr.responseText);
                        } else {
                            let serverErr = `Error HTTP ${xhr.status}`;
                            try {
                                const parsed = JSON.parse(xhr.responseText);
                                if (parsed?.error) serverErr = parsed.error;
                            } catch (e) { }
                            reject(new Error(`Error al subir el fragmento ${i + 1}: ${serverErr}`));
                        }
                    };

                    xhr.onerror = () => {
                        this.clearStallWatchdog();
                        this.activeXhr = null;
                        reject(new Error(`El navegador no expone la causa del fallo de red (conexión interrumpida o rechazada por el servidor en fragmento ${i + 1}).`));
                    };

                    xhr.onabort = () => {
                        this.clearStallWatchdog();
                        this.activeXhr = null;
                        reject(new Error('Transferencia cancelada.'));
                    };

                    xhr.send(formData);
                });
            }

            this.setPhase('Confirmando archivo', 'Ensamblando fragmentos en el servidor...');
            this.statusMessage = 'Uniendo fragmentos...';

            this.activeAbortController = new AbortController();
            const completeTimeout = setTimeout(() => {
                this.activeAbortController?.abort();
            }, 60000);

            const completeResponse = await fetch('/admin/podcast-uploads/chunk/complete', {
                method: 'POST',
                signal: this.activeAbortController.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    upload_id: uploadId,
                    total_chunks: totalChunks,
                    filename: file.name,
                }),
            });
            clearTimeout(completeTimeout);
            this.activeAbortController = null;

            if (!completeResponse.ok) {
                const errJson = await completeResponse.json().catch(() => ({}));
                throw new Error(errJson.error || `Error al completar la unión de fragmentos (HTTP ${completeResponse.status}).`);
            }

            const completeData = await completeResponse.json();
            this.setPhase('Confirmando archivo', 'Archivo transferido. Guardando episodio...');
            this.submitFormWithChunkedKey(completeData.chunked_key);

        } catch (error) {
            this.clearStallWatchdog();
            this.activeXhr = null;
            this.activeAbortController = null;
            console.error('Error en subida por partes:', error);

            this.uploading = false;
            this.canRetry = true;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = 'La subida por fragmentos falló.';
            this.statusMessage = error.message || 'Error durante la subida por fragmentos.';

            const errList = [error.message || 'Error de transferencia por fragmentos.'];
            if (this.originalR2Error) {
                errList.push(`Fallo previo en Cloudflare R2: ${this.originalR2Error.message}`);
            }
            this.errorMessages = errList;
            this.uploadEtaLabel = '';
        }
    },
    submitFormWithChunkedKey(chunkedKey) {
        this.statusMessage = 'Archivo transferido con éxito. Guardando episodio...';
        this.setPhase('Confirmando archivo', 'Registrando episodio en la base de datos...');

        const form = this.formToSubmit || document.querySelector('form[action*="podcast-uploads"]');
        const formData = new FormData(form);

        const activeProgramSelect = this.getActiveProgramSelect(form);
        const activeProgramId = activeProgramSelect?.value?.trim?.() ?? '';
        if (activeProgramId) {
            formData.set('master_program_id', activeProgramId);
        }

        formData.delete('archivo_mp3');
        formData.append('chunked_key', chunkedKey);
        formData.set('pipeline_action', this.pipelineAction || 'process');

        const xhr = new XMLHttpRequest();
        this.activeXhr = xhr;
        xhr.open(form.method || 'POST', form.action, true);
        xhr.responseType = 'text';
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

            this.activeXhr = null;
            this.uploading = false;

            let payload = null;
            try {
                payload = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                payload = null;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                this.progress = 100;
                this.progressLabel = '100%';
                this.statusMessage = payload?.status ?? 'Episodio procesado correctamente.';
                this.errorMessages = [];
                this.uploadEtaLabel = 'Transferencia completada.';

                if (payload?.pipeline_action === 'save') {
                    this.phaseLabel = 'Guardado';
                    this.phaseDetailLabel = 'Episodio creado sin iniciar el pipeline.';
                } else {
                    this.phaseLabel = 'Procesando RadioBOSS';
                    this.phaseDetailLabel = 'El archivo está siendo procesado en segundo plano.';
                }

                window.dispatchEvent(new CustomEvent('podcast-upload-complete', {
                    detail: {
                        status: payload?.status ?? 'Episodio procesado correctamente.',
                        redirectUrl: payload?.redirect_url ?? null,
                    },
                }));

                if (payload?.redirect_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.redirect_url;
                    }, 300);
                }

                return;
            }

            this.canRetry = true;
            const validationErrors = payload?.errors ?? {};
            if (xhr.status === 422 || Object.keys(validationErrors).length > 0) {
                this.showValidationIssues(form, validationErrors);
                return;
            }

            this.phaseLabel = 'Error';
            this.phaseDetailLabel = `Error HTTP ${xhr.status}`;
            this.statusMessage = 'No se pudo completar el registro.';
            this.errorMessages = payload?.message ? [payload.message] : [`Error HTTP ${xhr.status} al guardar episodio.`];
            this.uploadEtaLabel = '';
        };

        xhr.onerror = () => {
            this.activeXhr = null;
            this.uploading = false;
            this.canRetry = true;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = 'Error de red al guardar';
            this.statusMessage = 'No se pudo contactar al servidor tras transferir el archivo.';
            this.uploadEtaLabel = '';
        };

        xhr.send(formData);
    },
    submitFormWithR2Key(r2Key) {
        this.statusMessage = 'Archivo subido a R2. Guardando información del episodio...';
        this.setPhase('Confirmando archivo', 'Registrando episodio en la base de datos...');

        const form = this.formToSubmit || document.querySelector('form[action*="podcast-uploads"]');
        const formData = new FormData(form);

        const activeProgramSelect = this.getActiveProgramSelect(form);
        const activeProgramId = activeProgramSelect?.value?.trim?.() ?? '';
        if (activeProgramId) {
            formData.set('master_program_id', activeProgramId);
        }

        formData.delete('archivo_mp3');
        formData.append('r2_key', r2Key);
        formData.set('pipeline_action', this.pipelineAction || 'process');

        const xhr = new XMLHttpRequest();
        this.activeXhr = xhr;
        xhr.open(form.method || 'POST', form.action, true);
        xhr.responseType = 'text';
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

            this.activeXhr = null;
            this.uploading = false;

            let payload = null;
            try {
                payload = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                payload = null;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                this.progress = 100;
                this.progressLabel = '100%';
                this.statusMessage = payload?.status ?? 'Episodio procesado correctamente.';
                this.errorMessages = [];
                this.uploadEtaLabel = 'Transferencia completada.';

                if (payload?.pipeline_action === 'save') {
                    this.phaseLabel = 'Guardado';
                    this.phaseDetailLabel = 'Episodio creado sin iniciar el pipeline.';
                } else {
                    this.phaseLabel = 'Procesando RadioBOSS';
                    this.phaseDetailLabel = 'El archivo está siendo procesado en segundo plano.';
                }

                window.dispatchEvent(new CustomEvent('podcast-upload-complete', {
                    detail: {
                        status: payload?.status ?? 'Episodio procesado correctamente.',
                        redirectUrl: payload?.redirect_url ?? null,
                    },
                }));

                if (payload?.redirect_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.redirect_url;
                    }, 300);
                }

                return;
            }

            this.canRetry = true;
            const validationErrors = payload?.errors ?? {};
            if (xhr.status === 422 || Object.keys(validationErrors).length > 0) {
                this.showValidationIssues(form, validationErrors);
                return;
            }

            this.phaseLabel = 'Error';
            this.phaseDetailLabel = `Error HTTP ${xhr.status}`;
            this.statusMessage = 'No se pudo completar el registro.';
            this.errorMessages = payload?.message ? [payload.message] : [`Error HTTP ${xhr.status} al guardar episodio.`];
            this.uploadEtaLabel = '';
        };

        xhr.onerror = () => {
            this.activeXhr = null;
            this.uploading = false;
            this.canRetry = true;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = 'Error de red al guardar el episodio.';
            this.statusMessage = 'El registro falló por un error de red o del servidor.';
            this.uploadEtaLabel = '';
        };

        xhr.send(formData);
    },
}));

Alpine.data('podcastUploadsDashboard', (options = {}) => ({
    recentUrl: options.recentUrl ?? '',
    refreshInterval: Number.isFinite(Number(options.refreshInterval)) ? Math.max(5000, Number(options.refreshInterval)) : 15000,
    isRefreshing: false,
    lastUpdatedLabel: '',
    timer: null,
    requestToken: 0,
    init() {
        if (!this.recentUrl) {
            return;
        }

        this.refresh(false);
        this.startTimer();
    },
    destroy() {
        this.stopTimer();
    },
    handleUploadSuccess() {
        this.startPolling();
        this.refresh(true);
    },
    startPolling() {
        this.startTimer();
    },
    startTimer() {
        if (this.timer) {
            return;
        }

        this.timer = window.setInterval(() => {
            this.refresh(true);
        }, this.refreshInterval);
    },
    stopTimer() {
        if (!this.timer) {
            return;
        }

        window.clearInterval(this.timer);
        this.timer = null;
    },
    hasActiveEntries(root = document) {
        const nodes = root.querySelectorAll?.('[data-podcast-refresh-active="1"]') ?? [];
        return nodes.length > 0;
    },
    parseHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = html;
        return template.content;
    },
    renderHtml(html) {
        if (this.$refs.recentUploads) {
            const activeEl = document.activeElement;
            const hadFocusInside = this.$refs.recentUploads.contains(activeEl);
            const activeHref = activeEl?.getAttribute?.('href');
            const activeName = activeEl?.getAttribute?.('name');
            const activeVal = activeEl?.getAttribute?.('value');

            this.$refs.recentUploads.innerHTML = html;

            if (hadFocusInside && activeEl) {
                let targetToFocus = null;
                if (activeHref) {
                    targetToFocus = this.$refs.recentUploads.querySelector(`a[href="${CSS.escape(activeHref)}"]`);
                } else if (activeName && activeVal) {
                    targetToFocus = this.$refs.recentUploads.querySelector(`button[name="${CSS.escape(activeName)}"][value="${CSS.escape(activeVal)}"]`);
                }
                if (targetToFocus && typeof targetToFocus.focus === 'function') {
                    targetToFocus.focus({ preventScroll: true });
                }
            }
        }
    },
    async refresh(silent = false) {
        if (!this.recentUrl) {
            return;
        }

        if (this.isRefreshing) {
            return;
        }

        const token = ++this.requestToken;
        this.isRefreshing = true;

        try {
            const url = new URL(this.recentUrl, window.location.origin);
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.forEach((value, key) => {
                url.searchParams.set(key, value);
            });
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            if (token !== this.requestToken) {
                return;
            }

            const fragment = this.parseHtml(html);
            const hasActiveEntries = this.hasActiveEntries(fragment);
            this.renderHtml(html);

            const now = new Date();
            this.lastUpdatedLabel = `Actualizado: ${now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;

            if (!hasActiveEntries) {
                this.stopTimer();
            } else {
                this.startTimer();
            }
        } catch (error) {
            console.warn('podcastUploadsDashboard: no se pudo actualizar la lista de episodios.', error);
        } finally {
            if (token === this.requestToken) {
                this.isRefreshing = false;
            }
        }
    },
}));

Alpine.data('postContentEditor', (options = {}) => ({
    blocks: Array.isArray(options.initialBlocks) ? options.initialBlocks : [],
    uploadUrl: options.uploadUrl ?? '',
    uploading: false,
    uploadError: '',
    serialized: '',
    serializedBlocks: '',
    init() {
        if (!this.blocks.length) {
            this.blocks = [this.createBlock('paragraph')];
        } else {
            this.blocks = this.blocks.map((block) => this.normalizeBlock(block));
        }

        this.sync();
    },
    createBlock(type = 'paragraph') {
        return {
            id: `block-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            type,
            value: '',
            src: '',
            alt: '',
            cite: '',
            caption: '',
            items: [],
        };
    },
    normalizeBlock(block = {}) {
        const base = this.createBlock(block.type || 'paragraph');
        const items = Array.isArray(block.items)
            ? block.items.map((item) => ({
                id: item?.id ?? `item-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                src: typeof item?.src === 'string' ? item.src : '',
                alt: typeof item?.alt === 'string' ? item.alt : '',
            }))
            : [];

        return {
            ...base,
            ...block,
            type: ['paragraph', 'heading', 'quote', 'image', 'raw'].includes(block.type) ? block.type : 'paragraph',
            value: typeof block.value === 'string' ? block.value : '',
            src: typeof block.src === 'string' ? block.src : '',
            alt: typeof block.alt === 'string' ? block.alt : '',
            cite: typeof block.cite === 'string' ? block.cite : '',
            caption: typeof block.caption === 'string' ? block.caption : '',
            items: block.type === 'gallery' ? items : [],
        };
    },
    addBlock(type) {
        const block = this.createBlock(type);
        if (type === 'gallery' && !block.items.length) {
            block.items.push({ id: `item-${Date.now()}`, src: '', alt: '' });
        }

        this.blocks.push(block);
        this.sync();
    },
    removeBlock(index) {
        if (this.blocks.length <= 1) {
            this.blocks = [this.createBlock('paragraph')];
            this.sync();
            return;
        }

        this.blocks.splice(index, 1);
        this.sync();
    },
    moveBlock(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= this.blocks.length) {
            return;
        }

        const [block] = this.blocks.splice(index, 1);
        this.blocks.splice(target, 0, block);
        this.sync();
    },
    sync() {
        this.serialized = this.blocks
            .map((block) => this.serializeBlock(block))
            .filter((block) => block !== '')
            .join('\n\n');

        this.serializedBlocks = JSON.stringify(this.blocks);

        if (this.$refs.contentText) {
            this.$refs.contentText.value = this.serialized;
        }
        if (this.$refs.contentBlocks) {
            this.$refs.contentBlocks.value = this.serializedBlocks;
        }
    },
    async uploadImageForBlock(block, event) {
        const file = event?.target?.files?.[0] ?? null;
        if (!file) {
            return;
        }

        await this.uploadToBlock(block, [file], false);
        event.target.value = '';
    },
    async uploadGalleryImages(block, event) {
        const files = Array.from(event?.target?.files ?? []);
        if (!files.length) {
            return;
        }

        await this.uploadToBlock(block, files, true);
        event.target.value = '';
    },
    async uploadToBlock(block, files, appendMultiple) {
        if (!this.uploadUrl) {
            this.uploadError = 'No upload endpoint configured.';
            return;
        }

        const formData = new FormData();
        if (appendMultiple) {
            files.forEach((file) => formData.append('images[]', file));
        } else {
            formData.append('image', files[0]);
        }

        this.uploading = true;
        this.uploadError = '';

        try {
            const response = await fetch(this.uploadUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();
            const urls = Array.isArray(payload?.data?.urls) ? payload.data.urls.filter(Boolean) : [];

            if (!response.ok) {
                throw new Error(payload?.message || 'Upload failed');
            }

            if (appendMultiple) {
                urls.forEach((url, index) => {
                    block.items.push({
                        id: `item-${Date.now()}-${index}-${Math.random().toString(36).slice(2, 8)}`,
                        src: url,
                        alt: '',
                    });
                });
            } else if (urls[0]) {
                block.src = urls[0];
            }

            this.sync();
        } catch (error) {
            this.uploadError = error?.message || 'Upload failed.';
        } finally {
            this.uploading = false;
        }
    },
    addGalleryItem(block) {
        if (!Array.isArray(block.items)) {
            block.items = [];
        }

        block.items.push({
            id: `item-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            src: '',
            alt: '',
        });
        this.sync();
    },
    removeGalleryItem(block, index) {
        if (!Array.isArray(block.items)) {
            return;
        }

        block.items.splice(index, 1);
        if (!block.items.length) {
            block.items.push({
                id: `item-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                src: '',
                alt: '',
            });
        }

        this.sync();
    },
    serializeBlock(block) {
        const value = (block.value ?? '').trim();
        const src = (block.src ?? '').trim();
        const alt = (block.alt ?? '').trim();
        const cite = (block.cite ?? '').trim();
        const caption = (block.caption ?? '').trim();

        switch (block.type) {
            case 'heading':
                return value ? `<h2>${this.escapeHtml(value)}</h2>` : '';
            case 'quote':
                return value ? `<blockquote><p>${this.escapeHtml(value)}</p>${cite ? `<cite>${this.escapeHtml(cite)}</cite>` : ''}</blockquote>` : '';
            case 'image':
                return src ? `<figure class="wp-block-image"><img src="${this.escapeAttr(src)}" alt="${this.escapeAttr(alt)}"></figure>` : '';
            case 'gallery':
                if (!Array.isArray(block.items) || !block.items.length) {
                    return '';
                }

                return `<figure class="wp-block-gallery">${block.items
                    .filter((item) => (item.src ?? '').trim() !== '')
                    .map((item) => `<figure class="wp-block-image"><img src="${this.escapeAttr((item.src ?? '').trim())}" alt="${this.escapeAttr((item.alt ?? '').trim())}"></figure>`)
                    .join('')}${caption ? `<figcaption>${this.escapeHtml(caption)}</figcaption>` : ''}</figure>`;
            case 'raw':
                return value;
            case 'paragraph':
            default:
                return value ? `<p>${this.escapeHtml(value).replace(/\n/g, '<br>')}</p>` : '';
        }
    },
    previewHtml(block) {
        return this.serializeBlock(block) || '<div class="text-[#7b7b7b]">No preview yet.</div>';
    },
    escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },
    escapeAttr(value) {
        return this.escapeHtml(value).replace(/`/g, '&#096;');
    },
}));

function setupRadioPlayerLayout() {
    const dock = document.querySelector('.radio-player-dock');
    const footer = document.querySelector('#site-footer');
    const siteContent = document.querySelector('#site-content');

    if (!dock || !footer || !siteContent) {
        return;
    }

    const rootStyle = document.documentElement.style;
    const mobileQuery = window.matchMedia('(max-width: 767px)');

    const syncMobileState = () => {
        dock.classList.toggle('radio-player-mobile', mobileQuery.matches);
    };

    const syncDockOffset = () => {
        const height = Math.ceil(dock.getBoundingClientRect().height);
        rootStyle.setProperty('--radio-player-offset', `${height + 24}px`);
    };

    const syncFooterLift = (lift = 0) => {
        rootStyle.setProperty('--radio-player-lift', `${Math.max(0, Math.ceil(lift))}px`);
    };

    syncMobileState();
    syncDockOffset();
    syncFooterLift(0);

    if (typeof ResizeObserver !== 'undefined') {
        const dockObserver = new ResizeObserver(() => {
            syncDockOffset();
        });
        dockObserver.observe(dock);
    } else {
        window.addEventListener('resize', syncDockOffset, { passive: true });
    }

    if (typeof IntersectionObserver !== 'undefined') {
        const footerObserver = new IntersectionObserver((entries) => {
            const entry = entries[0];

            if (!entry) {
                return;
            }

            syncFooterLift(entry.isIntersecting ? entry.intersectionRect.height : 0);
        }, {
            root: null,
            threshold: [0, 0.01, 0.1, 0.25, 0.5, 1],
        });

        footerObserver.observe(footer);
    }

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', syncMobileState);
    } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(syncMobileState);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupRadioPlayerLayout, { once: true });
} else {
    setupRadioPlayerLayout();
}

const playerRoot = document.querySelector('[data-radio-player-root]');
if (playerRoot) {
    registerRadioPlayer(Alpine);
}

Alpine.start();

const forceCloseModals = () => {
    window.dispatchEvent(new CustomEvent('sr-force-close-modals'));
};

window.addEventListener('load', forceCloseModals, { once: true });
window.addEventListener('pageshow', forceCloseModals);
