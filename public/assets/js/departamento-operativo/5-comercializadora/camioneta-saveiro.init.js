document.addEventListener('alpine:init', () => {
    Alpine.data('camionetaSaveiroComponent', () => ({
        BASE_URL: '/departamento-operativo/comercializadora/camioneta-saveiro',
        tipos: [
            'Documentos Generales',
            'Facturas',
            'Póliza de Seguro',
            'Tarjeta de circulación ',
            'Tenencia',
            'Servicios',
            'Verificación'
        ],
        tipoActual: 'Todos los documentos',
        tablaDt: null,

        editando: false,
        guardando: false,
        form: {
            id: 0,
            fecha: '',
            descripcion: '',
            archivo: null
        },
        errors: {
            fecha: false,
            descripcion: false,
            archivo: false
        },

        documentoSeleccionadoId: 0,
        documentoSeleccionadoDescripcion: '',
        cargandoComentarios: false,
        guardandoComentario: false,
        comentarios: [],
        nuevoComentario: '',
        errorComentario: false,

init() {
    // 1. Detectar si la página fue recargada (F5 / Reload)
    const navEntries = performance.getEntriesByType('navigation');
    const esRecarga = (navEntries.length > 0 && navEntries[0].type === 'reload') 
                   || (performance.navigation && performance.navigation.type === 1);

    const savedTipo = sessionStorage.getItem('camioneta_saveiro_tipo');

    if (esRecarga && savedTipo && (savedTipo === 'Todos los documentos' || this.tipos.includes(savedTipo))) {
        this.tipoActual = savedTipo;
    } else {
        this.tipoActual = 'Todos los documentos';
        sessionStorage.setItem('camioneta_saveiro_tipo', 'Todos los documentos');
    }

    window.camionetaSaveiroInstance = this;

    this.$nextTick(() => {
        // Asegura que el valor del select visual quede idéntico al valor en memoria tras recargar
        const selectEl = document.getElementById('selectTipoDoc');
        if (selectEl) {
            selectEl.value = this.tipoActual;
        }

        this.initDataTable();
    });

    window.addEventListener('saveiro:descargar', (e) => {
        this.download('camioneta-saveiro', e.detail.archivo);
    });
    window.addEventListener('saveiro:editar', (e) => this.abrirModalEditar(e.detail));
    window.addEventListener('saveiro:eliminar', (e) => this.eliminarRegistro(e.detail.id, e.detail.descripcion));
    window.addEventListener('saveiro:comentarios', (e) => this.abrirModalComentarios(e.detail.id, e.detail.descripcion));
},
        cambiarTipo() {
            sessionStorage.setItem('camioneta_saveiro_tipo', this.tipoActual);
            if (this.tablaDt) {
                const esTodos = this.tipoActual === 'Todos los documentos';
                this.tablaDt.column(1).visible(esTodos);
                this.tablaDt.ajax.url(`${this.BASE_URL}/data?tipo=${encodeURIComponent(this.tipoActual)}`).load();
            }
        },

        initDataTable() {
            if (typeof window.jQuery === 'undefined') return;

            const $ = window.jQuery;
            const tableEl = $('#tabla-camioneta');
            if (!tableEl.length) return;

            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().destroy();
            }

            const esTodos = this.tipoActual === 'Todos los documentos';

            this.tablaDt = tableEl.DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: `${this.BASE_URL}/data?tipo=${encodeURIComponent(this.tipoActual)}`,
                    type: 'GET',
                    dataSrc: (json) => json?.data || []
                },
                order: [[0, 'desc']],
                pageLength: 15,
                lengthMenu: [15, 30, 50, 100],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json'
                },
                columns: [
                    { 
                        title: '#', 
                        data: 'id',
                        className: 'text-center align-middle', 
                        width: '96px'
                    },
                    { 
                        title: 'Tipo de Documento', 
                        data: 'tipo', 
                        className: 'align-middle text-start text-nowrap',
                        visible: esTodos
                    },
                    { title: 'Fecha', data: 'fecha', className: 'text-center align-middle', width: '180px' },
                    { title: 'Descripción', data: 'descripcion', className: 'align-middle text-wrap' },
                    { 
                        title: '<i class="ti ti-message fs-7"></i>', 
                        data: null, 
                        className: 'text-center align-middle', 
                        orderable: false, 
                        searchable: false,
                        width: '48px',
                        render: (row) => {
                            const count = row.total_comentarios || 0;
                            const badge = count > 0 
                                ? `<span class="badge-historico position-absolute top-0 start-100 translate-middle">${count}</span>` 
                                : '';
                            return `
                                <a href="javascript:void(0)" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-comentario" data-id="${row.id}" data-descripcion="${row.descripcion}" title="Comentarios">
                                    <i class="ti ti-message fs-7"></i>${badge}
                                </a>
                            `;
                        }
                    },
                    { 
                        title: '<i class="ti ti-dots-vertical fs-5"></i>', 
                        data: null, 
                        className: 'text-center align-middle', 
                        orderable: false, 
                        searchable: false, 
                        width: '48px',
                        render: (row) => {
                            return `
                                <div class="dropdown dropstart">
                                    <a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item pointer btn-descargar" data-archivo="${row.archivo}"><i class="ti ti-file-text me-1"></i> Documento</a>
                                        <a class="dropdown-item pointer btn-editar" data-row='${JSON.stringify(row)}'><i class="ti ti-pencil me-1"></i> Editar</a>
                                        <a class="dropdown-item pointer btn-eliminar" data-id="${row.id}" data-descripcion="${row.descripcion}"><i class="ti ti-trash me-1"></i> Eliminar</a>
                                    </div>
                                </div>
                            `;
                        }
                    }
                ],
                initComplete: () => {
                    this.tablaDt.column(1).visible(this.tipoActual === 'Todos los documentos');
                }
            });

            tableEl.off('click', '.btn-descargar').on('click', '.btn-descargar', function (e) {
                e.preventDefault();
                const archivo = this.dataset.archivo;
                window.dispatchEvent(new CustomEvent('saveiro:descargar', { detail: { archivo } }));
            });

            tableEl.off('click', '.btn-editar').on('click', '.btn-editar', function (e) {
                e.preventDefault();
                const row = JSON.parse(this.dataset.row);
                window.dispatchEvent(new CustomEvent('saveiro:editar', { detail: row }));
            });

            tableEl.off('click', '.btn-eliminar').on('click', '.btn-eliminar', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                const descripcion = this.dataset.descripcion;
                window.dispatchEvent(new CustomEvent('saveiro:eliminar', { detail: { id, descripcion } }));
            });

            tableEl.off('click', '.btn-comentario').on('click', '.btn-comentario', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                const descripcion = this.dataset.descripcion;
                window.dispatchEvent(new CustomEvent('saveiro:comentarios', { detail: { id, descripcion } }));
            });
        },

        seleccionarArchivo(event) {
            this.form.archivo = event.target.files[0] || null;
            this.errors.archivo = false;
        },

        abrirModalCrear() {
            this.editando = false;
            this.form.id = 0;
            this.form.fecha = '';
            this.form.descripcion = '';
            this.form.archivo = null;
            this.errors = { fecha: false, descripcion: false, archivo: false };

            const input = document.getElementById('inputArchivo');
            if (input) input.value = '';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumento')).show();
        },

        abrirModalEditar(row) {
            this.editando = true;
            this.form.id = row.id;
            this.form.fecha = row.fecha_raw;
            this.form.descripcion = row.descripcion;
            this.form.archivo = null;
            this.errors = { fecha: false, descripcion: false, archivo: false };

            const input = document.getElementById('inputArchivo');
            if (input) input.value = '';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumento')).show();
        },

        async guardarDocumento() {
            this.errors = { fecha: false, descripcion: false, archivo: false };
            let hasError = false;

            if (!this.form.fecha) {
                this.errors.fecha = true;
                hasError = true;
            }
            if (!this.form.descripcion.trim()) {
                this.errors.descripcion = true;
                hasError = true;
            }
            if (!this.editando && !this.form.archivo) {
                this.errors.archivo = true;
                hasError = true;
            }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;
            const fd = new FormData();
            fd.append('tipo', this.tipoActual === 'Todos los documentos' ? 'Documentos Generales' : this.tipoActual);
            fd.append('fecha', this.form.fecha);
            fd.append('descripcion', this.form.descripcion);
            if (this.form.archivo) {
                fd.append('archivo', this.form.archivo);
            }

            const url = this.editando ? `${this.BASE_URL}/update` : `${this.BASE_URL}/store`;
            if (this.editando) {
                fd.append('id', this.form.id);
            }

            try {
                const resp = await axios.post(url, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDocumento')).hide();
                    this.tablaDt.ajax.reload(null, false);
                } else {
                    this.notify('error', resp.data?.message || (this.editando ? 'Error al editar la información' : 'Error al cargar el archivo'));
                }
            } catch (err) {
                this.notify('error', err.response?.data?.message || 'Error en el servidor al procesar la solicitud.');
            } finally {
                this.guardando = false;
            }
        },

        async eliminarRegistro(id, descripcion) {
            await this.deleteAction({
                url: `${this.BASE_URL}/delete`,
                id: id,
                name: descripcion || 'Documento',
                table: '#tabla-camioneta'
            });
        },

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        async abrirModalComentarios(id, descripcion) {
            this.documentoSeleccionadoId = id;
            this.documentoSeleccionadoDescripcion = descripcion || '';
            this.nuevoComentario = '';
            this.comentarios = [];
            this.errorComentario = false;

            const offcanvasEl = document.getElementById('modalComentarios');
            bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).show();

            await this.cargarComentarios();
        },

        async cargarComentarios() {
            this.cargandoComentarios = true;
            try {
                const resp = await axios.get(`${this.BASE_URL}/comentarios`, {
                    params: { id: this.documentoSeleccionadoId }
                });
                if (resp.data?.success) {
                    this.comentarios = resp.data.data || [];
                    this.scrollChatToBottom();
                }
            } catch (err) {
                console.error('Error cargando comentarios:', err);
            } finally {
                this.cargandoComentarios = false;
            }
        },

        async agregarComentario() {
            if (this.guardandoComentario) return;

            if (!this.nuevoComentario.trim()) {
                this.errorComentario = true;
                this.notify('error', 'Ingresa un comentario válido.');
                return;
            }

            this.guardandoComentario = true;
            try {
                const resp = await axios.post(`${this.BASE_URL}/comentarios/store`, {
                    id: this.documentoSeleccionadoId,
                    comentario: this.nuevoComentario.trim()
                });

                if (resp.data?.success) {
                    this.nuevoComentario = '';
                    this.notify('success', 'Comentario agregado exitosamente');
                    await this.cargarComentarios();
                    this.tablaDt.ajax.reload(null, false);
                } else {
                    this.notify('error', 'Error al guardar el comentario');
                }
            } catch (err) {
                this.notify('error', 'Error al guardar el comentario');
            } finally {
                this.guardandoComentario = false;
            }
        }
    }));
});