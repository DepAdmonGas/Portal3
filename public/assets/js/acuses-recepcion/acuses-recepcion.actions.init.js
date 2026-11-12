// ============================================================
// Acuses de Recepción — Componente Alpine: alta, gestión de
// documentos (Pendiente), entrega (En proceso), detalle y
// eliminación con el mecanismo global.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('acusesRecepcionComponent', () => ({

        guardando: false,
        cargandoDetalle: false,

        // 'gestion' (Pendiente) | 'entrega' (En proceso) | 'detalle'
        modo: 'gestion',

        acuse: null,
        acuseId: 0,
        documentos: [],

        // Valores derivados expuestos como propiedades planas: el objeto se
        // combina con actions() mediante spread en x-data, y un getter se
        // "congelaría" al evaluarse durante el spread (perdiendo reactividad).
        tablaId: '#tabla-acuses-recepcion',
        tituloGestion: 'Formulario Acuses de Recepción',
        bloqueado: false,

        filtro: '0',

        puedeAgregar: false,
        puedeEditar: false,
        puedeEliminar: false,
        esAdmin: false,

        empresas: [],

        form: {
            id: '',
            empresa: '',
            documento: '',
            paginas: '',
            original: false,
            copia: false,
            quien_recibe: ''
        },

        errores: {},

        init() {
            const c = document.getElementById('acuses-recepcion');
            if (!c) return;

            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.esAdmin = c.dataset.esAdmin === 'true';

            try {
                this.empresas = JSON.parse(c.dataset.empresas || '[]');
            } catch (e) {
                this.empresas = [];
            }

            document.addEventListener('acuses-recepcion:editar', (e) => this.abrirGestion(e.detail.id));
            document.addEventListener('acuses-recepcion:entrega', (e) => this.abrirEntrega(e.detail.id));
            document.addEventListener('acuses-recepcion:detalle', (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('acuses-recepcion:eliminar', (e) => this.eliminar(e.detail.id));
        },

        limpiarDocumento() {
            this.form.documento = '';
            this.form.paginas = '';
            this.form.original = false;
            this.form.copia = false;
        },

        limpiarErrores() {
            this.errores = {};
        },

        /** Alta de un acuse vacío (Pendiente) y apertura de su gestión. */
        async agregar() {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para agregar acuses de recepción.');
                return;
            }

            if (this.guardando) return;
            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const res = await this.createAction({
                    url: '/acuses-recepcion/store',
                    data: {},
                    notify: false
                });

                if (res && res.success && res.id) {
                    this.notify('success', res.message);
                    this.cargarTabla();
                    await this.abrirGestion(res.id);
                } else {
                    this.notify('error', (res && res.message) || 'No fue posible crear el acuse.');
                }
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Carga y abre el modal de gestión de un acuse Pendiente. */
        async abrirGestion(id) {
            if (!this.puedeEditar) {
                this.notify('error', 'No tienes permiso para editar acuses de recepción.');
                return;
            }

            this.modo = 'gestion';
            this.acuse = null;
            this.acuseId = Number(id);
            this.bloqueado = false;
            this.tituloGestion = 'Formulario Acuses de Recepción';
            this.documentos = [];
            this.form.id = id;
            this.limpiarDocumento();
            this.limpiarErrores();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).show();

            try {
                const resp = await axios.get('/acuses-recepcion/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    const d = data.data;

                    // Cada estado tiene su propia vista.
                    if (Number(d.estado) === 2) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).hide();
                        this.cargandoDetalle = false;
                        this.abrirDetalle(id);
                        return;
                    }

                    if (Number(d.estado) === 1) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).hide();
                        this.cargandoDetalle = false;
                        this.abrirEntrega(id);
                        return;
                    }

                    this.acuse = d;
                    this.documentos = d.documentos || [];
                    this.form.id = d.id;
                    this.form.empresa = d.empresa || '';
                } else {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).hide();
                    this.notify('error', data.message || 'No se pudo cargar el acuse.');
                }
            } catch (e) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).hide();
                this.notify('error', 'Error al consultar el acuse de recepción.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Agrega un documento al acuse (sólo en Pendiente). */
        async agregarDocumento() {
            if (!this.puedeEditar || this.bloqueado) return;

            const documento = String(this.form.documento || '').trim();
            const paginas = parseInt(this.form.paginas, 10);

            this.limpiarErrores();

            if (documento === '') {
                this.errores.documento = true;
                this.notify('error', 'El nombre del documento es obligatorio.');
                return;
            }

            if (!paginas || paginas <= 0) {
                this.errores.paginas = true;
                this.notify('error', 'El número de páginas es obligatorio.');
                return;
            }

            if (this.guardando) return;
            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const res = await this.createAction({
                    url: '/acuses-recepcion/documento',
                    data: {
                        id: this.acuseId,
                        documento: documento,
                        paginas: paginas,
                        original: this.form.original ? 1 : 0,
                        copia: this.form.copia ? 1 : 0
                    }
                });

                if (res && res.success) {
                    await this.recargarDocumentos();
                    this.limpiarDocumento();
                }
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Elimina un documento del acuse con confirmación global + loader. */
        async eliminarDocumento(idDocumento, nombre) {
            if (!this.puedeEditar || this.bloqueado) return;
            if (this.guardando) return;

            const result = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: 'El registro: ' + (nombre || ('documento #' + idDocumento)) + ' será eliminado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const resp = await axios.post('/acuses-recepcion/documento/delete', { id: idDocumento });
                const data = resp.data || {};

                if (data.success === true) {
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    await this.recargarDocumentos();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible eliminar el documento.');
                    this.notify('error', data.message || 'No fue posible eliminar el documento.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al eliminar el documento.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Guarda empresa y pasa a En proceso (Pendiente -> En proceso). */
        async guardarEmpresa() {
            const empresa = String(this.form.empresa || '').trim();

            if (empresa === '') {
                this.errores.empresa = true;
                this.notify('error', 'La empresa es obligatoria.');
                return false;
            }

            this.errores.empresa = false;

            const res = await this.createAction({
                url: '/acuses-recepcion/empresa',
                data: { id: this.acuseId, empresa: empresa },
                notify: false
            });

            return !!(res && res.success);
        },

        /** Pasa el acuse a En proceso (botón Continuar del formulario). */
        async pasarAEntrega() {
            if (!this.puedeEditar || this.bloqueado) return;
            if (this.documentos.length === 0) {
                this.notify('error', 'Agrega al menos un documento antes de continuar.');
                return;
            }
            if (this.guardando) return;

            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const ok = await this.guardarEmpresa();

                if (ok) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcuse')).hide();
                    this.cargarTabla();
                }
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Abre el modal de entrega (En proceso): pide quién recibe. */
        async abrirEntrega(id) {
            if (!this.puedeEditar) {
                this.notify('error', 'No tienes permiso para gestionar entregas.');
                return;
            }

            this.modo = 'entrega';
            this.acuse = null;
            this.acuseId = Number(id);
            this.bloqueado = false;
            this.documentos = [];
            this.form.id = id;
            this.form.quien_recibe = '';
            this.limpiarErrores();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFinalizarAcuse')).show();

            try {
                const resp = await axios.get('/acuses-recepcion/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    const d = data.data;

                    if (Number(d.estado) !== 1) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFinalizarAcuse')).hide();
                        this.cargandoDetalle = false;
                        if (Number(d.estado) === 2) {
                            this.abrirDetalle(id);
                        } else {
                            this.abrirGestion(id);
                        }
                        return;
                    }

                    this.acuse = d;
                    this.documentos = d.documentos || [];
                    this.form.id = d.id;
                } else {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFinalizarAcuse')).hide();
                    this.notify('error', data.message || 'No se pudo cargar el acuse.');
                }
            } catch (e) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFinalizarAcuse')).hide();
                this.notify('error', 'Error al consultar el acuse de recepción.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Confirma la entrega: finaliza el acuse (En proceso -> Finalizado). */
        async confirmarEntrega() {
            const quienRecibe = String(this.form.quien_recibe || '').trim();

            if (quienRecibe === '') {
                this.errores.quien_recibe = true;
                this.notify('error', 'El nombre de quién recibe es obligatorio.');
                return;
            }

            if (this.guardando) return;
            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const res = await this.createAction({
                    url: '/acuses-recepcion/finalizar',
                    data: { id: this.acuseId, quien_recibe: quienRecibe }
                });

                if (res && res.success) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFinalizarAcuse')).hide();
                    this.cargarTabla();
                }
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Detalle del acuse (todos los estados). */
        async abrirDetalle(id) {
            this.modo = 'detalle';
            this.acuse = null;
            this.acuseId = Number(id);
            this.bloqueado = false;
            this.tituloGestion = 'Detalle Acuse de Recepción';
            this.documentos = [];
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleAcuse')).show();

            try {
                const resp = await axios.get('/acuses-recepcion/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.acuse = data.data;
                    this.documentos = data.data.documentos || [];
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el detalle.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el acuse de recepción.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        /** Elimina el acuse con confirmación global y loader. */
        async eliminar(id) {
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permiso para eliminar acuses de recepción.');
                return;
            }

            if (id === undefined || id === null || id === '') {
                id = this.acuseId;
            }

            if (!id) {
                this.notify('error', 'Acuse no válido.');
                return;
            }

            if (this.guardando) return;

            const confirmacion = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: 'El registro: el acuse #' + id + ' será eliminado',
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
                const resp = await axios.post('/acuses-recepcion/delete', { id: id });

                this.handleResponse(resp, this.tablaId);

                if (resp.data && resp.data.success) {
                    const modal = document.getElementById('modalAcuse');
                    if (modal && modal.classList.contains('show')) {
                        bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }
                }
            } catch (err) {
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    ? err.response.data.message
                    : 'Error al eliminar el acuse.';

                this.showAlert('error', 'Error', mensaje);
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Abre el modal de búsqueda. */
        abrirBuscar() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarAcuse')).show();
        },

        /** Aplica el filtro elegido y recarga la tabla. */
        buscar() {
            const c = document.getElementById('acuses-recepcion');
            if (c) {
                c._filtro = this.filtro;
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBuscarAcuse')).hide();
            this.cargarTabla();
        },

        /** Recarga la lista de documentos del acuse en gestión. */
        async recargarDocumentos() {
            try {
                const resp = await axios.get('/acuses-recepcion/documentos', { params: { id: this.acuseId } });
                const data = resp.data || {};

                if (data.success === true) {
                    this.documentos = data.data || [];
                } else {
                    this.notify('error', data.message || 'No se pudieron cargar los documentos.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar los documentos.');
            }
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
