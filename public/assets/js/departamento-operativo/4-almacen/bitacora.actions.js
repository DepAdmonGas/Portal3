// ============================================================
// Bitácora de Maquinaria y Equipos — Almacén
// Pantalla índice (calendario + registros del día).
// Todas las acciones del flujo viven en pantallas completas
// (nuevo, registro/mantenimiento, firma, editar) como el legacy;
// aquí sólo quedan el modal del día, el offcanvas de comentarios
// (patrón de solicitud-cheque) y el modal de evidencias.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('bitacoraActions', () => ({

        // Raíz de la bitácora: los endpoints JSON y las pantallas comparten prefijo
        // (cada pantalla tiene su propio sufijo: -nuevo, -detalle, -editar…).
        BASE: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        BASE_RAIZ: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        pdfBase: '/departamento-operativo/almacen/maquinaria-equipos-bitacora/pdf',

        // Contexto
        idEquipo: 0,
        equipo: {},
        esUsuarioEstacion: false,

        // Día
        dia: { fecha: '', fecha_label: '', registros: [] },
        fechaSeleccionada: '',
        actividadesDia: [],
        cargandoDia: false,

        // Editar (modal de costo)
        editarReg: {},
        editarRegistroId: 0,
        editarRegistroLabel: '',
        costoEdicion: 0,
        guardando: false,

        // Evidencias (modal)
        evidencias: [],
        evidenciaRegistroId: 0,
        evidenciaRegistroLabel: '',
        subiendo: false,

        // Comentarios (offcanvas)
        comentarios: [],
        comentarioRegistroId: 0,
        comentarioRegistroLabel: '',
        nuevoComentario: '',
        guardandoComentario: false,

        modales: {},

        /* ------------------------------------------------------------ */
        /* Init                                                          */
        /* ------------------------------------------------------------ */

        initBitacora() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE = c.dataset.baseUrl || this.BASE;
                this.BASE_RAIZ = c.dataset.baseUrl || this.BASE_RAIZ;
                this.pdfBase = c.dataset.pdfBase || this.pdfBase;
                this.idEquipo = parseInt(c.dataset.idEquipo || '0', 10);
                this.esUsuarioEstacion = c.dataset.esUsuarioEstacion === 'true';

                try {
                    this.equipo = JSON.parse(c.dataset.equipo || '{}');
                } catch (e) {
                    this.equipo = {};
                }
            }

            const elDia = document.getElementById('modalDiaBitacora');
            const elEv = document.getElementById('modalEvidenciasBitacora');
            const elEd = document.getElementById('modalEditarBitacora');

            if (elDia) {
                this.modales.dia = new bootstrap.Modal(elDia);
            }

            if (elEv) {
                this.modales.evidencias = new bootstrap.Modal(elEv);
            }

            if (elEd) {
                this.modales.editar = new bootstrap.Modal(elEd);
            }

            document.addEventListener('mqb:dia', (e) => {
                this.cargarDia(e.detail.fecha, { abrirModal: true });
            });

            this.initCalendario();
        },

        /* ------------------------------------------------------------ */
        /* URLs de las pantallas completas                               */
        /* ------------------------------------------------------------ */

        urlNuevo() {
            return this.BASE_RAIZ + '-nuevo/' + this.idEquipo;
        },

        urlDetalle(id) {
            return this.BASE_RAIZ + '-detalle/' + id;
        },

        urlMantenimiento(id) {
            return this.BASE_RAIZ + '-mantenimiento/' + id;
        },

        urlEditar(id) {
            return this.BASE_RAIZ + '-editar/' + id;
        },

        urlFirma(id) {
            return this.BASE_RAIZ + '-firma/' + id;
        },

        /* ------------------------------------------------------------ */
        /* Día                                                           */
        /* ------------------------------------------------------------ */

        async cargarDia(fecha, opciones) {
            if (!fecha) {
                return;
            }

            const opts = opciones || {};
            this.cargandoDia = true;

            try {
                const { data } = await axios.post(this.BASE + '/actividades', {
                    idEquipo: this.idEquipo,
                    fecha: fecha
                });

                this.dia = data.data || { fecha: fecha, fecha_label: '', registros: [] };
                this.fechaSeleccionada = this.dia.fecha_label || fecha;
                this.actividadesDia = this.dia.registros || [];

                if (opts.abrirModal && this.modales.dia) {
                    this.modales.dia.show();
                }
            } catch (e) {
                this.error(e);
            } finally {
                this.cargandoDia = false;
            }
        },

        refrescarDia() {
            if (this.dia.fecha) {
                this.cargarDia(this.dia.fecha);
            }

            document.dispatchEvent(new CustomEvent('mqb:refrescar'));
        },

        /* ------------------------------------------------------------ */
        /* Flujo del registro                                            */
        /* ------------------------------------------------------------ */

        async completar(reg) {
            try {
                const { data } = await axios.post(this.BASE + '/completar', { id: reg.id });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Registro completado.');
                this.refrescarDia();
            } catch (e) {
                this.error(e);
            }
        },

        async eliminarRegistro(reg) {
            if (this.guardando) {
                return;
            }

            const res = await this.bajaAction({
                url: this.BASE + '/eliminar-registro',
                id: reg.id,
                name: 'Registro ' + (reg.orden_label || '') + ' y sus consecuentes',
                table: null
            });

            if (res && res.success) {
                this.refrescarDia();
            }
        },

        /* ------------------------------------------------------------ */
        /* Editar (modal de costo, como el legacy)                       */
        /* ------------------------------------------------------------ */

        async abrirEditar(item) {
            this.editarRegistroId = item.id;
            this.editarRegistroLabel = String(parseInt(item.orden || '0', 10) || '0').padStart(2, '0');
            this.editarReg = {};
            this.costoEdicion = 0;

            // Igual que comentarios/evidencias: el modal del día se cierra.
            if (this.modales.dia) {
                this.modales.dia.hide();
            }

            try {
                const { data } = await axios.post(this.BASE + '/detalle', { id: item.id });
                this.editarReg = data.data || {};
                this.costoEdicion = this.editarReg.costo ?? 0;
            } catch (e) {
                this.editarReg = {};
                this.error(e);
                return;
            }

            if (this.modales.editar) {
                this.modales.editar.show();
            }
        },

        async guardarCostoModal() {
            if (this.guardando) {
                return;
            }

            this.guardando = true;

            try {
                const { data } = await axios.post(this.BASE + '/editar-costo', {
                    id: this.editarRegistroId,
                    costo: this.costoEdicion
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Costo actualizado.');

                if (this.modales.editar) {
                    this.modales.editar.hide();
                }

                this.refrescarDia();
            } catch (e) {
                this.error(e);
            } finally {
                this.guardando = false;
            }
        },

        /* ------------------------------------------------------------ */
        /* Evidencias (modal, diseño del legacy)                         */
        /* ------------------------------------------------------------ */

        async abrirEvidencias(item) {
            this.evidenciaRegistroId = item.id;
            this.evidenciaRegistroLabel = String(parseInt(item.orden || '0', 10) || '0').padStart(2, '0');
            this.evidencias = [];

            // Igual que comentarios: el modal del día se cierra para que el de
            // evidencias no quede apilado encima.
            if (this.modales.dia) {
                this.modales.dia.hide();
            }

            if (this.modales.evidencias) {
                this.modales.evidencias.show();
            }

            await this.cargarEvidencias(item.id);
        },

        regresarAlDia() {
            const el = document.getElementById('offcanvasComentariosBitacora');

            if (el) {
                bootstrap.Offcanvas.getOrCreateInstance(el).hide();
            }

            if (this.modales.evidencias) {
                this.modales.evidencias.hide();
            }

            if (this.modales.editar) {
                this.modales.editar.hide();
            }

            if (this.modales.dia) {
                this.modales.dia.show();
            }
        },

        async cargarEvidencias(id) {
            try {
                const { data } = await axios.post(this.BASE + '/detalle', { id: id });
                this.evidencias = (data.data && data.data.evidencias) || [];
            } catch (e) {
                this.evidencias = [];
                this.error(e);
            }
        },

        async subirEvidenciaModal() {
            const input = document.getElementById('inputEvidenciaModal');

            if (!input || !input.files || input.files.length === 0) {
                this.showAlert('warning', 'Sin archivo', 'Selecciona un archivo.');
                return;
            }

            const form = new FormData();
            form.append('id', this.evidenciaRegistroId);
            form.append('evidencia', input.files[0]);

            this.subiendo = true;

            try {
                const { data } = await axios.post(this.BASE + '/evidencias/subir', form, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Evidencia subida.');
                input.value = '';
                await this.cargarEvidencias(this.evidenciaRegistroId);
                this.refrescarDia();
            } catch (e) {
                this.error(e);
            } finally {
                this.subiendo = false;
            }
        },

        async eliminarEvidencia(evidencia) {
            const res = await this.bajaAction({
                url: this.BASE + '/evidencias/eliminar',
                id: evidencia.id,
                name: evidencia.nombre_original || 'evidencia',
                table: null
            });

            if (res && res.success) {
                await this.cargarEvidencias(this.evidenciaRegistroId);
            }
        },

        /* ------------------------------------------------------------ */
        /* Comentarios (offcanvas tipo chat, patrón solicitud-cheque)    */
        /* ------------------------------------------------------------ */

        async abrirComentarios(item) {
            this.comentarioRegistroId = item.id;
            this.comentarioRegistroLabel = String(parseInt(item.orden || '0', 10) || '0').padStart(2, '0');
            this.nuevoComentario = '';
            this.comentarios = [];

            // El modal del día se cierra: el offcanvas toma su lugar.
            if (this.modales.dia) {
                this.modales.dia.hide();
            }

            await this.cargarComentarios(item.id);

            const el = document.getElementById('offcanvasComentariosBitacora');

            if (el) {
                bootstrap.Offcanvas.getOrCreateInstance(el).show();
            }
        },

        async cargarComentarios(id) {
            try {
                const { data } = await axios.post(this.BASE + '/comentarios', { id: id });

                this.comentarios = (data.comentarios || []).slice().reverse().map((c) => ({
                    id: c.id,
                    comentario: c.comentario,
                    nombre_usuario: c.nombre_usuario,
                    fecha_label: c.fecha_label,
                    esMio: !!c.es_propio
                }));
            } catch (e) {
                this.comentarios = [];
            }
        },

        async agregarComentario() {
            const texto = (this.nuevoComentario || '').trim();

            if (!texto || this.guardandoComentario) {
                return;
            }

            this.guardandoComentario = true;

            try {
                const { data } = await axios.post(this.BASE + '/comentarios/agregar', {
                    id: this.comentarioRegistroId,
                    comentario: texto
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.nuevoComentario = '';
                await this.cargarComentarios(this.comentarioRegistroId);
                this.refrescarDia();
            } catch (e) {
                this.error(e);
            } finally {
                this.guardandoComentario = false;
            }
        },

        /* ------------------------------------------------------------ */
        /* Utilidades de UI                                              */
        /* ------------------------------------------------------------ */

        colorFila(estatus) {
            return ['#ffb6af', '#fcfcda', '#b0f2c2'][estatus] || '#ffffff';
        },

        claseEstatus(estatus) {
            return ['bg-danger', 'bg-warning', 'bg-success'][estatus] || 'bg-secondary';
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
