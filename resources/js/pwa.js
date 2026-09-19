/*
 * La parte de navegador de la PWA. RONDA-PLAN-MAESTRO.md sec. 13.3, ADR 0010.
 *
 * Tres cosas:
 *   1. registrar el service worker (y avisarle cuando alguien cierra sesión);
 *   2. un aviso visible cuando no hay señal;
 *   3. el borrador del formulario en IndexedDB, para que una entrega a medias
 *      sobreviva a quedarse sin batería, sin señal o sin pestaña.
 *
 * El borrador es del dispositivo, no del servidor (sec. 13.3). Guarda solo las
 * respuestas escritas: las fotos son archivos y su sitio es el envío.
 */

const BASE = 'ronda-borradores';
const ALMACEN = 'borradores';

function abrirBase() {
    return new Promise((resolve, reject) => {
        const solicitud = indexedDB.open(BASE, 1);

        solicitud.onupgradeneeded = () => {
            const base = solicitud.result;

            if (!base.objectStoreNames.contains(ALMACEN)) {
                base.createObjectStore(ALMACEN);
            }
        };

        solicitud.onsuccess = () => resolve(solicitud.result);
        solicitud.onerror = () => reject(solicitud.error);
    });
}

/**
 * Cualquier fallo de IndexedDB (modo privado, cuota, permisos) se traga: un
 * borrador es una comodidad, y perderlo nunca puede impedir entregar.
 */
async function conElAlmacen(modo, operacion) {
    try {
        const base = await abrirBase();

        return await new Promise((resolve, reject) => {
            const transaccion = base.transaction(ALMACEN, modo);
            const peticion = operacion(transaccion.objectStore(ALMACEN));

            peticion.onsuccess = () => resolve(peticion.result);
            peticion.onerror = () => reject(peticion.error);
            transaccion.oncomplete = () => base.close();
        });
    } catch {
        return null;
    }
}

const borradores = {
    leer: (clave) => conElAlmacen('readonly', (almacen) => almacen.get(clave)),
    guardar: (clave, valor) => conElAlmacen('readwrite', (almacen) => almacen.put(valor, clave)),
    borrar: (clave) => conElAlmacen('readwrite', (almacen) => almacen.delete(clave)),
};

document.addEventListener('alpine:init', () => {
    /**
     * Aviso de sin conexión. Se monta una vez en el layout.
     */
    window.Alpine.data('connectionStatus', () => ({
        offline: !navigator.onLine,

        init() {
            window.addEventListener('online', () => {
                this.offline = false;
            });

            window.addEventListener('offline', () => {
                this.offline = true;
            });
        },
    }));

    /**
     * Borrador de un formulario de entrega.
     *
     * Se engancha al elemento que envuelve el formulario. Al abrir, si hay algo
     * guardado de este mismo reporte, lo devuelve a los campos; mientras se
     * escribe, guarda; y cuando el envío llega al servidor, lo borra.
     */
    window.Alpine.data('submissionDraft', (clave) => ({
        restored: false,
        pendiente: null,

        init() {
            this.restaurar();

            // Se guarda al escribir, con un respiro: sin esto se escribiría en
            // IndexedDB en cada tecla.
            this.$el.addEventListener('input', () => this.programarGuardado());
            this.$el.addEventListener('change', () => this.programarGuardado());

            // Lo lanza el componente Livewire cuando el envío ya está guardado.
            window.addEventListener('submission-saved', () => borradores.borrar(clave));
        },

        async restaurar() {
            const guardado = await borradores.leer(clave);

            if (!guardado || typeof guardado !== 'object') {
                return;
            }

            for (const [campo, valor] of Object.entries(guardado)) {
                // `false`: no se manda al servidor campo por campo; viaja con
                // la siguiente petición.
                this.$wire.set(`answers.${campo}`, valor, false);
            }

            this.restored = true;
        },

        programarGuardado() {
            clearTimeout(this.pendiente);
            this.pendiente = setTimeout(() => this.guardar(), 600);
        },

        guardar() {
            const respuestas = this.$wire?.answers;

            if (respuestas && Object.keys(respuestas).length > 0) {
                borradores.guardar(clave, JSON.parse(JSON.stringify(respuestas)));
            }
        },
    }));
});

/*
 * Registro del service worker. Solo en contexto seguro: en http:// sin
 * localhost el navegador lo rechaza, y no pasa nada por quedarse sin él.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la aplicación funciona igual, solo que sin
            // modo sin conexión.
        });
    });

    // Al cerrar sesión se tiran las pantallas guardadas: un teléfono compartido
    // no puede seguir mostrando los pendientes de quien se fue.
    document.addEventListener('submit', (evento) => {
        if (evento.target instanceof HTMLFormElement && evento.target.action.endsWith('/logout')) {
            navigator.serviceWorker.controller?.postMessage({ type: 'clear-pages' });
        }
    });
}
