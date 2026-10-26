// ============================================================
// Soporte (Estaciones) — Componente Alpine del listado:
// comentarios (offcanvas), eliminación y búsqueda.
//
// El selector de estación/departamento lo imprime el layout a partir de
// moduleStationKey (ver ModuleStationService::render). Al cambiar el
// contexto el componente module-station-selector.js recarga la página
// completa: así el badge de pendientes, la columna "Estación" y las
// filas de la tabla quedan siempre consistentes entre sí.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('soporteEstacionesComponent', () => ({

        comentariosData: null,
        comentariosCargados: false,
        nuevoComentario: '',
        guardandoComentario: false,

        // Estado del atributo :disabled del botón de comentario (Alpine no
        // re-evalúa un binding booleano con "!" u "||").
        comentarioDeshabilitado: true,

        filtroEstado: '5',
        idResponsable: '',
        textoBusqueda: '',
        responsables: [],

        puedeFiltrarResponsable: false,
        puedeAgregar: false,

        ticketAsignar: null,
        idResponsableAsignar: '',
        guardandoAsignacion: false,

        // Estado del atributo :disabled del botón de asignar (Alpine no
        // re-evalúa un binding booleano con "!" u "||").
        asignarDeshabilitado: true,

        detalle: null,
        idTicketRevision: 0,
        motivoRevision: '',


        endpoint: '/sistemas/estaciones',
        tablaId: 'tabla-soporte-estaciones',
        eventos: 'soporte-estaciones',

        init() {
            const c = document.getElementById('soporte-estaciones')
                || document.getElementById('soporte-actividades');
            if (!c) return;

            this.endpoint = c.dataset.endpoint || '/sistemas/estaciones';
            this.tablaId = c.dataset.tablaId || 'tabla-soporte-estaciones';
            this.eventos = c.dataset.eventos || 'soporte-estaciones';

            try {
                this.responsables = JSON.parse(c.dataset.responsables || '[]');
            } catch (e) {
                this.responsables = [];
            }

            this.puedeFiltrarResponsable = c.dataset.puedeFiltrarResponsable === 'true';
            this.puedeAgregar = c.dataset.puedeAgregar === 'true';

            document.addEventListener(this.eventos + ':comentarios', (e) => this.verComentarios(e.detail.id));
            document.addEventListener(this.eventos + ':eliminar', (e) => this.eliminar(e.detail.id));
            document.addEventListener(this.eventos + ':asignar', (e) => this.abrirAsignar(e.detail.id));
            document.addEventListener(this.eventos + ':detalle', (e) => this.abrirDetalle(e.detail.id));
        },

        /* -------------------------------------------------------------- */
        /* Alta de registro                                                 */
        /* -------------------------------------------------------------- */

        /**
         * Crea el folio del registro y abre la vista de captura.
         * Lo usa Actividades (Sistemas) para registrar una actividad.
         */
        async nuevoRegistro() {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para crear registros.');
                return;
            }

            const resp = await this.createAction({
                url: this.endpoint + '/nuevo-folio',
                data: { categoria: 'Actividad' },
                notify: false
            });

            if (resp && resp.success && resp.id_ticket) {
                window.location.href = this.endpoint + '/nueva/' + resp.id_ticket;
            } else if (resp && resp.message) {
                this.notify('error', resp.message);
            }
        },


        /* -------------------------------------------------------------- */
        /* Utilidades                                                      */
        /* -------------------------------------------------------------- */

        mostrarOffcanvas(id) {
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById(id)).show();
        },

        cargarTabla() {
            const t = $('#' + this.tablaId);

            if (t.length && $.fn.DataTable.isDataTable('#' + this.tablaId)) {
                const url = this.endpoint + '/data'
                    + '?estado=' + encodeURIComponent(this.filtroEstado)
                    + '&id_responsable=' + encodeURIComponent(this.idResponsable || '')
                    + '&texto=' + encodeURIComponent(this.textoBusqueda || '');

                t.DataTable().ajax.url(url).load();
            }
        },

        /** Devuelve el valor o "S/I" cuando el campo viene vacío. */
        valorCampo(valor) {
            const texto = (valor === null || valor === undefined) ? '' : String(valor).trim();

            return texto === '' ? 'S/I' : texto;
        },

        /** Clase del badge de prioridad: Baja (verde), Media (amarillo), Alta (rojo). */
        clasePrioridad(prioridad) {
            const valor = String(prioridad || '').trim();

            if (valor === 'Baja') return 'badge bg-success text-white';
            if (valor === 'Media') return 'badge bg-warning text-white';
            if (valor === 'Alta') return 'badge bg-danger text-white';

            return 'badge bg-secondary text-white';
        },

        /** Clase del badge de estatus: Finalizado (verde), Creando (amarillo), demás (rojo). */
        claseEstado(estado) {
            const valor = Number(estado);

            if (valor === 3) return 'badge bg-success text-white';
            if (valor === 2) return 'badge bg-warning text-white';
            if (valor === 0) return 'badge bg-warning text-white';

            return 'badge bg-danger text-white';
        },


        /* -------------------------------------------------------------- */
        /* Detalle (modal)                                                  */
        /* -------------------------------------------------------------- */

        /**
         * Abre el modal con el detalle del ticket: información, responsable,
         * fechas comprometidas y actividades (los comentarios se consultan
         * con el icono de la tabla).
         */
        async abrirDetalle(idTicket) {
            this.detalle = null;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();

            try {
                const resp = await axios.get(this.endpoint + '/show', { params: { id_ticket: idTicket } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.detalle = data.data;

                    this.$nextTick(() => {
                        const btn = document.getElementById('se-btn-seguimiento');
                        if (btn) {
                            btn.setAttribute('href', this.endpoint + '/seguimiento/' + data.data.id_ticket);
                        }
                    });
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el detalle.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el ticket.');
            }
        },

        /**
         * El solicitante valida el trabajo del área de Sistemas. La ruta es
         * la de Soporte (Sistemas) porque la validación la hace quien
         * solicitó el registro, sea del módulo que sea.
         */
        async validarSolicitud() {
            if (!this.detalle) {
                return;
            }

            const idTicket = this.detalle.id_ticket;

            const result = await Swal.fire({
                title: '¿Confirmar que la actividad se realizó?',
                text: 'Al validar, tu solicitud se marcará como finalizada.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, validar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                reverseButtons: true
            });

            if (!result.isConfirmed) return;

            const resp = await this.createAction({
                url: '/sistemas/soporte/validar-solicitud',
                data: { id_ticket: idTicket }
            });

            if (resp && resp.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).hide();
                this.cargarTabla();
            }
        },

        /**
         * El solicitante devuelve la actividad al área de Sistemas porque el
         * resultado no es el esperado o la actividad sigue inconclusa.
         */
        solicitarRevision() {
            if (!this.detalle) {
                return;
            }

            if (window.Swal && typeof Swal.close === 'function') {
                Swal.close();
            }

            this.idTicketRevision = this.detalle.id_ticket;
            this.motivoRevision = 'El resultado de la atención no es el esperado o la actividad sigue inconclusa.';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRevision')).show();

            this.$nextTick(() => {
                const campo = document.getElementById('se-motivo-revision');

                if (campo) {
                    campo.focus();
                    campo.setSelectionRange(campo.value.length, campo.value.length);
                }
            });
        },

        /**
         * Confirma la solicitud de revisión desde el modal.
         */
        async confirmarSolicitarRevision() {
            if (!this.idTicketRevision) {
                return;
            }

            const motivo = String(this.motivoRevision || '').trim();

            if (motivo === '') {
                this.notify('error', 'Escribe el motivo de la solicitud de revisión.');
                return;
            }

            const resp = await this.createAction({
                url: this.endpoint + '/solicitar-revision',
                data: { id_ticket: this.idTicketRevision, motivo: motivo }
            });

            if (resp && resp.success) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRevision')).hide();
                this.motivoRevision = '';
                this.idTicketRevision = 0;
                this.cargarTabla();
            }
        },

        /* -------------------------------------------------------------- */
        /* Asignación del responsable                                        */
        /* -------------------------------------------------------------- */


        /**
         * Abre el modal de asignación con la información del ticket.
         * Es el equivalente al modal "Asignar Personal" del sistema anterior.
         */
        async abrirAsignar(idTicket) {
            this.ticketAsignar = null;
            this.idResponsableAsignar = '';
            this.guardandoAsignacion = false;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAsignar')).show();

            try {
                const resp = await axios.get(this.endpoint + '/show', { params: { id_ticket: idTicket } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.ticketAsignar = data.data;
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el ticket.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el ticket.');
            }
        },

        async asignarResponsable() {
            if (this.guardandoAsignacion) return;

            const id = Number(this.idResponsableAsignar || 0);

            if (!id) {
                this.notify('error', 'Selecciona un responsable.');
                return;
            }

            if (!this.ticketAsignar) {
                this.notify('error', 'No se pudo cargar el ticket.');
                return;
            }

            this.guardandoAsignacion = true;
            this.asignarDeshabilitado = true;

            try {
                const resp = await this.createAction({
                    url: this.endpoint + '/asignar-responsable',
                    data: { id_ticket: this.ticketAsignar.id_ticket, id_responsable: id }
                });

                if (resp && resp.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAsignar')).hide();
                    this.cargarTabla();
                }
            } catch (e) {
                this.notify('error', 'Error al asignar el responsable.');
            } finally {
                this.guardandoAsignacion = false;
                this.asignarDeshabilitado = !this.idResponsableAsignar;
            }
        },

        /* -------------------------------------------------------------- */
        /* Comentarios                                                     */
        /* -------------------------------------------------------------- */

        async verComentarios(idTicket) {
            this.comentariosData = null;
            this.comentariosCargados = false;
            this.nuevoComentario = '';
            this.mostrarOffcanvas('modalComentarios');

            try {
                const resp = await axios.get(this.endpoint + '/comentarios', { params: { id_ticket: idTicket } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.comentariosData = data.data;
                } else {
                    this.notify('error', data.message || 'No se pudieron cargar los comentarios.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar los comentarios.');
            } finally {
                this.comentariosCargados = true;
                this.$nextTick(() => this.scrollComentariosAlFondo());
            }
        },

        scrollComentariosAlFondo() {
            const contenedor = this.$refs.chatContainer;
            if (contenedor) {
                contenedor.scrollTop = contenedor.scrollHeight;
            }
        },

        async guardarComentario() {
            if (this.guardandoComentario) return;

            if (String(this.nuevoComentario || '').trim() === '') {
                this.notify('error', 'El comentario es obligatorio.');
                return;
            }

            const idTicket = this.comentariosData ? this.comentariosData.ticket.id_ticket : 0;
            this.guardandoComentario = true;
            this.comentarioDeshabilitado = true;

            try {
                const resp = await this.createAction({
                    url: this.endpoint + '/guardar-comentario',
                    data: { id_ticket: idTicket, comentario: this.nuevoComentario }
                });

                if (resp && resp.success) {
                    this.nuevoComentario = '';
                    await this.verComentarios(idTicket);
                    this.cargarTabla();
                }
            } catch (e) {
                this.notify('error', 'Error al guardar el comentario.');
            } finally {
                this.guardandoComentario = false;
                this.comentarioDeshabilitado = !this.nuevoComentario.trim();
            }
        },

        /* -------------------------------------------------------------- */
        /* Eliminar                                                        */
        /* -------------------------------------------------------------- */

        eliminar(idTicket) {
            this.deleteAction({
                url: this.endpoint + '/cancelar-ticket',
                id: idTicket,
                name: 'el ticket #' + idTicket,
                table: '#' + this.tablaId,
                data: { id_ticket: idTicket }
            });
        },

        /* -------------------------------------------------------------- */
        /* Búsqueda                                                        */
        /* -------------------------------------------------------------- */

        abrirBuscar() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscar')).show();
        },

        buscar() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscar')).hide();
            this.cargarTabla();
        },

        /** Limpia los filtros del modal de búsqueda. */
        limpiarBusqueda() {
            this.textoBusqueda = '';
            this.filtroEstado = '5';
            this.idResponsable = '';
            this.cargarTabla();
        }


    }));

});
