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

        // Validaciones textuales del legacy (GuardarMantenimiento).
        validar() {
            if (!this.nuevo.tipoMantenimiento) {
                this.notificar('Debes seleccionar el tipo de mantenimiento');
                return false;
            }

            if (!this.nuevo.estadoActual) {
                this.notificar('Debes seleccionar el estado actual de la maquinaria');
                return false;
            }

            if (this.nuevo.tipoMantenimiento === '2' && (this.nuevo.fallaDescripcion || '').trim() === '') {
                this.notificar('Debes describir la falla');
                return false;
            }

            if (this.nuevo.tipoMantenimiento === '1' && this.usaFrecuencia && !this.nuevo.frecuencia) {
                this.notificar('Debes seleccionar la frecuencia');
                return false;
            }

            if (!this.nuevo.fechaInicio) {
                this.notificar('Debes seleccionar la fecha de inicio');
                return false;
            }

            return true;
        },

        notificar(msg) {
            if (typeof this.showAlert === 'function') {
                this.showAlert('warning', 'Atención', msg);
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
                    costoMantenimiento: this.nuevo.costoMantenimiento,
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
