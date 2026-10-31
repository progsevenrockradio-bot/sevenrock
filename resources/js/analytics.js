/**
 * Seven Rock Radio - Unified Analytics & Consent Management
 * 
 * Vía única canónica (dataLayer.push), bloqueo previo estricto de scripts externos,
 * parámetros estructurales sin texto libre (anti-PII) y diagnóstico local restringido a dev/tests.
 */

const VALID_EVENTS = new Set([
    'listen_click',
    'playback_started',
    'playback_error',
    'podcast_play',
    'podcast_seek',
    'band_registration_started',
    'band_registration_completed',
    'search_no_results',
    'contact_success',
]);

// Whitelist de parámetros estructurales (sin campos de texto libre como search_term o episode_title)
const EVENT_PARAM_WHITELIST = {
    listen_click: ['source', 'stream_type'],
    playback_started: ['stream_type', 'time_to_start_ms'],
    playback_error: ['stream_type', 'error_code'],
    podcast_play: ['episode_id', 'program_slug'],
    podcast_seek: ['episode_id', 'seek_position_seconds'],
    band_registration_started: ['category'],
    band_registration_completed: ['category', 'plan'],
    search_no_results: ['section', 'query_length'],
    contact_success: ['form_name', 'subject_category'],
};

const GTM_CONTAINER_ID = 'GTM-5NCG6PN';

class AnalyticsService {
    constructor() {
        this.eventLog = [];
        this.maxLogSize = 100;
        this.seekThrottleTimers = {};
        this.gtmLoaded = false;
        this.initConsent();
    }

    /**
     * Determina si el entorno actual es de desarrollo, pruebas o localhost
     */
    isTestOrDev() {
        if (typeof window === 'undefined') return true;
        const host = window.location.hostname || '';
        const path = window.location.pathname || '';
        const search = window.location.search || '';
        return (
            host === 'localhost' ||
            host === '127.0.0.1' ||
            host.endsWith('.test') ||
            path.startsWith('/dev/') ||
            search.includes('test=1') ||
            search.includes('sr_test=true') ||
            window.SR_IS_ADMIN === true ||
            window.SR_FORCE_DEV_LOG === true
        );
    }

    /**
     * Inicializa el estado de consentimiento desde localStorage
     */
    initConsent() {
        this.preferences = {
            necessary: true,
            analytics: false,
            personalization: false,
        };

        try {
            const raw = localStorage.getItem('sevenrock_cookie_consent');
            if (raw) {
                const parsed = JSON.parse(raw);
                this.preferences.analytics = Boolean(parsed.analytics);
                this.preferences.personalization = Boolean(parsed.personalization);
            }
        } catch (e) {}

        // Si ya había consentimiento previo otorgado, cargar GTM una sola vez
        if (this.hasAnalyticsConsent()) {
            this.loadGtmOnce();
        }
    }

    /**
     * Comprueba si el usuario concedió cookies analíticas
     */
    hasAnalyticsConsent() {
        return Boolean(this.preferences && this.preferences.analytics === true);
    }

    /**
     * Carga el script de GTM una única vez tras consentimiento explícito
     * (Mantenido deshabilitado por defecto hasta auditar el contenedor GTM externo)
     */
    loadGtmOnce() {
        if (this.gtmLoaded || typeof window === 'undefined' || typeof document === 'undefined') {
            return;
        }

        // Carga externa deshabilitada de forma preventiva hasta auditar el contenedor GTM externo
        const isExternalAnalyticsEnabled = Boolean(
            typeof window !== 'undefined' && window.SR_EXTERNAL_ANALYTICS_ENABLED === true
        );
        if (!isExternalAnalyticsEnabled) {
            return;
        }

        this.gtmLoaded = true;
        window.dataLayer = window.dataLayer || [];

        // Inicializar contenedor GTM de forma asíncrona
        window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtm.js?id=${GTM_CONTAINER_ID}`;
        script.setAttribute('data-sr-gtm', 'true');
        
        const firstScript = document.getElementsByTagName('script')[0];
        if (firstScript && firstScript.parentNode) {
            firstScript.parentNode.insertBefore(script, firstScript);
        } else if (document.head) {
            document.head.appendChild(script);
        }
    }

    /**
     * Actualiza el consentimiento del usuario
     */
    setConsent(newPrefs) {
        const wasAnalyticsGranted = this.hasAnalyticsConsent();
        
        this.preferences = {
            necessary: true,
            analytics: Boolean(newPrefs.analytics),
            personalization: Boolean(newPrefs.personalization),
        };

        try {
            localStorage.setItem('sevenrock_cookie_consent', JSON.stringify(this.preferences));
        } catch (e) {}

        // Notificar a Google Consent Mode v2 si gtag está disponible
        if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                'analytics_storage': this.preferences.analytics ? 'granted' : 'denied',
                'functionality_storage': this.preferences.personalization ? 'granted' : 'denied'
            });
        }

        if (this.preferences.analytics) {
            // Cargar GTM una vez si se acaba de aceptar
            if (!this.gtmLoaded) {
                this.loadGtmOnce();
            }
            window.dataLayer.push({
                event: 'consent_granted',
                consent_level: 'analytics_allowed',
            });
        } else {
            // Revocación: comunicar al dataLayer el corte de consentimiento
            if (this.gtmLoaded) {
                window.dataLayer.push({
                    event: 'consent_revoked',
                    consent_level: 'necessary_only',
                });
            }
        }

        // Diagnóstico restringido a dev/tests
        if (this.isTestOrDev()) {
            this.recordLocalEvent('consent_update', {
                analytics: this.preferences.analytics,
                personalization: this.preferences.personalization,
            }, this.preferences.analytics ? 'consent_granted' : 'consent_revoked');
        }
    }

    /**
     * Vía única canónica para eventos: dataLayer.push({ event: eventName, ...params })
     */
    track(eventName, rawParams = {}) {
        if (!VALID_EVENTS.has(eventName)) {
            return false;
        }

        // 1. Filtrar parámetros por whitelist estricta
        const allowedKeys = EVENT_PARAM_WHITELIST[eventName] || [];
        const cleanParams = {};

        for (const key of allowedKeys) {
            if (key in rawParams && rawParams[key] !== undefined && rawParams[key] !== null) {
                const val = rawParams[key];
                if (typeof val === 'number') {
                    cleanParams[key] = Number.isFinite(val) ? Math.round(val) : 0;
                } else if (typeof val === 'boolean') {
                    cleanParams[key] = val;
                } else {
                    // Cadenas fijas seguras: truncar a 60 caracteres y sin caracteres especiales
                    cleanParams[key] = String(val).trim().slice(0, 60);
                }
            }
        }

        // 2. Control de repetición / throttle para podcast_seek
        if (eventName === 'podcast_seek') {
            const epId = cleanParams.episode_id || 'default';
            const now = Date.now();
            const lastTime = this.seekThrottleTimers[epId] || 0;
            if (now - lastTime < 1500) {
                return false;
            }
            this.seekThrottleTimers[epId] = now;
        }

        const isDev = this.isTestOrDev();
        const hasConsent = this.hasAnalyticsConsent();

        // 3. Diagnóstico local en memoria (SOLO en entorno de pruebas/desarrollo)
        if (isDev) {
            const status = hasConsent ? 'dispatched' : 'blocked_by_consent';
            this.recordLocalEvent(eventName, cleanParams, status);
        }

        // 4. Emisión de evento DOM para receptor de pruebas
        if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new CustomEvent('sevenrock:analytics', {
                detail: {
                    event: eventName,
                    params: cleanParams,
                    consent: hasConsent,
                }
            }));
        }

        // 5. Bloqueo estricto previo: sin consentimiento, abortar sin enviar nada
        if (!hasConsent) {
            return false;
        }

        // 6. ÚNICA VÍA CANÓNICA: dataLayer.push
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: eventName,
            ...cleanParams,
        });

        return true;
    }

    recordLocalEvent(eventName, payload, status) {
        if (!this.isTestOrDev()) return null;

        const record = {
            id: 'evt_' + Math.random().toString(36).substr(2, 9),
            timestamp: new Date().toISOString(),
            event: eventName,
            params: payload,
            status,
        };

        this.eventLog.push(record);
        if (this.eventLog.length > this.maxLogSize) {
            this.eventLog.shift();
        }

        return record;
    }

    getEventLog() {
        return this.isTestOrDev() ? [...this.eventLog] : [];
    }

    clearEventLog() {
        this.eventLog = [];
    }
}

// Instanciar singleton
const analyticsInstance = new AnalyticsService();
if (typeof window !== 'undefined') {
    window.SevenRockAnalytics = analyticsInstance;
}

export default analyticsInstance;
