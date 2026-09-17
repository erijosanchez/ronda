/*
 * Evidencia en el formulario de entrega. RONDA-PLAN-MAESTRO.md sec. 9.5
 *
 * Componentes Alpine registrados desde el bundle y no en linea: la CSP no admite
 * scripts en linea (ADR 0011). Alpine llega con Livewire; se registran en
 * `alpine:init`, antes de que Alpine recorra la pagina.
 */

document.addEventListener('alpine:init', () => {
    /**
     * Firma a mano alzada sobre un canvas.
     *
     * Al levantar el trazo manda la imagen PNG a la propiedad Livewire indicada
     * (sin peticion inmediata: viaja con la entrega). Lo que llega al servidor se
     * valida como cualquier otra evidencia: que sea de verdad un PNG, hash, IP y
     * hora los pone el servidor, no el navegador.
     */
    window.Alpine.data('signaturePad', (property) => ({
        drawing: false,
        dirty: false,
        context: null,

        init() {
            const canvas = this.$refs.canvas;

            // El canvas se dimensiona en pixeles reales: con solo CSS, el trazo
            // sale desplazado y borroso en pantallas de alta densidad.
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;

            this.context = canvas.getContext('2d');
            this.context.scale(ratio, ratio);
            this.context.lineWidth = 2;
            this.context.lineCap = 'round';
            this.context.lineJoin = 'round';
            this.context.strokeStyle = '#18181b';

            canvas.addEventListener('pointerdown', (event) => this.start(event));
            canvas.addEventListener('pointermove', (event) => this.move(event));
            canvas.addEventListener('pointerup', () => this.end());
            canvas.addEventListener('pointerleave', () => this.end());
        },

        point(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        },

        start(event) {
            event.preventDefault();
            this.$refs.canvas.setPointerCapture(event.pointerId);
            this.drawing = true;

            const { x, y } = this.point(event);
            this.context.beginPath();
            this.context.moveTo(x, y);
        },

        move(event) {
            if (!this.drawing) {
                return;
            }

            const { x, y } = this.point(event);
            this.context.lineTo(x, y);
            this.context.stroke();
            this.dirty = true;
        },

        end() {
            if (!this.drawing) {
                return;
            }

            this.drawing = false;

            if (this.dirty) {
                this.$wire.set(property, this.$refs.canvas.toDataURL('image/png'), false);
            }
        },

        clear() {
            const canvas = this.$refs.canvas;
            this.context.clearRect(0, 0, canvas.width, canvas.height);
            this.dirty = false;
            this.$wire.set(property, '', false);
        },
    }));

    /**
     * Ubicacion del dispositivo, pedida una vez al abrir el formulario.
     *
     * Es la segunda fuente de ubicacion: muchos navegadores moviles quitan el GPS
     * del EXIF al subir una foto. Si se niega o falla, no pasa nada: se entrega
     * sin ubicacion y la revision lo ve.
     */
    window.Alpine.data('deviceLocation', () => ({
        init() {
            if (!('geolocation' in navigator)) {
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.$wire.set('latitude', String(position.coords.latitude), false);
                    this.$wire.set('longitude', String(position.coords.longitude), false);
                },
                () => {},
                { enableHighAccuracy: true, maximumAge: 60000, timeout: 15000 },
            );
        },
    }));
});
