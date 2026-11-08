import assert from 'node:assert';

// Mock DOM & Browser Environment
class LocalStorageMock {
    constructor() {
        this.store = {};
    }
    getItem(key) {
        return this.store[key] || null;
    }
    setItem(key, value) {
        this.store[key] = String(value);
    }
    removeItem(key) {
        delete this.store[key];
    }
    clear() {
        this.store = {};
    }
}

globalThis.localStorage = new LocalStorageMock();
globalThis.document = {
    head: {
        appendChild: (el) => {
            globalThis.document.scripts.push(el);
        }
    },
    scripts: [],
    createElement: (tag) => {
        return {
            tagName: tag.toUpperCase(),
            async: false,
            src: '',
            attributes: {},
            setAttribute(k, v) { this.attributes[k] = v; },
        };
    },
    getElementsByTagName: () => [],
};

globalThis.window = {
    location: {
        hostname: 'localhost',
        pathname: '/',
        search: '',
    },
    dataLayer: [],
    dispatchEvent: function() {},
};

const { default: analytics } = await import('../../resources/js/analytics.js');

console.log('=== TEST 1: Estado 1 - Sin Eleccion (Primer visitante) ===');
localStorage.clear();
analytics.initConsent();
assert.strictEqual(analytics.hasAnalyticsConsent(), false, 'Consentimiento debe ser falso');
assert.strictEqual(analytics.gtmLoaded, false, 'GTM NO debe cargarse sin eleccion');
assert.strictEqual(document.scripts.length, 0, 'Cero scripts inyectados');

const unconsentedTrack = analytics.track('listen_click', { source: 'dock', stream_type: 'live' });
assert.strictEqual(unconsentedTrack, false, 'No debe emitir eventos analiticos');
assert.strictEqual(window.dataLayer.length, 0, 'dataLayer vacio');

console.log('=== TEST 2: Estado 2 - Rechazo ("Solo necesarias") ===');
analytics.setConsent({ analytics: false, personalization: false });
assert.strictEqual(analytics.hasAnalyticsConsent(), false);
assert.strictEqual(analytics.gtmLoaded, false, 'GTM NO debe cargarse tras rechazo');
assert.strictEqual(document.scripts.length, 0, 'Ningun script de terceros inyectado');

const rejectedTrack = analytics.track('playback_started', { stream_type: 'live', time_to_start_ms: 300 });
assert.strictEqual(rejectedTrack, false);
assert.strictEqual(window.dataLayer.length, 0, 'dataLayer sigue estrictamente vacio');

console.log('=== TEST 3: Estado 3 - Aceptacion ("Aceptar todas") con flag externo habilitado ===');
globalThis.window.SR_EXTERNAL_ANALYTICS_ENABLED = true;
analytics.setConsent({ analytics: true, personalization: true });
assert.strictEqual(analytics.hasAnalyticsConsent(), true);
assert.strictEqual(analytics.gtmLoaded, true, 'GTM debe cargarse tras consentimiento cuando el flag esta activo');
assert.strictEqual(document.scripts.length, 1, 'Exactamente 1 script de GTM inyectado');
assert.ok(document.scripts[0].src.includes('GTM-5NCG6PN'));

// Verificar que NO se reprodujeron eventos pasados retrospectivamente
const pastPlaybackEvents = window.dataLayer.filter(e => e.event === 'playback_started');
assert.strictEqual(pastPlaybackEvents.length, 0, 'No debe reproducir retrospectivamente eventos previos al consentimiento');

console.log('=== TEST 4: Verificacion de Via Unica y Whitelist Estricta ===');
window.dataLayer = [];
analytics.track('listen_click', { source: 'hero', stream_type: 'live' });
assert.strictEqual(window.dataLayer.length, 1);
assert.strictEqual(window.dataLayer[0].event, 'listen_click');
assert.strictEqual(window.dataLayer[0].source, 'hero');

// Verificar podcast_play sin episode_title libre (solo program_slug y episode_id)
analytics.track('podcast_play', {
    episode_id: 42,
    program_slug: 'vuelo-del-dragon',
    episode_title: 'Titulo con nombre Pedro Perez', // Debe ser ignorado por whitelist
});
const playRecord = window.dataLayer.find(e => e.event === 'podcast_play');
assert.ok(playRecord);
assert.strictEqual(playRecord.program_slug, 'vuelo-del-dragon');
assert.strictEqual(playRecord.episode_title, undefined, 'episode_title libre no debe ser transmitido');

// Verificar search_no_results sin search_term (solo section y query_length)
analytics.track('search_no_results', {
    section: 'muro_del_rock',
    query_length: 15,
    search_term: 'juan@gmail.com', // Debe ser ignorado
});
const searchRecord = window.dataLayer.find(e => e.event === 'search_no_results');
assert.ok(searchRecord);
assert.strictEqual(searchRecord.section, 'muro_del_rock');
assert.strictEqual(searchRecord.query_length, 15);
assert.strictEqual(searchRecord.search_term, undefined, 'search_term no debe existir');

// Verificar contact_success sin datos personales
analytics.track('contact_success', {
    form_name: 'contact_page',
    subject_category: 'general',
    name: 'Carlos',
    email: 'carlos@mail.com',
    message: 'Hola mundo',
});
const contactRecord = window.dataLayer.find(e => e.event === 'contact_success');
assert.strictEqual(contactRecord.form_name, 'contact_page');
assert.strictEqual(contactRecord.name, undefined);
assert.strictEqual(contactRecord.email, undefined);
assert.strictEqual(contactRecord.message, undefined);

console.log('=== TEST 5: Estado 4 - Personalizacion ===');
analytics.setConsent({ analytics: false, personalization: true });
assert.strictEqual(analytics.hasAnalyticsConsent(), false);
const blockedCustom = analytics.track('podcast_seek', { episode_id: 1, seek_position_seconds: 40 });
assert.strictEqual(blockedCustom, false, 'Sin analitica, eventos quedan bloqueados');

console.log('=== TEST 6: Estado 5 - Revocacion de Consentimiento ===');
// Reactivar primero
analytics.setConsent({ analytics: true, personalization: true });
const countBefore = document.scripts.length;
// Revocar
analytics.setConsent({ analytics: false, personalization: false });
assert.strictEqual(analytics.hasAnalyticsConsent(), false);
assert.strictEqual(document.scripts.length, countBefore, 'No se inyectan nuevos scripts al revocar');

const afterRevocationTrack = analytics.track('listen_click', { source: 'dock', stream_type: 'live' });
assert.strictEqual(afterRevocationTrack, false, 'Eventos posteriores a la revocacion quedan estrictamente bloqueados');

console.log('=== TEST 7: Restriccion de Diagnostico Local a Produccion ===');
// En entorno de produccion simulado (dominio publico sin flags de dev)
window.location.hostname = 'sevenrockradio.com';
window.location.search = '';
window.location.pathname = '/';
analytics.clearEventLog();

analytics.setConsent({ analytics: true, personalization: true });
analytics.track('listen_click', { source: 'hero', stream_type: 'live' });
assert.strictEqual(analytics.getEventLog().length, 0, 'En produccion no se debe almacenar log de auditoria en memoria');

console.log('\n>>> RESULTADO: TODOS LOS 7 ESCENARIOS DE CIERRE DE BLOQUE 6 SUPERADOS CON EXITO <<<');
