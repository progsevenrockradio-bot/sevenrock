import Alpine from 'alpinejs';
import { registerRadioPlayer } from './player';

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
        if (! this.searchUrl) {
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
        if (! this.results.length) {
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
    dateSuggestion: '',
    dateSuggestionType: '',
    uploading: false,
    progress: 0,
    progressLabel: '0%',
    statusMessage: '',
    phaseLabel: 'Listo',
    phaseDetailLabel: '',
    errorMessages: [],
    downloading: false,
    uploadEtaLabel: '',
    fileSizeLabel: '',
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
    init() {
        this.computeDateForDay(this.activeDay);

        this.$watch('activeDay', (day) => {
            this.computeDateForDay(day);
        });
    },
    computeDateForDay(dayKey) {
        const targetDay = DAY_MAP[String(dayKey ?? '').toUpperCase()];
        if (!targetDay) {
            return;
        }

        const today = new Date();
        const currentDay = today.getDay();
        const currentDayIso = currentDay === 0 ? 7 : currentDay;
        const targetDate = new Date(today);

        if (targetDay === currentDayIso) {
            this.dateSuggestionType = 'today';
            this.dateSuggestion = 'Hoy es el día correcto';
            this.fecha_emision = this.toDateInputValue(today);
            return;
        }

        const diff = targetDay > currentDayIso
            ? targetDay - currentDayIso
            : 7 - currentDayIso + targetDay;

        targetDate.setDate(today.getDate() + diff);
        this.dateSuggestionType = targetDay > currentDayIso ? 'future' : 'next';
        this.dateSuggestion = `Próximo ${this.formatWeekday(targetDate)} = ${this.formatDayMonth(targetDate)}`;
        this.fecha_emision = this.toDateInputValue(targetDate);
    },
    formatWeekday(date) {
        return date.toLocaleDateString('es-ES', {
            weekday: 'long',
        });
    },
    formatDayMonth(date) {
        return date.toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'long',
        });
    },
    toDateInputValue(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');

        return `${y}-${m}-${d}`;
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
        if (! field) {
            return;
        }

        this.activeTab = this.tabForField(field);

        window.setTimeout(() => {
            const selector = `[name="${CSS.escape(field)}"]`;
            const element = form.querySelector(selector);
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
        this.progress = 0;
        this.progressLabel = '0%';
        this.phaseLabel = 'Validando';
        this.phaseDetailLabel = this.phaseDetailText();
        this.statusMessage = 'Faltan campos obligatorios. Revisa el formulario.';
        this.errorMessages = messages.length > 0 ? messages : ['Revisa los campos marcados.'];
        this.uploadEtaLabel = '';

        this.focusField(form, firstField);

        if (! firstField) {
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

        if (label.includes('subiend') || label.includes('transfer')) {
            return 'border-[#5a4315] bg-[rgba(118,86,22,.18)] text-[#f2d89b]';
        }

        if (label.includes('sincronizando')) {
            return 'border-[#31553f] bg-[rgba(30,76,49,.2)] text-[#b8e2c7]';
        }

        if (label.includes('procesando') || label.includes('preparando')) {
            return 'border-[#3f4f2c] bg-[rgba(60,82,35,.2)] text-[#d5e7aa]';
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

        if (phase.includes('subiend')) {
            return 'Subiendo el MP3 al servidor de la radio.';
        }

        if (phase.includes('procesando')) {
            return 'Escribiendo metadatos y enviando a RadioBOSS.';
        }

        if (phase.includes('sincronizando')) {
            return 'Sincronizando el episodio con Archive.org.';
        }

        if (phase.includes('preparando')) {
            return 'La transferencia terminó. Preparando la descarga local.';
        }

        if (phase.includes('error')) {
            return 'La operación se detuvo por un problema de red, validación o servidor.';
        }

        if (phase.includes('listo')) {
            return 'Todo quedó listo para iniciar una nueva carga.';
        }

        return '';
    },
    formatBytes(bytes) {
        const size = Number(bytes ?? 0);
        if (!Number.isFinite(size) || size <= 0) {
            return '';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let value = size;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        const precision = unitIndex === 0 ? 0 : value >= 10 ? 1 : 2;

        return `${value.toFixed(precision)} ${units[unitIndex]}`;
    },
    formatDuration(seconds) {
        const total = Math.max(0, Math.ceil(Number(seconds ?? 0)));
        const hrs = Math.floor(total / 3600);
        const mins = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        if (hrs > 0) {
            return `${hrs}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        return `${mins}:${String(secs).padStart(2, '0')}`;
    },
    updateUploadEstimate(file) {
        const size = Number(file?.size ?? 0);
        this.fileSizeLabel = this.formatBytes(size);

        if (size <= 0) {
            this.uploadEtaLabel = '';
            return;
        }

        const seconds = Math.max(1, Math.ceil(size / Math.max(1, this.estimatedUploadRateBytesPerSec)));
        this.uploadEtaLabel = `Estimado de carga: ~${this.formatDuration(seconds)}`;
    },
    validateBeforeSubmit(form) {
        const errors = [];
        const masterProgram = form.querySelector(`[data-day-panel="${this.activeDay}"] select[name="master_program_id"]`);
        const title = form.querySelector('[name="live_title"]');
        const date = form.querySelector('[name="fecha_emision"]');
        const audioInput = form.querySelector('[name="archivo_mp3"]');
        const audioFile = audioInput?.files?.[0] ?? null;

        if (!masterProgram?.value) {
            errors.push('Programa maestro: selecciona un programa.');
        }

        if (!String(title?.value ?? '').trim()) {
            errors.push('Título del episodio: este campo es obligatorio.');
        }

        if (!String(date?.value ?? '').trim()) {
            errors.push('Fecha de emisión: selecciona una fecha.');
        }

        if (!audioFile) {
            errors.push('Archivo MP3: selecciona un archivo antes de continuar.');
        } else if (audioFile.size <= 0) {
            errors.push('Archivo MP3: el archivo seleccionado no es válido.');
        } else if (audioFile.size > 512000 * 1024) {
            errors.push('Archivo MP3: no puede superar 500 MB.');
        }

        if (errors.length > 0) {
            this.showValidationIssues(form, {
                master_program_id: !masterProgram?.value ? ['Debes seleccionar un programa maestro.'] : [],
                live_title: !String(title?.value ?? '').trim() ? ['El título del episodio es obligatorio.'] : [],
                fecha_emision: !String(date?.value ?? '').trim() ? ['La fecha de emisión es obligatoria.'] : [],
                archivo_mp3: !audioFile ? ['Debes seleccionar un archivo MP3.'] : [],
            });

            return null;
        }

        this.updateUploadEstimate(audioFile);

        return audioFile;
    },
    formatValidationErrors(validationErrors = {}) {
        return Object.entries(validationErrors).flatMap(([field, messages]) => {
            const label = this.fieldLabelMap[field] ?? field.replaceAll('_', ' ');
            const normalizedMessages = Array.isArray(messages) ? messages : [messages];

            return normalizedMessages
                .filter(Boolean)
                .map((message) => {
                    const text = String(message).trim();

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
    submit(event) {
        const form = event?.target;
        if (!form || this.uploading) {
            return;
        }

        event.preventDefault();
        const submitter = event?.submitter ?? null;
        const action = String(submitter?.value ?? 'process').toLowerCase();
        const pipelineAction = action === 'save' ? 'save' : 'process';

        this.phaseLabel = 'Validando';
        const audioFile = this.validateBeforeSubmit(form);
        if (!audioFile) {
            return;
        }

        const pipelineInput = form.querySelector('input[name="pipeline_action"]');
        if (pipelineInput) {
            pipelineInput.value = pipelineAction;
        } else {
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="pipeline_action" value="${pipelineAction}" data-pipeline-action-field="1">`);
        }

        this.uploading = true;
        this.downloading = false;
        this.progress = 0;
        this.progressLabel = '0%';
        this.phaseLabel = 'Subiendo';
        this.phaseDetailLabel = this.phaseDetailText();
        this.statusMessage = 'Subiendo archivo...';
        this.errorMessages = [];
        this.uploadEtaLabel = this.uploadEtaLabel || 'Calculando tiempo estimado...';

        const xhr = new XMLHttpRequest();
        xhr.open(form.method || 'POST', form.action, true);
        xhr.responseType = 'text';
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const uploadStartedAt = performance.now();

        xhr.upload.onprogress = (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                return;
            }

            const percent = Math.min(100, Math.max(0, Math.round((progressEvent.loaded / progressEvent.total) * 100)));
            this.progress = percent;
            this.progressLabel = `${percent}%`;

            if (progressEvent.loaded > 0) {
                const elapsedSeconds = Math.max(0.1, (performance.now() - uploadStartedAt) / 1000);
                const bytesPerSecond = progressEvent.loaded / elapsedSeconds;
                if (bytesPerSecond > 0 && progressEvent.total > progressEvent.loaded) {
                    const remainingSeconds = Math.ceil((progressEvent.total - progressEvent.loaded) / bytesPerSecond);
                    this.uploadEtaLabel = `Restan ~${this.formatDuration(remainingSeconds)}`;
                } else if (percent >= 99) {
                    this.uploadEtaLabel = 'Finalizando transferencia...';
                }
            }
        };

        xhr.onreadystatechange = () => {
            if (xhr.readyState !== XMLHttpRequest.DONE) {
                return;
            }

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
                    this.phaseLabel = payload?.download_url
                        ? 'Preparando descarga'
                        : (form.querySelector('[name="sync_archive_org"]')?.checked ? 'Sincronizando Archive.org' : 'Procesando RadioBOSS');
                    this.phaseDetailLabel = this.phaseDetailText();
                }

                window.dispatchEvent(new CustomEvent('podcast-upload-complete', {
                    detail: {
                        status: payload?.status ?? 'Episodio procesado correctamente.',
                        redirectUrl: payload?.redirect_url ?? null,
                    },
                }));

                if (payload?.download_url) {
                    this.downloading = true;
                    const anchor = document.createElement('a');
                    anchor.href = payload.download_url;
                    anchor.rel = 'noopener';
                    anchor.target = '_self';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                }

                if (payload?.redirect_url) {
                    window.setTimeout(() => {
                        window.location.href = payload.redirect_url;
                    }, payload?.download_url ? 900 : 300);
                }

                return;
            }

            const validationErrors = payload?.errors ?? {};
            if (xhr.status === 422 || Object.keys(validationErrors).length > 0) {
                this.showValidationIssues(form, validationErrors);
                return;
            }

            this.phaseLabel = 'Procesando RadioBOSS';
            this.phaseDetailLabel = this.phaseDetailText();
            this.statusMessage = 'No se pudo completar la subida.';
            this.errorMessages = payload?.message ? [payload.message] : [];
            this.uploadEtaLabel = '';
        };

        xhr.onerror = () => {
            this.uploading = false;
            this.phaseLabel = 'Error';
            this.phaseDetailLabel = this.phaseDetailText();
            this.statusMessage = 'La subida falló por un error de red o del servidor.';
            this.uploadEtaLabel = '';
        };

        xhr.send(new FormData(form));
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
        if (! this.recentUrl) {
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
        if (! this.timer) {
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
            this.$refs.recentUploads.innerHTML = html;
        }
    },
    async refresh(silent = false) {
        if (! this.recentUrl) {
            return;
        }

        if (this.isRefreshing) {
            return;
        }

        const token = ++this.requestToken;
        this.isRefreshing = true;

        try {
            const url = new URL(this.recentUrl, window.location.origin);
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            });

            if (! response.ok) {
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

            if (! hasActiveEntries) {
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
    init() {
        if (! this.blocks.length) {
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
        if (type === 'gallery' && ! block.items.length) {
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

        if (this.$refs.contentText) {
            this.$refs.contentText.value = this.serialized;
        }
    },
    async uploadImageForBlock(block, event) {
        const file = event?.target?.files?.[0] ?? null;
        if (! file) {
            return;
        }

        await this.uploadToBlock(block, [file], false);
        event.target.value = '';
    },
    async uploadGalleryImages(block, event) {
        const files = Array.from(event?.target?.files ?? []);
        if (! files.length) {
            return;
        }

        await this.uploadToBlock(block, files, true);
        event.target.value = '';
    },
    async uploadToBlock(block, files, appendMultiple) {
        if (! this.uploadUrl) {
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

            if (! response.ok) {
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
        if (! Array.isArray(block.items)) {
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
        if (! Array.isArray(block.items)) {
            return;
        }

        block.items.splice(index, 1);
        if (! block.items.length) {
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
                if (! Array.isArray(block.items) || ! block.items.length) {
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

registerRadioPlayer(Alpine);

Alpine.start();

const forceCloseModals = () => {
    window.dispatchEvent(new CustomEvent('sr-force-close-modals'));
};

window.addEventListener('load', forceCloseModals, { once: true });
window.addEventListener('pageshow', forceCloseModals);
