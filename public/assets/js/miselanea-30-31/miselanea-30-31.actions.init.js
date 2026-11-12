// ============================================================
// Miscelánea 30, 31 — Componente Alpine: gestión de archivos por
// documento (alta con FormData, descarga global y baja), dentro de
// la estación y el ejercicio seleccionados.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('miselaneaComponent', () => ({

        guardando: false,
        cargando: false,

        idEstacion: '',
        estacion: '',
        year: '',
        tipoDescarga: 'miselanea',

        documentoId: 0,
        tituloDocumento: 'Documento',
        archivos: [],

        puedeAgregar: false,
        puedeEliminar: false,

        form: {
            detalle: ''
        },

        errores: {},

        init() {
            const c = document.getElementById('miselanea-30-31');
            if (!c) return;

            this.idEstacion = c.dataset.idEstacion || '';
            this.estacion = c.dataset.estacion || '';
            this.year = c.dataset.year || '';
            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';

            document.addEventListener('miselanea:documento', (e) => this.abrirDocumento(e.detail));
        },

        /** Abre el modal de archivos de un documento. */
        async abrirDocumento(detalle) {
            this.documentoId = Number(detalle.id);
            this.tituloDocumento = (detalle.lista ? detalle.lista + '. ' : '')
                + (detalle.documento || 'Documento');
            this.archivos = [];
            this.form.detalle = '';
            this.errores = {};
            this.cargando = true;

            bootstrap.Modal.getOrCreateInstance(
                document.getElementById('modalMiselaneaDocumento')
            ).show();

            await this.recargarArchivos();
        },

        /** Carga los archivos del documento en la estación y ejercicio. */
        async recargarArchivos() {
            this.cargando = true;

            try {
                const resp = await axios.get('/miselanea-30-31/documentos', {
                    params: { id_documento: this.documentoId, year: this.year }
                });

                const data = resp.data || {};

                if (data.success === true) {
                    this.archivos = data.data || [];
                } else {
                    this.notify('error', data.message || 'No se pudieron cargar los archivos.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar los archivos del documento.');
            } finally {
                this.cargando = false;
            }
        },

        /** Agrega un archivo al documento (multipart). */
        async agregarArchivo() {
            if (!this.puedeAgregar || this.guardando) return;

            const detalle = String(this.form.detalle || '').trim();
            const input = this.$refs.archivo;
            const file = input && input.files ? input.files[0] : null;

            this.errores = {};

            if (detalle === '') {
                this.errores.detalle = true;
                this.notify('error', 'El detalle es obligatorio.');
                return;
            }

            if (!file) {
                this.errores.archivo = true;
                this.notify('error', 'El archivo es obligatorio.');
                return;
            }

            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const fd = new FormData();
                fd.append('id_documento', this.documentoId);
                fd.append('year', this.year);
                fd.append('detalle', detalle);
                fd.append('archivo', file);

                const res = await this.createAction({
                    url: '/miselanea-30-31/agregar',
                    data: fd
                });

                if (res && res.success) {
                    this.form.detalle = '';
                    if (input) input.value = '';
                    await this.recargarArchivos();
                    this.recargarTablas();
                }
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Elimina un archivo con confirmación global + loader. */
        async eliminarArchivo(id, detalle) {
            if (!this.puedeEliminar || this.guardando) return;

            const confirmacion = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: 'El registro: ' + (detalle || ('archivo #' + id)) + ' será eliminado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!confirmacion.isConfirmed) return;

            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const resp = await axios.post('/miselanea-30-31/eliminar', { id: id });
                const data = resp.data || {};

                if (data.success === true) {
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    await this.recargarArchivos();
                    this.recargarTablas();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible eliminar el archivo.');
                    this.notify('error', data.message || 'No fue posible eliminar el archivo.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al eliminar el archivo.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Recarga ambas tablas de etapas. */
        recargarTablas() {
            if (typeof window.__miselaneaRecargarTablas === 'function') {
                window.__miselaneaRecargarTablas();
            }
        }

    }));

});
