/*
 * Service worker de la app de tripulación (Despacho Sarapiquí).
 * Guarda la pantalla para que abra rápido y, sin señal, muestra la última
 * versión. Los datos del servicio siempre se piden en vivo al servidor.
 */
const CACHE = 'crew-v1';
const SHELL = [
    '/images/logo.svg',
    '/images/app-icon-192.png',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).catch(() => null));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Estado, GPS y pasos: siempre en vivo, nunca de la memoria
    if (request.method !== 'GET' || request.url.includes('/estado')) {
        return;
    }

    // La pantalla: primero la red; sin señal, la última guardada
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request)),
        );
        return;
    }

    // Logo, íconos y mapa: de la memoria si ya están
    event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
});
