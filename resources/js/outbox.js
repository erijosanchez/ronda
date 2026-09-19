/*
 * La cola de envío del dispositivo. RONDA-PLAN-MAESTRO.md sec. 13.3, ADR 0010.
 *
 * Sin señal, un reporte lleno no se pierde: se guarda entero (respuestas y
 * fotos) en IndexedDB y sale solo cuando vuelve la red.
 *
 * Reglas que sostienen esto:
 *
 *   - Cada entrega lleva una huella (`client_token`) generada aquí ANTES de
 *     mandarla. Si la respuesta se pierde por el camino, el reintento trae la
 *     misma huella y el servidor devuelve el mismo envío en vez de crear otro.
 *   - Solo se reintenta un fallo de red. Un 403, un 409 o un 422 son
 *     respuestas: la cola para y lo dice, porque insistir daría lo mismo.
 *   - Nada se borra de la cola hasta que el servidor confirma.
 */

const BASE = 'ronda-outbox';
const ALMACEN = 'envios';
const EVENTO_CAMBIO = 'outbox-changed';

function abrirBase() {
    return new Promise((resolve, reject) => {
        const solicitud = indexedDB.open(BASE, 1);

        solicitud.onupgradeneeded = () => {
            const base = solicitud.result;

            if (!base.objectStoreNames.contains(ALMACEN)) {
                base.createObjectStore(ALMACEN, { keyPath: 'token' });
            }
        };

        solicitud.onsuccess = () => resolve(solicitud.result);
        solicitud.onerror = () => reject(solicitud.error);
    });
}

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

export const outbox = {
    todos: () => conElAlmacen('readonly', (almacen) => almacen.getAll()),
    guardar: (envio) => conElAlmacen('readwrite', (almacen) => almacen.put(envio)),
    borrar: (token) => conElAlmacen('readwrite', (almacen) => almacen.delete(token)),
};

function avisarDeCambio() {
    window.dispatchEvent(new CustomEvent(EVENTO_CAMBIO));
}

function token() {
    return (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`).replaceAll('-', '');
}

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Lee un archivo como base64, sin el prefijo `data:`.
 */
function leerArchivo(archivo) {
    return new Promise((resolve, reject) => {
        const lector = new FileReader();

        lector.onload = () => resolve(String(lector.result).split(',')[1] ?? '');
        lector.onerror = () => reject(lector.error);
        lector.readAsDataURL(archivo);
    });
}

/**
 * Manda una entrega guardada. Devuelve qué hacer con ella.
 */
async function enviar(envio) {
    let respuesta;

    try {
        respuesta = await fetch(envio.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                client_token: envio.token,
                answers: envio.answers,
                evidence: envio.evidence,
            }),
        });
    } catch {
        // Sigue sin haber red: se queda en la cola tal cual.
        return { estado: 'pendiente' };
    }

    if (respuesta.ok) {
        return { estado: 'entregado' };
    }

    // La sesión caducó mientras esperaba: no es un error de la entrega, se
    // reintenta cuando la persona vuelva a entrar.
    if (respuesta.status === 401 || respuesta.status === 419) {
        return { estado: 'pendiente' };
    }

    const cuerpo = await respuesta.json().catch(() => ({}));

    return { estado: 'rechazado', motivo: cuerpo.message ?? cuerpo.code ?? String(respuesta.status) };
}

let drenando = false;

/**
 * Intenta mandar todo lo que espera. Se llama al cargar, al volver la señal y
 * cuando alguien pulsa «reintentar».
 */
export async function drenar() {
    if (drenando || !navigator.onLine) {
        return;
    }

    drenando = true;

    try {
        const envios = (await outbox.todos()) ?? [];

        for (const envio of envios) {
            if (envio.rechazado) {
                continue;
            }

            const resultado = await enviar(envio);

            if (resultado.estado === 'entregado') {
                await outbox.borrar(envio.token);
            } else if (resultado.estado === 'rechazado') {
                await outbox.guardar({ ...envio, rechazado: true, motivo: resultado.motivo });
            }
        }
    } finally {
        drenando = false;
        avisarDeCambio();
    }
}

/**
 * Guarda una entrega para mandarla luego.
 */
export async function encolar({ url, titulo, sede, answers, files, signatures, latitude, longitude }) {
    const evidence = {};

    for (const { campo, archivo } of files) {
        evidence[campo] ??= [];
        evidence[campo].push({
            name: archivo.name,
            data: await leerArchivo(archivo),
            latitude,
            longitude,
        });
    }

    // La firma ya es una imagen: viene del canvas como data URL.
    for (const [campo, dataUrl] of Object.entries(signatures ?? {})) {
        const base64 = String(dataUrl).split(',')[1];

        if (base64) {
            evidence[campo] ??= [];
            evidence[campo].push({ name: `firma-${campo}.png`, data: base64, latitude, longitude });
        }
    }

    await outbox.guardar({
        token: token(),
        url,
        titulo,
        sede,
        answers,
        evidence,
        creado: new Date().toISOString(),
    });

    avisarDeCambio();
}

document.addEventListener('alpine:init', () => {
    /**
     * Entregar sin señal.
     *
     * Se engancha por encima del formulario y escucha el envío en fase de
     * captura, o sea ANTES que Livewire: si no hay red, se queda la entrega,
     * la guarda con sus fotos y manda a la persona de vuelta a sus pendientes.
     * Con red no hace nada y el formulario sigue su camino de siempre.
     */
    window.Alpine.data('offlineSubmit', (config) => ({
        guardando: false,

        init() {
            this.$el.addEventListener('submit', (evento) => this.interceptar(evento), true);
        },

        async interceptar(evento) {
            if (navigator.onLine || this.guardando) {
                return;
            }

            evento.preventDefault();
            evento.stopPropagation();
            this.guardando = true;

            try {
                await encolar({
                    url: config.url,
                    titulo: config.titulo,
                    sede: config.sede,
                    answers: JSON.parse(JSON.stringify(this.$wire.answers ?? {})),
                    files: this.archivos(),
                    signatures: JSON.parse(JSON.stringify(this.$wire.signatures ?? {})),
                    latitude: this.$wire.latitude,
                    longitude: this.$wire.longitude,
                });

                // El borrador ya no hace falta: la entrega entera está en la cola.
                window.dispatchEvent(new CustomEvent('submission-saved'));

                window.location.assign(config.pendientes);
            } catch {
                this.guardando = false;
                window.alert(config.errorMessage);
            }
        },

        /**
         * Los archivos se leen de los `input` del formulario y no de Livewire:
         * sin red, la subida temporal de Livewire nunca llegó a ocurrir.
         */
        archivos() {
            const encontrados = [];

            for (const input of this.$el.querySelectorAll('input[type="file"]')) {
                const modelo = input.getAttribute('wire:model') ?? '';
                const campo = modelo.startsWith('uploads.') ? modelo.slice('uploads.'.length) : null;

                if (!campo) {
                    continue;
                }

                for (const archivo of input.files ?? []) {
                    encontrados.push({ campo, archivo });
                }
            }

            return encontrados;
        },
    }));

    /**
     * La lista de lo que espera: cuántos hay, cuáles fallaron y un botón para
     * volver a intentarlo. Vive en la pantalla de pendientes.
     */
    window.Alpine.data('outboxQueue', () => ({
        envios: [],

        init() {
            this.refrescar();

            window.addEventListener(EVENTO_CAMBIO, () => this.refrescar());
            window.addEventListener('online', () => drenar());

            drenar();
        },

        async refrescar() {
            this.envios = (await outbox.todos()) ?? [];
        },

        reintentar() {
            return drenar();
        },

        async descartar(token) {
            await outbox.borrar(token);
            await this.refrescar();
        },
    }));
});

// Al cargar la página, lo que quedó esperando sale sin que nadie lo pida.
window.addEventListener('load', () => drenar());
