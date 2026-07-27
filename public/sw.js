/**
 * Seven Rock Radio — Service Worker
 *
 * Estrategia de caché:
 *  - Assets estáticos (CSS, JS, imágenes): Cache-First
 *  - Páginas PWA (/app/*):                 Network-First (siempre fresco)
 *  - Stream de audio (radioboss):          Bypass (nunca cachear)
 *  - API /app/api/*:                       Network-First, sin caché
 */

const CACHE_NAME     = 'srr-pwa-v1';
const STATIC_CACHE   = 'srr-static-v1';

// Assets que se cachean en la instalación
const PRECACHE_URLS = [
    '/app',
    '/assets/lucille/logo.png',
];

// ─────────────────────────────────────────────
// INSTALL: pre-cachear assets esenciales
// ─────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(PRECACHE_URLS);
        }).then(() => self.skipWaiting())
    );
});

// ─────────────────────────────────────────────
// ACTIVATE: limpiar caches antiguos
// ─────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// ─────────────────────────────────────────────
// FETCH: interceptar requests
// ─────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // 1. Bypass: streams de audio y API de RadioBoss (no cachear)
    if (
        url.hostname.includes('radioboss.fm') ||
        url.hostname.includes('archive.org') ||
        url.pathname.startsWith('/app/api/')
    ) {
        return; // Dejar que el navegador maneje la request directamente
    }

    // 2. Solo interceptar GET
    if (request.method !== 'GET') return;

    // 3. Assets estáticos: Cache-First
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/assets/') ||
        url.pathname.match(/\.(css|js|woff2?|ttf|eot|png|jpg|jpeg|webp|svg|ico)$/)
    ) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // 4. Páginas PWA (/app/*): Network-First
    if (url.pathname.startsWith('/app')) {
        event.respondWith(networkFirst(request));
        return;
    }
});

// ─────────────────────────────────────────────
// Estrategia Cache-First
// ─────────────────────────────────────────────
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Offline', { status: 503 });
    }
}

// ─────────────────────────────────────────────
// Estrategia Network-First
// ─────────────────────────────────────────────
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        return caches.match('/app'); // Fallback al shell de la PWA
    }
}
