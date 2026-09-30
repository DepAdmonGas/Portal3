document.addEventListener('alpine:init', () => {
    Alpine.data('proveedoresComponent', () => ({
        BASE_URL: '/departamento-operativo/almacen/proveedores',
        tablaDt: null,

        detalle: null,
        cargandoDetalle: false,
        idProveedorSeleccionado: 0,
        proveedorSeleccionadoNombre: '',
        listaDocsProveedor: [],

        guardandoArchivo: false,
        formArchivo: {
            tipo: '',
            fecha: new Date().toISOString().slice(0, 10),
            archivo: null
        },
        errorsArchivo: {
            tipo: false,
            fecha: false,
            archivo: false
        },

        init() {
            window.proveedoresInstance = this;

            this.$nextTick(() => {
                this.initDataTable();
            });

            window.addEventListener('prov:ver-detalle', (e) => this.abrirDetalle(e.detail.id));
            window.addEventListener('prov:ver-documentos', (e) => this.abrirModalArchivos(e.detail.id, e.detail.name));
            window.addEventListener('prov:eliminar', (e) => this.eliminarProveedor(e.detail.id, e.detail.name));
            window.addEventListener('prov:descargar', (e) => this.download('proveedores', e.detail.archivo));
        },

        initDataTable() {
            if (typeof window.jQuery === 'undefined') return;

            const $ = window.jQuery;
            const tableEl = $('#tabla-proveedores');
            if (!tableEl.length) return;

            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().destroy();
            }

            this.tablaDt = tableEl.DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: `${this.BASE_URL}/data`,
                    type: 'POST',
                    dataSrc: (json) => json?.data || []
                },
                order: [[1, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json'
                },
                columns: [
                    {
                        title: 'No.',
                        data: null,
                        className: 'text-center align-middle',
                        width: '48px',
                        render: (data, type, row, meta) => meta.row + 1
                    },
                    { title: 'Folio', data: 'folio', className: 'text-center align-middle fw-semibold' },
                    { title: 'Fecha', data: 'fecha', className: 'text-center align-middle' },
                    { title: 'Nombre comercial de la empresa (Proveedor)', data: 'razon_social', className: 'align-middle fw-semibold' },
                    { title: 'Actividad económica', data: 'actividad_economica', className: 'align-middle' },
                    {
                        title: '<i class="ti ti-file-text fs-7 text-primary"></i>',
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        width: '60px',
                        render: (row) => {
                            const count = row.alertas_count || 0;
                            const badge = count > 0
                                ? `<span class="badge-historico position-absolute top-0 start-100 translate-middle">${count}</span>`
                                : '';
                            return `
                                <a href="javascript:void(0)" class="btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-ver-docs" data-id="${row.id}" data-name="${encodeURIComponent(row.razon_social || '')}" title="Documentos">
                                    <i class="ti ti-file-text fs-7 text-primary"></i>${badge}
                                </a>
                            `;
                        }
                    },
                    {
                        title: '<i class="ti ti-dots-vertical fs-6"></i>',
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        width: '48px',
                        render: (row) => {
                            return `
                                <div class="dropdown dropstart">
                                    <a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item pointer btn-ver-detalle" data-id="${row.id}"><i class="ti ti-eye me-1"></i> Detalle</a>
                                        <a class="dropdown-item pointer" href="${this.BASE_URL}-editar/${row.id}"><i class="ti ti-pencil me-1"></i> Editar</a>
                                        <a class="dropdown-item pointer text-danger btn-eliminar-prov" data-id="${row.id}" data-name="${row.razon_social}"><i class="ti ti-trash me-1"></i> Eliminar</a>
                                    </div>
                                </div>
                            `;
                        }
                    }
                ]
            });

            tableEl.off('click', '.btn-ver-docs').on('click', '.btn-ver-docs', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                const name = decodeURIComponent(this.dataset.name || '');
                window.dispatchEvent(new CustomEvent('prov:ver-documentos', { detail: { id, name } }));
            });

            tableEl.off('click', '.btn-ver-detalle').on('click', '.btn-ver-detalle', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                window.dispatchEvent(new CustomEvent('prov:ver-detalle', { detail: { id } }));
            });

            tableEl.off('click', '.btn-eliminar-prov').on('click', '.btn-eliminar-prov', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                const name = this.dataset.name;
                window.dispatchEvent(new CustomEvent('prov:eliminar', { detail: { id, name } }));
            });
        },

        async abrirDetalle(id) {
            this.cargandoDetalle = true;
            this.detalle = null;

            const modalEl = document.getElementById('modalDetalle');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            try {
                const resp = await axios.post(`${this.BASE_URL}/detalle`, { idProveedor: id });
                if (resp.data?.success) {
                    this.detalle = resp.data.data;
                } else {
                    this.notify('error', resp.data?.message || 'No se pudo obtener el detalle.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error al consultar el detalle del proveedor.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        async abrirModalArchivos(id, name = '') {
            this.idProveedorSeleccionado = id;
            this.proveedorSeleccionadoNombre = name;

            this.formArchivo = {
                tipo: '',
                fecha: new Date().toISOString().slice(0, 10),
                archivo: null
            };
            this.errorsArchivo = { tipo: false, fecha: false, archivo: false };

            const input = document.getElementById('inputDocArchivo');
            if (input) input.value = '';

            const modalEl = document.getElementById('modalArchivos');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            try {
                const resp = await axios.post(`${this.BASE_URL}/detalle`, { idProveedor: id });
                if (resp.data?.success) {
                    this.listaDocsProveedor = resp.data.data.documentos || [];
                    if (!this.proveedorSeleccionadoNombre && resp.data.data.razon_social) {
                        this.proveedorSeleccionadoNombre = resp.data.data.razon_social;
                    }
                } else {
                    this.notify('error', 'Error al cargar los documentos.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error al cargar los documentos.');
            }
        },

        async actualizarArchivo() {
            this.errorsArchivo = { tipo: false, fecha: false, archivo: false };
            let hasError = false;

            if (!this.formArchivo.tipo) {
                this.errorsArchivo.tipo = true;
                hasError = true;
            }
            if (!this.formArchivo.fecha) {
                this.errorsArchivo.fecha = true;
                hasError = true;
            }
            if (!this.formArchivo.archivo) {
                this.errorsArchivo.archivo = true;
                hasError = true;
            }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoArchivo = true;
            const fd = new FormData();
            fd.append('idProveedor', this.idProveedorSeleccionado);
            fd.append('TipoArchivo', this.formArchivo.tipo);
            fd.append('FechaDocumentacion', this.formArchivo.fecha);
            fd.append('Archivo_file', this.formArchivo.archivo);

            try {
                const resp = await axios.post(`${this.BASE_URL}/actualizar-archivo`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);

                    const detResp = await axios.post(`${this.BASE_URL}/detalle`, { idProveedor: this.idProveedorSeleccionado });
                    if (detResp.data?.success) {
                        this.listaDocsProveedor = detResp.data.data.documentos || [];
                    }

                    this.formArchivo.tipo = '';
                    this.formArchivo.archivo = null;
                    const input = document.getElementById('inputDocArchivo');
                    if (input) input.value = '';

                    this.tablaDt.ajax.reload(null, false);
                } else {
                    this.notify('error', resp.data?.message || 'Error al actualizar el documento.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error en el servidor al intentar actualizar el documento.');
            } finally {
                this.guardandoArchivo = false;
            }
        },

        async eliminarProveedor(id, name) {
            await this.deleteAction({
                url: `${this.BASE_URL}/eliminar`,
                id: id,
                name: name || 'Proveedor',
                table: '#tabla-proveedores'
            });
        }
    }));
});