// ============================================================
// Calibración de Dispensarios — Almacén
// Componente Alpine: alta, edición y eliminación.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('calibracionDispensariosComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/calibracion-dispensarios',

        guardando: false,
        cargandoDetalle: false,
        detalle: null,

        // Periodos y permisos viajan como data-attributes del contenedor (#container),
        // así que la vista no necesita iterar nada.
        periodos: [],
        puedeCrear: false,
        estacionFija: false,

        form: {
            year: '',
            periodo: '',
            archivo: null
        },

        errores: {
            year: false,
            periodo: false,
            archivo: false
        },

        init() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;

                try {
                    this.periodos = JSON.parse(c.dataset.periodos || '[]');
                } catch (e) {
                    console.error(e);
                    this.periodos = [];
                }

                this.puedeCrear   = c.dataset.puedeCrear === 'true';
                this.estacionFija = c.dataset.estacionFija === 'true';
            }

            document.addEventListener('cd:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('cd:eliminar', (e) => this.eliminar(e.detail.id, e.detail.nombre));
        },

        limpiarFormulario() {
            this.form = {
                year: '',
                periodo: '',
                archivo: null
            };
            this.errores = { year: false, periodo: false, archivo: false };
            this.detalle = null;

            const nuevo = document.getElementById('inputArchivoNuevo');
            if (nuevo) nuevo.value = '';

            const editar = document.getElementById('inputArchivoEditar');
            if (editar) editar.value = '';
        },

        abrirNuevo() {
            this.limpiarFormulario();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevo')).show();
        },

        async abrirEditar(id) {
            this.limpiarFormulario();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    this.detalle = resp.data.data;
                    this.form.year    = this.detalle.year;
                    this.form.periodo = this.detalle.periodo;
                } else {
                    this.detalle = null;
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo obtener el registro.');
                }
            } catch (err) {
                console.error(err);
                this.detalle = null;
                this.notify('error', 'Error al consultar el registro.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /**
         * @returns {boolean} true si hay errores
         */
        validar() {
            this.errores = { year: false, periodo: false, archivo: false };
            let hayError = false;

            const year = String(this.form.year).trim();

            if (!year) {
                this.errores.year = true;
                hayError = true;
            } else if (!/^\d{1,10}$/.test(year)) {
                this.errores.year = true;
                this.notify('error', 'El año debe ser un número entero válido.');
                return true;
            }

            if (!this.form.periodo) {
                this.errores.periodo = true;
                hayError = true;
            }

            return hayError;
        },

        async guardarNuevo() {
            if (this.validar() || !this.form.archivo) {
                if (!this.form.archivo) this.errores.archivo = true;
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = new FormData();
            fd.append('Year', String(this.form.year).trim());
            fd.append('Periodo', this.form.periodo);
            fd.append('Archivo_file', this.form.archivo);

            await this.enviar(this.BASE_URL + '/guardar', fd, 'modalNuevo');
        },

        async guardarEdicion() {
            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = new FormData();
            fd.append('id', this.detalle.id);
            fd.append('Year', String(this.form.year).trim());
            fd.append('Periodo', this.form.periodo);

            if (this.form.archivo) {
                fd.append('Archivo_file', this.form.archivo);
            }

            await this.enviar(this.BASE_URL + '/editar', fd, 'modalEditar');
        },

        async enviar(url, fd, modalId) {
            this.guardando = true;

            try {
                const resp = await axios.post(url, fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);

                    const modal = document.getElementById(modalId);
                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }

                    this.limpiarFormulario();

                    const tabla = $('#tabla-calibracion-dispensarios');
                    if (tabla.length && $.fn.DataTable.isDataTable(tabla)) {
                        tabla.DataTable().ajax.reload(null, false);
                    }
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

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/eliminar',
                id: id,
                name: nombre || 'Calibración',
                table: '#tabla-calibracion-dispensarios'
            });
        },

        descargar(archivo) {
            this.download('calibracion-dispensarios', archivo);
        }

    }));
});
