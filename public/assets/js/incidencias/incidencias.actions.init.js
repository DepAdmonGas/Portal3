// ============================================================
// Incidencias — Componente Alpine: alta, edición, detalle y eliminación.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('incidenciasComponent', () => ({

        guardando: false,
        cargandoDetalle: false,

        modo: 'nuevo', // 'nuevo' | 'editar'

        detalle: null,

        puedeAgregar: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,

        claveModulo: 'incidencias',

        // Id de la estación elegida en el selector (0 = todas / sin selector).
        entidadId: 0,

        // Estaciones que el usuario puede elegir cuando no hay una fija.
        estaciones: [],

        campos: ['fecha', 'hora', 'incidente', 'responsable', 'asunto', 'comentarios'],

        form: {},
        errores: {},

        get tablaId() {
            return '#tabla-incidencias';
        },

        get selectorId() {
            return 'module-station-selector-' + this.claveModulo;
        },

        init() {
            const c = document.getElementById('incidencias');
            if (!c) return;

            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';
            this.claveModulo = c.dataset.modulo || this.claveModulo;

            try {
                this.estaciones = JSON.parse(c.dataset.estaciones || '[]');
            } catch (e) {
                this.estaciones = [];
            }

            this.limpiarFormulario();

            // El selector de estación vive en el layout (ModuleStationService).
            this.entidadId = this.entidadDelSelector();
            this.escucharSelector();

            document.addEventListener('incidencias:detalle', (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('incidencias:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('incidencias:eliminar', (e) => this.eliminar(e.detail.id, e.detail.asunto));
        },

        /** Mantiene entidadId al día cuando cambia el selector compartido. */
        escucharSelector() {
            const sel = document.getElementById(this.selectorId);
            if (!sel) return;

            sel.addEventListener('change', () => {
                this.entidadId = this.entidadDelSelector();
            });
        },

        /** Id de la estación elegida en el selector (0 = todas / sin selector). */
        entidadDelSelector() {
            const sel = document.getElementById(this.selectorId);

            if (sel && sel.value) {
                return parseInt(String(sel.value).replace('estacion_', '').replace('depto_', ''), 10) || 0;
            }

            // Sin selector (usuario de una sola estación): estación fija.
            const c = document.getElementById('incidencias');
            const fija = parseInt(c?.dataset.idEstacion || '', 10);

            return Number.isNaN(fija) ? 0 : fija;
        },

        limpiarFormulario() {
            const f = { id: '', id_estacion: '', fecha: '', hora: '', incidente: '', responsable: '', asunto: '', comentarios: '', archivo_actual: '', archivo_url: '' };
            this.campos.forEach((campo) => { f[campo] = ''; });
            this.form = f;
            this.errores = {};
            if (this.$refs && this.$refs.inputArchivo) {
                this.$refs.inputArchivo.value = '';
            }
        },

        limpiarErrores() {
            const e = {};
            this.campos.forEach((campo) => { e[campo] = false; });
            e.archivo = false;
            e.estacion = false;
            this.errores = e;
        },

        validar() {
            this.limpiarErrores();

            let hayError = false;

            // Sin estación fija el alta exige elegirla en el formulario.
            if (this.modo === 'nuevo' && !this.entidadId && !String(this.form.id_estacion ?? '').trim()) {
                this.errores.estacion = true;
                hayError = true;
            }

            this.campos.forEach((campo) => {
                if (String(this.form[campo] ?? '').trim() === '') {
                    this.errores[campo] = true;
                    hayError = true;
                }
            });

            // El archivo sólo es obligatorio en el alta (en edición se conserva).
            const archivo = this.$refs.inputArchivo && this.$refs.inputArchivo.files[0];
            if (this.modo === 'nuevo' && !archivo) {
                this.errores.archivo = true;
                hayError = true;
            }

            return hayError;
        },

        formData() {
            const fd = new FormData();

            if (this.modo === 'editar') {
                fd.append('id', String(this.form.id));
            } else {
                // Estación del selector, o la que se eligió en el formulario.
                fd.append('id_estacion', String(this.entidadId || this.form.id_estacion || ''));
            }

            this.campos.forEach((campo) => {
                fd.append(campo, String(this.form[campo] ?? '').trim());
            });

            const archivo = this.$refs.inputArchivo && this.$refs.inputArchivo.files[0];
            if (archivo) {
                fd.append('archivo', archivo);
            }

            return fd;
        },

        abrirModalAgregar() {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para agregar incidencias.');
                return;
            }

            this.modo = 'nuevo';
            this.limpiarFormulario();
            this.limpiarErrores();

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidencia')).show();
        },

        async abrirEditar(id) {
            if (!this.puedeEditar) {
                this.notify('error', 'No tienes permiso para editar incidencias.');
                return;
            }

            this.modo = 'editar';
            this.limpiarFormulario();
            this.limpiarErrores();
            this.detalle = null;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidencia')).show();

            try {
                const resp = await axios.get('/incidencias/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    const d = data.data;
                    this.form.id = d.id;
                    // Los campos crudos (sin formato) van al formulario.
                    this.form.fecha = d.fecha || '';
                    this.form.hora = (d.hora || '').substring(0, 5);
                    this.form.incidente = d.incidente || '';
                    this.form.responsable = d.responsable || '';
                    this.form.asunto = d.asunto || '';
                    this.form.comentarios = d.comentarios || '';
                    this.form.archivo_actual = d.existe_archivo ? (d.archivo || '') : '';
                    this.form.archivo_url = d.archivo_url || '';
                } else {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidencia')).hide();
                    this.notify('error', data.message || 'No se pudo cargar la incidencia.');
                }
            } catch (e) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidencia')).hide();
                this.notify('error', 'Error al consultar la incidencia.');
            }
        },

        async guardar() {
            if (this.guardando) return;

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const esEdicion = this.modo === 'editar';
            const url = esEdicion ? '/incidencias/update' : '/incidencias/store';

            this.guardando = true;

            try {
                const resp = await axios.post(url, this.formData(), {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                const data = resp.data || {};

                if (data.success === true) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalIncidencia')).hide();
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    this.cargarTabla();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible guardar.');
                    this.notify('error', data.message || 'No fue posible guardar.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al guardar la incidencia.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
            }
        },

        async abrirDetalle(id) {
            this.detalle = null;
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleIncidencia')).show();

            try {
                const resp = await axios.get('/incidencias/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.detalle = data.data;
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el detalle.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar la incidencia.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Borrado con el mecanismo global deleteAction(). */
        eliminar(id, asunto) {
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permiso para eliminar incidencias.');
                return;
            }

            this.deleteAction({
                url: '/incidencias/delete',
                id: id,
                name: asunto ? ('la incidencia "' + asunto + '"') : ('la incidencia #' + id),
                table: this.tablaId,
                data: {}
            });
        },

        /** Refresca la tabla desde el endpoint de datos. */
        cargarTabla() {
            const t = $(this.tablaId);
            if (t.length && $.fn.DataTable.isDataTable(this.tablaId)) {
                t.DataTable().ajax.reload(null, false);
            }
        }

    }));

});
