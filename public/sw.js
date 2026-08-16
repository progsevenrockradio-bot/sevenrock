/**
 * Seven Rock Radio — Service Worker v2
 *
 * Estrategias de caché:
 *  - Assets estáticos (CSS, JS, imágenes): Cache-First
 *  - Páginas PWA (/app/*):                 Network-First → fallback offline
 *  - Streams de audio (radioboss):         Bypass total (nunca cachear)
 *  - API /app/api/*:                       Network-First, sin caché
 *  - Push Notifications:                   Listener para alertas "En Vivo"
 */

const CACHE_VERSION  = 'v3';
const CACHE_NAME     = `srr-pwa-${CACHE_VERSION}`;
const STATIC_CACHE   = `srr-static-${CACHE_VERSION}`;
const OFFLINE_URL    = '/app/offline';

// ─────────────────────────────────────────────────────
// Assets pre-cacheados en la instalación
// ─────────────────────────────────────────────────────
const PRECACHE_URLS = [
    '/app',
    '/app/offline',
    '/assets/lucille/logo.png',
    '/assets/lucille/album3.jpg',
    '/assets/lucille/podcats.webp',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

// ─────────────────────────────────────────────────────
// INSTALL: pre-cachear assets esenciales
// ─────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            // addAll ignora errores individuales para no romper la instalación
            return Promise.allSettled(
                PRECACHE_URLS.map(url =>
                    cache.add(url).catch(err =>
                        console.warn(`[SW] No se pudo pre-cachear ${url}:`, err)
                    )
                )
            );
        }).then(() => self.skipWaiting())
    );
});

// ─────────────────────────────────────────────────────
// ACTIVATE: limpiar caches de versiones anteriores
// ─────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE)
                    .map((name) => {
                        console.log('[SW] Eliminando caché antiguo:', name);
                        return caches.delete(name);
                    })
            );
        }).then(() => self.clients.claim())
    );
});

// ─────────────────────────────────────────────────────
// FETCH: interceptar requests
// ─────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // 1. Solo interceptar HTTP/HTTPS
    if (!url.protocol.startsWith('http')) return;

    // 2. Bypass: streams de audio y servicios externos (no cachear)
    if (
        url.hostname.includes('radioboss.fm') ||
        url.hostname.includes('archive.org')  ||
        url.hostname.includes('fonts.bunny.net') ||
        url.hostname.includes('fonts.googleapis.com')
    ) {
        return;
    }

    // 3. Solo interceptar GET
    if (request.method !== 'GET') return;

    // 4. API interna — Network-First, sin caché
    if (url.pathname.startsWith('/app/api/') || url.pathname.startsWith('/app/push/')) {
        return; // Dejar que el navegador lo maneje
    }

    // 5. Assets estáticos — Cache-First
    if (
        url.pathname.startsWith('/build/')   ||
        url.pathname.startsWith('/assets/')  ||
        url.pathname.startsWith('/icons/')   ||
        url.pathname.match(/\.(css|js|woff2?|ttf|eot|png|jpg|jpeg|webp|svg|ico|gif)$/)
    ) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // 6. Páginas PWA (/app/*) — Network-First con fallback offline
    if (url.pathname.startsWith('/app')) {
        event.respondWith(networkFirst(request));
        return;
    }
});

// ─────────────────────────────────────────────────────
// Estrategia: Cache-First
// ─────────────────────────────────────────────────────
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response && response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Recurso no disponible offline', { status: 503 });
    }
}

// ─────────────────────────────────────────────────────
// Estrategia: Network-First con fallback offline
// ─────────────────────────────────────────────────────
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response && response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        // Sin red: buscar en caché
        const cached = await caches.match(request);
        if (cached) return cached;

        // Fallback final: página offline personalizada
        const offline = await caches.match(OFFLINE_URL);
        return offline || new Response(
            '<html><body style="background:#121212;color:#e8e8e8;font-family:sans-serif;text-align:center;padding-top:40vh"><h1>Sin conexión</h1><p>Seven Rock Radio</p></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

// ─────────────────────────────────────────────────────
// PUSH: recibir notificaciones push del servidor
// ─────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {
        title: '🔴 Seven Rock Radio — En Vivo',
        body:  '¡La señal está al aire! Escúchanos ahora.',
        url:   '/app/live',
        icon:  '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag:   'live-alert',
    };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch {
        // Payload no es JSON — usar defaults
    }

    const options = {
        body:              data.body,
        icon:              data.icon,
        badge:             data.badge,
        tag:               data.tag || 'srr-push',
        renotify:          true,
        requireInteraction: false,
        vibrate:           [200, 100, 200],
        data:              { url: data.url },
        actions: [
            { action: 'open',   title: '▶ Escuchar ahora' },
            { action: 'close',  title: 'Cerrar' },
        ],
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// ─────────────────────────────────────────────────────
// NOTIFICATIONCLICK: manejar clic en notificación
// ─────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'close') return;

    const targetUrl = event.notification.data?.url || '/app/live';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Si ya hay una ventana PWA abierta, navegar en ella
            for (const client of clientList) {
                if (client.url.includes('/app') && 'focus' in client) {
                    client.focus();
                    return client.navigate(targetUrl);
                }
            }
            // Si no hay ventana abierta, abrir una nueva
            return clients.openWindow(targetUrl);
        })
    );
});

// ─────────────────────────────────────────────────────
// PUSHSUBSCRIPTIONCHANGE: renovar suscripción expirada
// ─────────────────────────────────────────────────────
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.registration.pushManager.getSubscription()
            .then(async (newSub) => {
                if (!newSub) return;
                // Re-enviar suscripción al servidor
                return fetch('/app/push/subscribe', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '' },
                    body:    JSON.stringify({
                        endpoint: newSub.endpoint,
                        keys: {
                            p256dh: btoa(String.fromCharCode(...new Uint8Array(newSub.getKey('p256dh')))),
                            auth:   btoa(String.fromCharCode(...new Uint8Array(newSub.getKey('auth')))),
                        }
                    })
                });
            })
    );
});
