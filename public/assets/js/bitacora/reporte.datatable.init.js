document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    const table = $('#table-aditivo-reporte').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/bitacora-aditivo/datatable-reporte',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
           {
                data: null,
                width: '60px',
                className: 'text-center',
                render: function (data, type, row, meta) {
                    return meta.row + 1;
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
            data: 'hora',
            render: function (data, type) {

                if (!data) return '';

                const fecha = new Date(`1970-01-01T${data}`);

                if (type === 'display') {
                    return fecha.toLocaleTimeString('es-MX', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                }

                return data; // para ordenamiento
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

            const noDesc = !permisos.descargar || disabled;
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
                                class="dropdown-item ${noDesc ? 'disabled' : ''}"
                                ${noDesc ? '' : `
                                @click="download('bitacora-aditivo','${row.documento}')"
                                `}
                            >
                                <i class="ti ti-file-download"></i> Descargar
                            </a>
                        </li>

                            <li>
                                <a 
                                    href="javascript:void(0)"
                                    class="dropdown-item ${noDelete ? 'disabled' : ''}"
                                    ${noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/bitacora-aditivo/delete-reporte",
                                        id: ${row.id},
                                        name: "${row.id}",
                                        table: "#table-aditivo-reporte"
                                    });

                                }'
                                    `}
                                >
                                    <i class="ti ti-trash fs-6"></i> Eliminar
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
    $('#table-aditivo-reporte').on('draw.dt', function () {
        Alpine.initTree(document.querySelector('#table-aditivo-reporte'));
    });

});