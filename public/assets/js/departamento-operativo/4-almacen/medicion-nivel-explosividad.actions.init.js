// ============================================================
// Medición Nivel de Explosividad — Almacén
// Componentes Alpine:
//   - medicionExplosividadComponent: listado (Nuevo / Eliminar)
//   - medicionFormComponent: formulario (captura, firmas y pozos)
// ============================================================

document.addEventListener('alpine:init', () => {

    /* -------------------------------------------------------- */
    /* LISTADO                                                   */
    /* -------------------------------------------------------- */

    Alpine.data('medicionExplosividadComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/medicion-nivel-explosividad',
        puedeCrear: false,
        estacionFija: false,

        init() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.estacionFija = c.dataset.estacionFija === 'true';
            }

            document.addEventListener('ne:eliminar', (e) => this.eliminar(e.detail.id, e.detail.nombre));
        },

        /**
         * El legacy creaba el borrador antes de abrir el formulario: sin ese paso
         * no existiría la fila rosa que permite retomar el registro.
         */
        nuevo() {
            window.location = this.BASE_URL + '/nuevo';
        },

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/eliminar',
                id: id,
                name: nombre || 'registro',
                table: '#tabla-medicion-nivel-explosividad'
            });
        }

    }));

    /* -------------------------------------------------------- */
    /* FORMULARIO                                                */
    /* -------------------------------------------------------- */

    Alpine.data('medicionFormComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/medicion-nivel-explosividad',
        idReporte: 0,
        folio: '',
        guardando: false,

        datos: {},
        pozos: [],

        pad1: null,
        pad2: null,

        etiquetas: {},
        encargados: [],
        opcionesPozo: [],

        ppm: [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
        opcionesElementos: {
            1: ['Mantenimiento', 'Extraordinaria'],
            2: ['Interno', 'Externo'],
            3: ['Urgentes SI', 'Urgentes NO']
        },

        form: {},
        errores: {},
        erroresPozo: { pozo: false, ppm: false },

        pozo: { pozo: '', ppm: '', ubicacion: '' },

        init() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.idReporte = parseInt(c.dataset.idReporte, 10) || 0;
                this.folio = c.dataset.folio || '';

                try { this.datos = JSON.parse(c.dataset.datos || '{}') || {}; }
                catch (e) { console.error(e); this.datos = {}; }

                try { this.pozos = JSON.parse(c.dataset.pozos || '[]') || []; }
                catch (e) { console.error(e); this.pozos = []; }

                try { this.etiquetas = JSON.parse(c.dataset.etiquetas || '{}') || {}; }
                catch (e) { console.error(e); this.etiquetas = {}; }

                try { this.encargados = JSON.parse(c.dataset.encargados || '[]') || []; }
                catch (e) { console.error(e); this.encargados = []; }

                try { this.opcionesPozo = JSON.parse(c.dataset.opcionesPozo || '[]') || []; }
                catch (e) { console.error(e); this.opcionesPozo = []; }
            }

            this.limpiarErrores();

            this.form = Object.assign({
                Fecha: '',
                Elemento1: '', Elemento2: '', Elemento3: '',
                Elemento4: '', Elemento5: '', Elemento6: '', Elemento7: '',
                Elemento8: '', Elemento9: '', Elemento10: '', Elemento11: '',
                Elemento12: '', Elemento13: '', Elemento14: '', Elemento15: '',
                Elemento16: '', Elemento17: '', Elemento18: '',
                Observaciones: '',
                Encargado: ''
            }, this.datos);

            this.$nextTick(() => this.iniciarFirmas());
        },

        limpiarErrores() {
            this.errores = {
                Fecha: false,
                Elemento1: false, Elemento2: false, Elemento3: false,
                Encargado: false, firma1: false, firma2: false
            };
            this.erroresPozo = { pozo: false, ppm: false };
        },

        /* ------------------------- firmas ------------------------- */

        iniciarFirmas() {
            this.pad1 = this.crearPad('canvas1');
            this.pad2 = this.crearPad('canvas2');

            let temporizador = null;
            const reajustar = () => {
                clearTimeout(temporizador);
                temporizador = setTimeout(() => this.ajustarCanvas(), 150);
            };

            window.addEventListener('resize', reajustar);
            window.addEventListener('load', reajustar);
        },

        crearPad(id) {
            const canvas = document.getElementById(id);

            if (!canvas || typeof SignaturePad === 'undefined') {
                return null;
            }

            // Sólo se re-escala cuando la firma está vacía: si ya hay una
            // capturada se conserva el mapa de bits aunque cambie el tamaño.
            this.dimensionarCanvas(canvas);

            return new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
        },

        /**
         * El canvas se ajusta al espacio real que le deja la card (offsetWidth /
         * offsetHeight) y duplica resolución en pantallas HiDPI; la firma queda
         * nítida y ocupa todo el área dibujable.
         */
        dimensionarCanvas(canvas) {
            if (!canvas) return;

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const ancho = canvas.offsetWidth;
            const alto = canvas.offsetHeight;

            if (!ancho || !alto) return;

            const nuevoAncho = Math.round(ancho * ratio);
            const nuevoAlto = Math.round(alto * ratio);

            if (canvas.width === nuevoAncho && canvas.height === nuevoAlto) return;

            canvas.width = nuevoAncho;
            canvas.height = nuevoAlto;
            canvas.getContext('2d').scale(ratio, ratio);
        },

        ajustarCanvas() {
            const pares = [
                [document.getElementById('canvas1'), this.pad1],
                [document.getElementById('canvas2'), this.pad2]
            ];

            pares.forEach(([canvas, pad]) => {
                if (!canvas) return;
                if (pad && !pad.isEmpty()) return;
                this.dimensionarCanvas(canvas);
            });
        },

        limpiarFirma(n) {
            const pad = n === 1 ? this.pad1 : this.pad2;
            const canvas = document.getElementById(n === 1 ? 'canvas1' : 'canvas2');

            if (pad) {
                pad.clear();
            }

            // Limpieza a prueba de todo: aunque el pad viva en una pantalla con
            // escala distinta, se borra el mapa de bits completo en coordenadas
            // del dispositivo (sin depender del transform del contexto).
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.save();
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.restore();
            }

            this.errores['firma' + n] = false;
        },

        /* ------------------------ validación ------------------------ */

        /**
         * Orden de los avisos igual que en el formulario legacy.
         *
         * @returns {boolean} true si todo está completo
         */
        validar() {
            this.limpiarErrores();

            const faltan = [];

            if (!this.form.Fecha) {
                this.errores.Fecha = true;
                faltan.push('la fecha');
            }

            const etiquetas = {
                Elemento1: 'el tipo de medición',
                Elemento2: 'el verificador',
                Elemento3: 'las observaciones'
            };

            Object.keys(etiquetas).forEach((k) => {
                if (!String(this.form[k] === undefined || this.form[k] === null ? '' : this.form[k]).trim()) {
                    this.errores[k] = true;
                    faltan.push(etiquetas[k]);
                }
            });

            if (!this.form.Encargado) {
                this.errores.Encargado = true;
                faltan.push('el encargado de la estación');
            }

            if (!this.pad1 || this.pad1.isEmpty()) {
                this.errores.firma1 = true;
                faltan.push('la firma de quien toma la medición');
            }

            if (!this.pad2 || this.pad2.isEmpty()) {
                this.errores.firma2 = true;
                faltan.push('la firma por la estación');
            }

            if (faltan.length) {
                this.notify('error', 'Falta ' + faltan.join(', ') + '.');
                return false;
            }

            return true;
        },

        /* -------------------------- guardado -------------------------- */

        async guardar() {
            if (this.guardando) return;

            if (!this.validar()) return;

            this.guardando = true;

            try {
                const fd = new FormData();
                fd.append('idReporte', this.idReporte);
                fd.append('Fecha', this.form.Fecha);

                for (let i = 1; i <= 18; i++) {
                    const valor = this.form['Elemento' + i];
                    fd.append('Elemento' + i, valor === undefined || valor === null ? '' : valor);
                }

                fd.append('Observaciones', this.form.Observaciones || '');
                fd.append('Encargado', this.form.Encargado);
                fd.append('baseImage1', this.pad1.toDataURL());
                fd.append('baseImage2', this.pad2.toDataURL());

                const resp = await axios.post(this.BASE_URL + '/guardar', fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    setTimeout(() => { window.location = this.BASE_URL; }, 700);
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el registro.');
                }
            } catch (err) {
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error en el servidor al guardar el registro.';
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
            }
        },

        /* --------------------------- pozos --------------------------- */

        abrirPozo() {
            this.pozo = { pozo: '', ppm: '', ubicacion: '' };
            this.erroresPozo = { pozo: false, ppm: false };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPozo')).show();
        },

        async guardarPozo() {
            let ok = true;

            if (!this.pozo.pozo) {
                this.erroresPozo.pozo = true;
                ok = false;
            }

            if (this.pozo.ppm === '' || this.pozo.ppm === null || this.pozo.ppm === undefined) {
                this.erroresPozo.ppm = true;
                ok = false;
            }

            if (!ok) {
                this.notify('error', 'Falta el pozo o el PPM.');
                return;
            }

            this.guardando = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/pozo/guardar', {
                    idReporte: this.idReporte,
                    PozoMotobomba: this.pozo.pozo,
                    PPM: this.pozo.ppm,
                    Ubicacion: this.pozo.ubicacion || ''
                });

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);

                    this.pozos.push({
                        id: resp.data.id,
                        pozo: this.pozo.pozo,
                        ppm: this.pozo.ppm,
                        ubicacion: this.pozo.ubicacion || ''
                    });

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPozo')).hide();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo agregar el pozo.');
                }
            } catch (err) {
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error en el servidor al agregar el pozo.';
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
            }
        },

        async eliminarPozo(pozo) {
            const resp = await this.deleteAction({
                url: this.BASE_URL + '/pozo/eliminar',
                id: pozo.id,
                name: pozo.pozo,
                data: { idNivel: pozo.id, idReporte: this.idReporte }
            });

            if (resp && resp.success) {
                this.pozos = this.pozos.filter((p) => p.id !== pozo.id);
            }
        }

    }));

    /* -------------------------------------------------------- */
    /* DETALLE (sólo lectura)                                    */
    /* -------------------------------------------------------- */

    Alpine.data('medicionDetalleComponent', () => ({

        detalle: {},
        etiquetas: {},
        porN: {},
        generales: [],
        mediciones: [],

        init() {
            const c = this.$el;

            try { this.detalle = JSON.parse(c.dataset.detalle || '{}') || {}; }
            catch (e) { console.error(e); this.detalle = {}; }

            try { this.etiquetas = JSON.parse(c.dataset.etiquetas || '{}') || {}; }
            catch (e) { console.error(e); this.etiquetas = {}; }

            (this.detalle.campos || []).forEach((campo) => {
                this.porN[campo.n] = campo;
            });

            this.generales = [1, 2, 3].map((n) => ({
                n: n,
                etiqueta: this.etiquetas[n] || '',
                texto: this.valor(n)
            }));

            this.mediciones = [];
            for (let n = 4; n <= 18; n++) {
                this.mediciones.push({
                    n: n,
                    etiqueta: this.etiquetas[n] || '',
                    texto: this.valor(n)
                });
            }
        },

        valor(n) {
            const campo = this.porN[n];
            return (campo && campo.texto) || 'S/I';
        }

    }));
});
