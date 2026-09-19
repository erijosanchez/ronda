/*
 * Service worker de Ronda. RONDA-PLAN-MAESTRO.md sec. 13.3 y ADR 0010.
 *
 * El usuario diario es un encargado de local con señal irregular. Esto es lo
 * que hace que la aplicación siga abriéndose sin red:
 *
 *   - los archivos del bundle y los iconos se guardan al usarlos;
 *   - las pantallas que ya se visitaron se vuelven a servir desde la caché;
 *   - lo que nunca se visitó cae en /offline.html, que explica qué pasa.
 *
 * Lo que NO se guarda nunca: peticiones que no sean GET, el endpoint de
 * Livewire, la evidencia (privada y firmada) y las descargas de exportaciones.
 * Una copia de esas en el teléfono sería una fuga esperando a pasar.
 *
 * Al cerrar sesión, la página manda `clear-pages` y se borra la caché de
 * pantallas: un teléfono compartido no puede seguir mostrando los pendientes
 * de quien se fue.
 */

const VERSION = 'v1';
const CACHE_ESTATICOS = `ronda-estaticos-${VERSION}`;
const CACHE_PAGINAS = `ronda-paginas-${VERSION}`;
const OFFLINE = '/offline.html';

const IMPRESCINDIBLES = [OFFLINE, '/icons/icon-192.png', '/manifest.webmanifest'];

/** Rutas que no se guardan jamás. */
const NUNCA = [/^\/livewire\//, /^\/evidencia\//, /^\/exportaciones\/\d+\/descargar/, /^\/horizon/];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_ESTATICOS)
            .then((cache) => cache.addAll(IMPRESCINDIBLES))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((nombres) =>
                Promise.all(
                    nombres
                        .filter((nombre) => nombre.startsWith('ronda-') && !nombre.endsWith(VERSION))
                        .map((nombre) => caches.delete(nombre)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'clear-pages') {
        event.waitUntil(caches.delete(CACHE_PAGINAS));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || NUNCA.some((patron) => patron.test(url.pathname))) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(paginaConRespaldo(request));

        return;
    }

    if (esEstatico(url.pathname)) {
        event.respondWith(desdeLaCachePrimero(request));
    }
});

function esEstatico(ruta) {
    return (
        ruta.startsWith('/build/') ||
        ruta.startsWith('/icons/') ||
        ruta.startsWith('/flux/') ||
        ruta === '/favicon.ico' ||
        ruta === '/manifest.webmanifest'
    );
}

/**
 * Primero la red: una pantalla vieja de pendientes es peor que esperar. Si no
 * hay red, la última copia; y si nunca se visitó, la página de sin conexión.
 */
async function paginaConRespaldo(request) {
    try {
        const respuesta = await fetch(request);

        if (respuesta.ok) {
            const cache = await caches.open(CACHE_PAGINAS);
            cache.put(request, respuesta.clone());
        }

        return respuesta;
    } catch {
        const guardada = await caches.match(request);

        return guardada ?? (await caches.match(OFFLINE));
    }
}

/**
 * Los archivos del bundle llevan hash en el nombre: si están, no han cambiado.
 */
async function desdeLaCachePrimero(request) {
    const guardado = await caches.match(request);

    if (guardado) {
        return guardado;
    }

    const respuesta = await fetch(request);

    if (respuesta.ok) {
        const cache = await caches.open(CACHE_ESTATICOS);
        cache.put(request, respuesta.clone());
    }

    return respuesta;
}
