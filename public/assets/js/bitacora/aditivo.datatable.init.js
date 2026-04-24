document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    const table = $('#table-aditivo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/bitacora-aditivo/datatable',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
            { data: 'folio',
                render: function(data, type, row) {
                // Convertir a string y rellenar con ceros a la izquierda
                return '00' + data;
            }
             },

            {
                data: 'fecha',
                render: function (data, type) {
                    if (!data) return '';

                    const fecha = new Date(data);
                    const formateada = fecha.toLocaleDateString('es-MX', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });

                    if (type === 'display') return formateada;
                    if (type === 'filter') return formateada + ' ' + data;

                    return data;
                }
            },

            {
                data: 'litros',
                render: function (data, type) {
                    if (!data) return '0';
                    if (type !== 'display') return data;

                    return Number(data).toLocaleString('es-MX');
                }
            },

            { data: 'no_factura' },
            { data: 'producto' },
            { data: 'galones' },
            { data: 'inventario_fisico' },

            {
                data: 'estado',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="badge text-bg-success">Activo</span>'
                        : '<span class="badge text-bg-danger">Eliminado</span>';
                }
            },

            // ACCIONES
            {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function (data, type, row) {

            const disabled = row.estado === 0;

            const noEdit = !permisos.editar || disabled;
            const noDelete = !permisos.eliminar || disabled;

            return `
                <div x-data="actions()" class="d-flex gap-1 justify-content-center">

                    <div class="dropdown dropstart">
                        <a href="javascript:void(0)" data-bs-toggle="dropdown">
                            <i class="ti ti-dots-vertical fs-6"></i>
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a 
                                    href="javascript:void(0)"
                                    class="dropdown-item ${noEdit ? 'disabled' : ''}"
                                    ${noEdit ? '' : `
                                    @click='$dispatch("open-edit", {
                                        id: ${row.id},
                                        litros: ${row.litros},
                                        producto: "${row.producto}",
                                        galones: ${row.galones},
                                        fecha: "${row.fecha}",
                                        no_factura: "${row.no_factura}"
                                    })'
                                    `}
                                >
                                    <i class="ti ti-edit"></i> Editar
                                </a>
                            </li>

                            <li>
                                <a 
                                    href="javascript:void(0)"
                                    class="dropdown-item ${noDelete ? 'disabled' : ''}"
                                    ${noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/bitacora-aditivo/delete",
                                        id: ${row.id},
                                        name: "${row.folio}",
                                        table: "#table-aditivo"
                                    });

                                    if (res && res.success) {
                                        window.aditivoInstance.updateInventario();
                                    }
                                }'
                                    `}
                                >
                                    <i class="ti ti-trash"></i> Eliminar
                                </a>
                            </li>

                        </ul>
                    </div>

                </div>
            `;
        }
        }
        ]
    });

    // REINICIALIZAR ALPINE DESPUÉS DE DIBUJAR TABLA
    $('#table-aditivo').on('draw.dt', function () {
        Alpine.initTree(document.querySelector('#table-aditivo'));
    });

});