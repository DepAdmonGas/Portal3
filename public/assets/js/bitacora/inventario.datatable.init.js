document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    const table = $('#table-aditivo-inventario').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/bitacora-aditivo/datatable-inventario',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
            { data: 'id' },

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

           { data: 'aditivo' },

            { data: 'galones' },
            { data: 'detalle' }

        ]
    });

    // REINICIALIZAR ALPINE DESPUÉS DE DIBUJAR TABLA
    $('#table-aditivo-inventario').on('draw.dt', function () {
        Alpine.initTree(document.querySelector('#table-aditivo-inventario'));
    });

});