document.addEventListener('alpine:init', () => {

    Alpine.data('constanciaSituacionFiscalComponent', () => ({

        guardando: false,
        entidadId: 0,

        puedeConsultar: true,
        puedeAgregar: false,
        puedeEliminar: false,
        puedeDescargar: false,

        tipoDescarga: 'constancia-fiscal',
        claveModulo: 'constancia-situacion-fiscal',

        get tablaId() {
            return '#tabla-constancia-situacion-fiscal';
        },

        get selectorId() {
            return 'module-station-selector-' + this.claveModulo;
        },

        init() {
            const c = document.getElementById('constancia-situacion-fiscal');
            if (!c) return;

            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';
            this.tipoDescarga = c.dataset.tipoDescarga || 'constancia-fiscal';
            this.claveModulo = c.dataset.modulo || this.claveModulo;

            // El selector de estación vive en el layout (ModuleStationService).
            // La entidad capturada es la elegida ahí; "todas" impide agregar.
            this.entidadId = this.entidadDelSelector();
            this.escucharSelector();

            document.addEventListener('cs-eliminar', (e) => {
                this.eliminar(e.detail.id);
            });
        },

        /** Mantiene entidadId al día cuando cambia el selector compartido. */
        escucharSelector() {
            const sel = document.getElementById(this.selectorId);
            if (!sel) return;

            sel.addEventListener('change', () => {
                this.entidadId = this.entidadDelSelector();
            });
        },

        /** Id de la entidad elegida en el selector (0 = todas). */
        entidadDelSelector() {
            const sel = document.getElementById(this.selectorId);
            if (!sel || !sel.value) return 0;

            return parseInt(String(sel.value).replace('estacion_', '').replace('depto_', ''), 10) || 0;
        },

        entidadSeleccionada() {
            return this.entidadId !== 0 ? this.entidadId : null;
        },

        /** Refresca la tabla desde el endpoint de datos. */
        cargarEntidad() {
            const t = $(this.tablaId);
            if (t.length && $.fn.DataTable.isDataTable(this.tablaId)) {
                t.DataTable().ajax.reload(null, false);
            }
        },

        abrirModalAgregar() {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para agregar constancias.');
                return;
            }

            if (!this.entidadId) {
                this.notify('error', 'Selecciona una estación o departamento.');
                return;
            }

            this.$refs.inputFecha.value = '';
            this.$refs.inputArchivo.value = '';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConstancia')).show();
        },

        async guardar() {
            if (this.guardando) return;

            const fecha = this.$refs.inputFecha.value;
            const archivo = this.$refs.inputArchivo.files[0];

            if (!this.entidadId) {
                this.notify('error', 'Selecciona una estación o departamento.');
                return;
            }

            if (!fecha) {
                this.notify('error', '* La fecha de actualización es obligatoria.');
                return;
            }

            if (!archivo) {
                this.notify('error', '* El archivo de la constancia es obligatorio.');
                return;
            }

            const fd = new FormData();
            fd.append('id_entidad', String(this.entidadId));
            fd.append('fecha', fecha);
            fd.append('documento', archivo);

            this.guardando = true;

            try {
                const resp = await axios.post('/constancia-situacion-fiscal/store', fd, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                const data = resp.data || {};

                if (data.success === true) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConstancia')).hide();
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    this.$refs.inputArchivo.value = '';
                    this.cargarEntidad();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible guardar.');
                    this.notify('error', data.message || 'No fue posible guardar.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al guardar la constancia.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
            }
        },

        /** Descarga por el mecanismo global de Portal3. */
        urlDescarga(archivo, vista = false) {
            const base = '/download?tipo=' + encodeURIComponent(this.tipoDescarga)
                + '&file=' + encodeURIComponent(archivo);
            return vista ? base + '&view=1' : base;
        },

        /** Borrado con el mecanismo global deleteAction(). */
        eliminar(id) {
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permiso para eliminar constancias.');
                return;
            }

            this.deleteAction({
                url: '/constancia-situacion-fiscal/delete',
                id: id,
                name: 'la constancia #' + id,
                table: this.tablaId,
                data: {}
            });
        }

    }));

});