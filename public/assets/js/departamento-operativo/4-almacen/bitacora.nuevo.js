// ============================================================
// Bitácora de Maquinaria y Equipos — Almacén
// Pantalla "Nuevo mantenimiento" (equivalente al legacy
// maquinaria-equipos-nuevo/{idEquipo}).
// Reglas idénticas al legacy:
//   · FRECUENCIA: sólo Preventivo y sólo si la maquinaria la usa
//     ( Hidrolavadora: Diario/Semanal/Por horas; Planta de emergencia:
//     Diario/Semanal/Mensual ). Para el resto no se muestra el select.
//   · FALLA: sólo Correctivo (obligatoria en ese caso).
//   · El resumen de actividades se pide al backend por
//     maquinaria + tipo + frecuencia (checklist-preview).
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('bitacoraNuevo', () => ({

        BASE: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        volver: '/departamento-operativo/almacen/maquinaria-equipos',

        idEquipo: 0,
        equipo: {},
        usaFrecuencia: false,

        estados: ['En operación', 'Fuera de servicio', 'En Reparación'],

        previewFilas: [],
        guardando: false,
        errores: {
            estadoActual: false,
            fechaInicio: false,
            tipoMantenimiento: false,
            frecuencia: false,
            fallaDescripcion: false,
            costoMantenimiento: false
        },

        nuevo: {
            tipoMantenimiento: '',
            frecuencia: '',
            fechaInicio: new Date().toISOString().substring(0, 10),
            estadoActual: '',
            costoMantenimiento: '',
            fallaDescripcion: '',
            observaciones: ''
        },

        initNuevo() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE = c.dataset.baseUrl || this.BASE;
                this.volver = c.dataset.volver || this.volver;
                this.idEquipo = parseInt(c.dataset.idEquipo || '0', 10);
                this.usaFrecuencia = c.dataset.usaFrecuencia === 'true';

                try {
                    this.equipo = JSON.parse(c.dataset.equipo || '{}');
                } catch (e) {
                    this.equipo = {};
                }
            }

            window.addEventListener('beforeunload', (e) => {
                if (this.guardando) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        // CambioTipo del legacy: Correctivo muestra la falla y oculta
        // frecuencia/resumen; Preventivo muestra la frecuencia sólo si aplica.
        cambioTipo() {
            if (this.nuevo.tipoMantenimiento === '2') {
                this.nuevo.frecuencia = '';
                this.previewFilas = [];
                return;
            }

            if (this.usaFrecuencia) {
                this.cambioFrecuencia();
            } else {
                this.previewFilas = [];
            }
        },

        cambioFrecuencia() {
            if (this.nuevo.tipoMantenimiento !== '1') {
                this.previewFilas = [];
                return;
            }

            if (!this.nuevo.frecuencia) {
                this.previewFilas = [];
                return;
            }

            this.cargarPreview();
        },

        parseCosto(valor) {
            const texto = String(valor ?? '').replace(/,/g, '').trim();
            if (texto === '') return null;
            const numero = Number(texto);
            return Number.isFinite(numero) ? numero : NaN;
        },

        formatoCosto(valor) {
            const texto = String(valor ?? '').replace(/,/g, '').trim();
            if (texto === '' || !/^-?\d*(?:\.\d{0,2})?$/.test(texto)) return '';
            const negativo = texto.startsWith('-');
            const sinSigno = negativo ? texto.slice(1) : texto;
            const partes = sinSigno.split('.');
            const entero = (partes[0] || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return (negativo ? '-' : '') + (partes.length > 1 ? `${entero}.${partes[1]}` : entero);
        },

        capturarCosto(evento) {
            const input = evento && evento.target;
            if (!input) return;

            const caret = input.selectionStart ?? input.value.length;
            const digitosAntes = input.value.slice(0, caret).replace(/[^0-9.]/g, '').length;
            let limpio = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const punto = limpio.indexOf('.');
            if (punto !== -1) {
                limpio = limpio.slice(0, punto + 1) + limpio.slice(punto + 1).replace(/\./g, '').slice(0, 2);
            }

            const partes = limpio.split('.');
            const entero = (partes[0] || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const formateado = partes.length > 1 ? `${entero}.${partes[1]}` : entero;
            input.value = formateado;
            this.nuevo.costoMantenimiento = limpio;
            this.errores.costoMantenimiento = false;

            let nuevaPosicion = 0;
            let contados = 0;
            while (nuevaPosicion < formateado.length && contados < digitosAntes) {
                if (/[0-9.]/.test(formateado[nuevaPosicion])) contados++;
                nuevaPosicion++;
            }
            input.setSelectionRange(nuevaPosicion, nuevaPosicion);
        },

        async cargarPreview() {
            try {
                const { data } = await axios.post(this.BASE + '/checklist-preview', {
                    idEquipo: this.idEquipo,
                    tipoMantenimiento: parseInt(this.nuevo.tipoMantenimiento || '0', 10),
                    frecuencia: this.nuevo.frecuencia
                });

                this.previewFilas = data.filas || [];
            } catch (e) {
                this.previewFilas = [];
            }
        },

        // Validación visual del formulario antes de enviar.
        validar() {
            this.errores = {
                estadoActual: !this.nuevo.estadoActual,
                fechaInicio: !this.nuevo.fechaInicio,
                tipoMantenimiento: !this.nuevo.tipoMantenimiento,
                frecuencia: this.nuevo.tipoMantenimiento === '1' && this.usaFrecuencia && !this.nuevo.frecuencia,
                fallaDescripcion: this.nuevo.tipoMantenimiento === '2' && (this.nuevo.fallaDescripcion || '').trim() === '',
                costoMantenimiento: false
            };

            const costo = this.parseCosto(this.nuevo.costoMantenimiento);
            if (costo !== null && (!Number.isFinite(costo) || costo < 0)) {
                this.errores.costoMantenimiento = true;
            }

            const hayError = Object.values(this.errores).some(Boolean);
            if (hayError) {
                if (window.Notify && typeof window.Notify.error === 'function') {
                    window.Notify.error('Completa los campos obligatorios.');
                } else {
                    this.notificar('Completa los campos obligatorios.');
                }
            }
            return !hayError;
        },

        notificar(msg) {
            if (typeof this.showAlert === 'function') {
                this.showAlert('error', 'Error', msg);
            }
        },

        async guardarNuevo() {
            if (this.guardando) {
                return;
            }

            if (!this.validar()) {
                return;
            }

            this.guardando = true;

            try {
                const { data } = await axios.post(this.BASE + '/crear', {
                    idEquipo: this.idEquipo,
                    tipoMantenimiento: parseInt(this.nuevo.tipoMantenimiento, 10),
                    frecuencia: (this.nuevo.tipoMantenimiento === '1' && this.usaFrecuencia) ? this.nuevo.frecuencia : '',
                    fechaInicio: this.nuevo.fechaInicio,
                    estadoActual: this.nuevo.estadoActual,
                    costoMantenimiento: String(this.nuevo.costoMantenimiento ?? '').replace(/,/g, ''),
                    fallaDescripcion: this.nuevo.fallaDescripcion,
                    observaciones: this.nuevo.observaciones
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Mantenimiento creado.');
                this.guardando = false;

                const fecha = this.nuevo.fechaInicio;
                window.location.href = this.volver + '?fecha=' + encodeURIComponent(fecha);
            } catch (e) {
                this.error(e);
                this.guardando = false;
            }
        },

        ok(msg) {
            if (typeof this.showAlert === 'function') {
                this.showAlert('success', 'Correcto', msg);
            }
        },

        error(e) {
            const data = (e && e.response && e.response.data) || {};
            const msg = data.message || 'Ocurrió un problema, intenta de nuevo.';

            if (typeof this.showAlert === 'function') {
                this.showAlert('error', 'Error', msg);
            }
        }

    }));

});
