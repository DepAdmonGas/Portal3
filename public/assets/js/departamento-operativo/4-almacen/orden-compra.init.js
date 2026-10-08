// Manejador global para la redirección desde el breadcrumb
window.CambioYearMes = function(year, mes) {
    window.location.href = `/departamento-operativo/almacen/orden-compra/${year}/${mes}`;
};

document.addEventListener('alpine:init', () => {
    Alpine.data('ordenCompraComponent', (idYear, idMes) => ({
        BASE_URL: '/departamento-operativo/almacen/orden-compra',
        year: idYear,
        mes: idMes,
        tablaDt: null,

        init() {
            this.$nextTick(() => {
                this.initDataTable();
            });

            window.addEventListener('oc:eliminar', (e) => this.eliminarRegistro(e.detail.id, e.detail.control));
        },

        initDataTable() {
            if (typeof window.jQuery === 'undefined') return;
            const $ = window.jQuery;
            const tableEl = $('#tabla-orden-compra');
            if (!tableEl.length) return;

            if ($.fn.DataTable.isDataTable(tableEl)) {
                tableEl.DataTable().destroy();
            }

            this.tablaDt = tableEl.DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: `${this.BASE_URL}/data/${this.year}/${this.mes}`,
                    type: 'POST',
                    dataSrc: (json) => json?.data || []
                },
                order: [[1, 'asc']],
                pageLength: 25,
                lengthMenu: [15, 25, 50, 100],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json'
                },
                createdRow: (row, data) => {
                    if (data.estatus === 0) {
                        $(row).css('background-color', '#fcfcda');
                    }
                },
      columns: [
    {
        title: 'No.',
        data: null,
        className: 'text-center align-middle',
        width: '48px',
        render: (data, type, row, meta) => meta.row + 1
    },
    { 
        title: 'No. De control', 
        data: 'no_control', 
        className: 'text-center align-middle fw-semibold' 
    },
    { 
        title: 'Responsable', 
        data: 'responsable', 
        className: 'align-middle' 
    },
    { 
        title: 'Fecha', 
        data: 'fecha', 
        className: 'text-center align-middle' 
    },
    { 
        title: 'Estatus', 
        data: 'estatus', 
        className: 'text-center align-middle',
        render: (estatus) => {
            const statusNum = parseInt(estatus, 10);
            if (statusNum === 0) {
                return '<span class="badge bg-danger">Pendiente</span>';
            } else if (statusNum >= 1) {
                return '<span class="badge bg-success">Finalizado</span>';
            }
            return '<span class="badge bg-secondary-subtle text-secondary">Desconocido</span>';
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
            const esElaborado = (row.estatus >= 1);
            const itemEditar = row.estatus === 0
                ? `<li><a class="dropdown-item pointer" href="/departamento-operativo/almacen/orden-compra-formulario/${row.id}"><i class="ti ti-pencil me-1"></i> Editar</a></li>`
                : `<li><span class="dropdown-item text-muted disabled"><i class="ti ti-pencil me-1"></i> Editar</span></li>`;

            const itemEliminar = row.estatus === 0
                ? `<li><a class="dropdown-item pointer text-danger btn-eliminar" data-id="${row.id}" data-control="${row.no_control}"><i class="ti ti-trash me-1"></i> Eliminar</a></li>`
                : `<li><span class="dropdown-item text-muted disabled"><i class="ti ti-trash me-1"></i> Eliminar</span></li>`;

            const itemDescargar = esElaborado
                ? `<li><a class="dropdown-item pointer" href="/departamento-operativo/almacen/orden-compra-descargar-pdf/${row.id}" target="_blank"><i class="ti ti-file-text me-1"></i> Descargar PDF</a></li>`
                : `<li><span class="dropdown-item text-muted disabled"><i class="ti ti-file-text me-1"></i> Descargar PDF</span></li>`;

            return `
                <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item pointer" href="/departamento-operativo/almacen/orden-compra-detalle/${row.id}"><i class="ti ti-eye me-1"></i> Detalle</a></li>
                        ${itemDescargar}
                        ${itemEditar}
                        ${itemEliminar}
                    </ul>
                </div>
            `;
        }
    }
]
            });

            tableEl.off('click', '.btn-eliminar').on('click', '.btn-eliminar', function (e) {
                e.preventDefault();
                const id = parseInt(this.dataset.id, 10);
                const control = this.dataset.control;
                window.dispatchEvent(new CustomEvent('oc:eliminar', { detail: { id, control } }));
            });
        },

        async nuevaOrden() {
            try {
                const resp = await axios.post(`${this.BASE_URL}/crear/${this.year}/${this.mes}`);
                if (resp.data?.success) {
                    this.notify('success', 'Orden de compra creada correctamente.');
                    window.location.href = `/departamento-operativo/almacen/orden-compra-formulario/${resp.data.id}`;
                } else {
                    this.notify('error', 'Error al crear la orden de compra.');
                }
            } catch (err) {
                this.notify('error', 'Error en el servidor al generar la orden.');
            }
        },

        async eliminarRegistro(id, control) {
            await this.deleteAction({
                url: `${this.BASE_URL}/eliminar`,
                id: id,
                name: `la Orden de Compra ${control}`,
                table: '#tabla-orden-compra'
            });
        }
    }));
});