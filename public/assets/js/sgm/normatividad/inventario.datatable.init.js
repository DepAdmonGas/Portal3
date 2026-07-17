document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    table1 = $('#table-inventario-normatividad').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sgm/normatividad-aplicable-mediciones/datatable-inventario',
            type: 'GET',
            dataSrc: function (json) {

                //guardas permisos globalmente
                permisos = json.permisos;
                return json.data;
            }
        },
        columns: [
            { data: 'norma',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
            },
            {
            data: 'fecha_publicacion',
            render: function (data, type) {

                if (!data || data === 'S/I') return 'S/I';

                const partes = data.split('-');

                if (partes.length !== 3) return 'S/I';

                const fecha = new Date(partes[0], partes[1] - 1, partes[2]);

                const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                if (type === 'display' || type === 'filter') {
                    return fechaFormateada;
                }

                return data;
            },
            orderable: true,
            searchable: true
        },

        {
            data: 'fecha_aplicacion',
            render: function (data, type) {

                if (!data || data === 'S/I') return 'S/I';

                const partes = data.split('-');

                if (partes.length !== 3) return 'S/I';

                const fecha = new Date(partes[0], partes[1] - 1, partes[2]);

                const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                if (type === 'display' || type === 'filter') {
                    return fechaFormateada;
                }

                return data;
            },
            orderable: true,
            searchable: true
        },
           { data: 'equipo',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
            },

            { data: 'link',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
            },
             
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                                      
                    const noEdit = permisos.editar;
                    const noDelete = permisos.eliminar;
                    const noDownload = permisos.descargar;

                    return `
                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/sgm/normatividad-aplicable-mediciones/delete-inventario",
                                        id: ${row.id},
                                        name: "${row.id}",
                                        table: "#table-inventario-normatividad"
                                    });
                                    }'
                                    `}>
                                        <i class="ti ti-trash fs-7 text-danger"></i>
                                    </a>
                    `;
                }
            }
        ]
    });

    $("#table-inventario-normatividad tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
