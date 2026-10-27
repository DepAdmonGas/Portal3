// ============================================================
// Soporte (Sistemas) — Componente Alpine del listado: creación
// (redirige a la vista dedicada), detalle, comentarios (offcanvas),
// finalización y cancelación de tickets.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('soporteComponent', () => ({

        cargandoDetalle: false,

        puedeConsultar: false,
        puedeAgregar: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,

        detalle: null,
        cacheDetalle: {},

        comentariosData: null,
        comentariosCargados: false,
        nuevoComentario: '',
        guardandoComentario: false,

        // Estado del atributo :disabled del botón de comentario (Alpine no
        // re-evalúa un binding booleano con "!" u "||").
        comentarioDeshabilitado: true,

        // Solicitud de revisión (modal de "No concluido")
        idTicketRevision: 0,
        motivoRevision: '',

        filtroEstado: '5',

        /* NOTA: es un método y no un getter. El x-data de la vista hace
           {...acciones(), ...componente()}: el operador spread copia el VALOR
           de un getter (evaluado en ese instante), por lo que un getter
           quedaría congelado. */
        tablaId() {
            return '#tabla-soporte';
        },

        init() {
            const c = document.getElementById('soporte');
            if (!c) return;

            this.puedeConsultar = c.dataset.puedeConsultar === 'true';
            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';

            document.addEventListener('soporte:detalle', (e) => this.verDetalle(e.detail.id));
            document.addEventListener('soporte:comentarios', (e) => this.verComentarios(e.detail.id));
            document.addEventListener('soporte:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('soporte:eliminar', (e) => this.eliminar(e.detail.id));
        },

        /* -------------------------------------------------------------- */
        /* Utilidades                                                      */
        /* -------------------------------------------------------------- */

        mostrarModal(id) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show();
        },

        ocultarModal(id) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).hide();
        },

        mostrarOffcanvas(id) {
            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById(id)).show();
        },

        cargarTabla() {
            const t = $(this.tablaId());
            if (t.length && $.fn.DataTable.isDataTable(this.tablaId())) {
                t.DataTable().ajax.reload(null, false);
            }
        },

        /* -------------------------------------------------------------- */
        /* Crear / editar registro                                         */
        /* -------------------------------------------------------------- */

        async nuevoRegistro(categoria = 'Actividad') {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para crear registros.');
                return;
            }

            try {
                const resp = await this.createAction({
                    url: '/sistemas/soporte/nuevo-folio',
                    data: { categoria: categoria },
                    notify: false
                });

                if (!resp || !resp.success) {
                    return;
                }

                window.location.href = '/sistemas/soporte-formulario/' + resp.id_ticket;
            } catch (e) {
                this.notify('error', 'No se pudo crear el registro.');
            }
        },

        abrirEditar(idTicket) {
            if (!this.puedeEditar) {
                this.notify('error', 'No tienes permiso para editar registros.');
                return;
            }

            window.location.href = '/sistemas/soporte-formulario/' + idTicket;
        },

        /* -------------------------------------------------------------- */
        /* Detalle                                                         */
        /* -------------------------------------------------------------- */

        async verDetalle(idTicket) {
            // 1) Se muestra el modal de inmediato y se pinta con los datos
            //    que la tabla ya tiene en memoria (sin esperar al servidor).
            const fila = this.buscarFila(idTicket);
            const cacheado = this.cacheDetalle[idTicket];

            this.detalle = cacheado
                ? { ...cacheado }
                : (fila ? { ...fila, actividades: [], evidencias: [] } : null);

            this.cargandoDetalle = true;
            this.mostrarModal('modalDetalle');

            // 2) Se completa la información (actividades y evidencias) en segundo plano.
            try {
                const resp = await axios.get('/sistemas/soporte/show', { params: { id_ticket: idTicket } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.detalle = data.data;
                    this.cacheDetalle[idTicket] = data.data;
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el detalle.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el ticket.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Busca la fila del ticket en los datos ya cargados por la tabla. */
        buscarFila(idTicket) {
            const filas = window.soporteFilas || [];

            for (let i = 0; i < filas.length; i++) {
                if (Number(filas[i].id_ticket) === Number(idTicket)) {
                    return filas[i];
                }
            }

            return null;
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
            if (valor === 0) return 'badge bg-warning text-white';

            return 'badge bg-danger text-white';
        },

        /** Devuelve el valor o "S/I" cuando el campo viene vacío. */
        valorCampo(valor) {
            const texto = (valor === null || valor === undefined) ? '' : String(valor).trim();

            return texto === '' ? 'S/I' : texto;
        },

        /* -------------------------------------------------------------- */
        /* Comentarios                                                     */
        /* -------------------------------------------------------------- */

        async verComentarios(idTicket, conservarContenido = false) {
            if (!conservarContenido) {
                this.comentariosData = null;
                this.comentariosCargados = false;
                this.nuevoComentario = '';
                this.mostrarOffcanvas('modalComentarios');
            }

            try {
                const resp = await axios.get('/sistemas/soporte/comentarios', { params: { id_ticket: idTicket } });
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
                    url: '/sistemas/soporte/guardar-comentario',
                    data: { id_ticket: idTicket, comentario: this.nuevoComentario },
                    notifySuccess: false
                });

                if (resp && resp.success) {
                    this.nuevoComentario = '';
                    if (resp.data) this.comentariosData = resp.data;
                    this.$nextTick(() => this.scrollComentariosAlFondo());
                }
            } catch (e) {
                this.notify('error', 'Error al guardar el comentario.');
            } finally {
                this.guardandoComentario = false;
                this.comentarioDeshabilitado = !this.nuevoComentario.trim();
            }
        },

        /* -------------------------------------------------------------- */
        /* Validación de la solicitud (solicitante)                          */
        /* -------------------------------------------------------------- */

        /**
         * El área de Sistemas terminó la atención: el solicitante confirma
         * que la actividad se realizó y la solicitud se cierra.
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
                this.ocultarModal('modalDetalle');
                this.cargarTabla();
            }
        },

        /**
         * El solicitante devuelve la solicitud al área de Sistemas porque el
         * resultado no es el esperado o sigue inconclusa.
         *
         * Se cierra la alerta anterior (si hubiera alguna) y se abre el modal
         * con el motivo, que es totalmente editable.
         */
        solicitarRevision() {
            if (!this.detalle) {
                return;
            }

            if (window.Swal && typeof Swal.close === 'function') {
                Swal.close();
            }

            this.idTicketRevision = this.detalle.id_ticket;
            this.motivoRevision = 'El resultado de la atención no es el esperado o la solicitud sigue inconclusa.';

            // Se cierra el modal de detalle y se abre el de la revisión.
            this.ocultarModal('modalDetalle');

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
                url: '/sistemas/soporte/solicitar-revision',
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
        /* Finalizar / cancelar                                            */
        /* -------------------------------------------------------------- */

        async finalizarSoporte(idTicket, finalizar) {
            try {
                const resp = await this.createAction({
                    url: '/sistemas/soporte/finalizar-soporte',
                    data: { id_ticket: idTicket, finalizar: finalizar ?? 3 }
                });

                if (resp && resp.success) {
                    this.ocultarModal('modalDetalle');
                    this.cargarTabla();
                }
            } catch (e) {
                this.notify('error', 'Error al finalizar el soporte.');
            }
        },

        eliminar(idTicket) {
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permiso para cancelar tickets.');
                return;
            }

            this.deleteAction({
                url: '/sistemas/soporte/cancelar-ticket',
                id: idTicket,
                name: 'el ticket #' + idTicket,
                table: this.tablaId(),
                data: { id_ticket: idTicket }
            });
        },

        /* -------------------------------------------------------------- */
        /* Búsqueda                                                        */
        /* -------------------------------------------------------------- */

        abrirBuscar() {
            this.mostrarModal('modalBuscar');
        },

        buscar() {
            this.ocultarModal('modalBuscar');
            const t = $(this.tablaId());
            if (t.length && $.fn.DataTable.isDataTable(this.tablaId())) {
                t.DataTable().ajax.url('/sistemas/soporte/data?estado=' + encodeURIComponent(this.filtroEstado)).load();
            }
        }

    }));

});
