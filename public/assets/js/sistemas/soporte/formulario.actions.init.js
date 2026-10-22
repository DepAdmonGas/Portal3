// ============================================================
// Soporte (Sistemas) — Componente Alpine de la vista de crear
// registro (ticket en estado Creando): descripción, prioridad,
// actividades y finalizar registro.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('soporteFormularioComponent', () => ({

        guardando: false,

        endpoint: '/sistemas/soporte',

        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,
        puedeAsignar: false,

        responsables: [],

        idTicket: 0,

        form: {
            id_ticket: 0,
            descripcion: '',
            prioridad: '',
            id_responsable: ''
        },

        errores: {
            descripcion: false,
            prioridad: false,
            actividad: false
        },

        actividades: [],

        nuevaActividad: {
            descripcion: ''
        },

        init() {
            const c = document.getElementById('soporte-formulario');
            if (!c) return;

            this.endpoint = c.dataset.endpoint || '/sistemas/soporte';

            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';
            this.puedeAsignar = c.dataset.puedeAsignar === 'true';

            try {
                this.responsables = JSON.parse(c.dataset.responsables || '[]');
            } catch (e) {
                this.responsables = [];
            }

        this.idTicket = parseInt(c.dataset.idTicket || '0', 10);
        this.form.id_ticket = this.idTicket;

        this.cargarTicket();
    },

        /* -------------------------------------------------------------- */
        /* Utilidades                                                      */
        /* -------------------------------------------------------------- */

        obtenerModal(id) {
            return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
        },

        limpiarErrores() {
            this.errores = { descripcion: false, prioridad: false, actividad: false };
        },

        /* -------------------------------------------------------------- */
        /* Carga inicial                                                   */
        /* -------------------------------------------------------------- */

        async cargarTicket() {
            try {
                const resp = await axios.get(this.endpoint + '/show', { params: { id_ticket: this.idTicket } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.form.descripcion = data.data.descripcion || '';
                    this.form.prioridad = data.data.prioridad || '';
                    this.form.id_responsable = data.data.id_personal_soporte ? String(data.data.id_personal_soporte) : '';
                    this.actividades = data.data.actividades || [];
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el registro.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el registro.');
            }
        },

        /* -------------------------------------------------------------- */
        /* Responsable                                                      */
        /* -------------------------------------------------------------- */

        async guardarResponsable() {
            if (!this.puedeAsignar) {
                return;
            }

            const idResponsable = parseInt(this.form.id_responsable || '0', 10);

            if (!idResponsable) {
                return;
            }

            try {
                await axios.post(this.endpoint + '/asignar-responsable', {
                    id_ticket: this.idTicket,
                    id_responsable: idResponsable
                });
            } catch (e) {
                this.notify('error', 'Error al guardar el responsable.');
            }
        },


        /* -------------------------------------------------------------- */
        /* Descripción / prioridad                                         */
        /* -------------------------------------------------------------- */

        async guardarDescripcion() {
            try {
                await axios.post(this.endpoint + '/editar-descripcion', {
                    id_ticket: this.idTicket,
                    descripcion: this.form.descripcion
                });
            } catch (e) {
                this.notify('error', 'Error al guardar la descripción.');
            }
        },

        async guardarPrioridad() {
            try {
                await axios.post(this.endpoint + '/editar-prioridad', {
                    id_ticket: this.idTicket,
                    prioridad: this.form.prioridad
                });
            } catch (e) {
                this.notify('error', 'Error al guardar la prioridad.');
            }
        },

        /* -------------------------------------------------------------- */
        /* Actividades                                                     */
        /* -------------------------------------------------------------- */

        abrirNuevaActividad() {
            this.nuevaActividad = { descripcion: '' };
            this.errores.actividad = false;
            if (this.$refs && this.$refs.actividadArchivo) {
                this.$refs.actividadArchivo.value = '';
            }
            this.obtenerModal('modalActividad').show();
        },

        async guardarActividad() {
            if (String(this.nuevaActividad.descripcion || '').trim() === '') {
                this.errores.actividad = true;
                this.notify('error', 'La descripción es obligatoria.');
                return;
            }

            const fd = new FormData();
            fd.append('id_ticket', String(this.idTicket));
            fd.append('descripcion', String(this.nuevaActividad.descripcion).trim());

            const archivo = this.$refs.actividadArchivo && this.$refs.actividadArchivo.files[0];
            if (archivo) {
                fd.append('archivo', archivo);
            }

            try {
                const resp = await axios.post(this.endpoint + '/agregar-actividad', fd, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                const data = resp.data || {};

                if (data.success === true) {
                    this.obtenerModal('modalActividad').hide();
                    this.notify('success', data.message);
                    await this.cargarActividades();
                } else {
                    this.notify('error', data.message || 'No fue posible agregar la actividad.');
                }
            } catch (e) {
                this.notify('error', 'Error al agregar la actividad.');
            }
        },

        async cargarActividades() {
            try {
                const resp = await axios.get(this.endpoint + '/actividades', { params: { id_ticket: this.idTicket } });
                const data = resp.data || {};
                this.actividades = (data.success && Array.isArray(data.data)) ? data.data : [];
            } catch (e) {
                this.actividades = [];
            }
        },

        eliminarActividad(idActividad) {
            this.deleteAction({
                url: this.endpoint + '/eliminar-actividad',
                id: idActividad,
                name: '#' + idActividad,
                data: { id_actividad: idActividad }
            }).then((res) => {
                if (res && res.success) {
                    this.cargarActividades();
                }
            });
        },

        /* -------------------------------------------------------------- */
        /* Finalizar registro                                              */
        /* -------------------------------------------------------------- */

        validar() {
            this.limpiarErrores();

            let hayError = false;

            if (String(this.form.descripcion || '').trim() === '') {
                this.errores.descripcion = true;
                hayError = true;
            }

            if (String(this.form.prioridad || '').trim() === '') {
                this.errores.prioridad = true;
                hayError = true;
            }

            return hayError;
        },

        async finalizarRegistro() {
            if (this.guardando) return;

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;

            try {
                await this.guardarDescripcion();
                await this.guardarPrioridad();

                const resp = await this.createAction({
                    url: this.endpoint + '/finalizar-registro',
                    data: { id_ticket: this.idTicket }
                });

                if (resp && resp.success) {
                    window.location.href = this.endpoint;
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al enviar el registro.';
                this.notify('error', msg);
            } finally {
                this.guardando = false;
            }
        }

    }));

});
