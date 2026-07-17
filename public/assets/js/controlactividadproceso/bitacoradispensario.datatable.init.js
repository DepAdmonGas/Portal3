document.addEventListener('DOMContentLoaded', () => {

    const year = new Date().getFullYear();

    table1 = $('#table-bitacora-dispensario').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/control-actividades-procesos/bitacora-dispensario/datatable?year=' + year,
            type: 'GET',
            dataSrc: function (json) {
         
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

        {
            data: 'fecha',
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

        { data: 'hora_inicio',
          defaultContent: 'S/I'
        },

        { data: 'hora_termino',
          defaultContent: 'S/I'
        },

        { data: 'no_dispensario', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

        { data: 'marca', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },


        { data: 'modelo', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

        { data: 'serie', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

         { data: 'lado', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

        { data: 'producto',
          defaultContent: 'S/I'
        },

        { data: 'clave_motivo',
          defaultContent: 'S/I'
        },


        { data: 'responsable', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

        { data: 'detalle', className: 'text-center align-middle', defaultContent: 'S/I',
            render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
        },

          {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data, type, row) {

                const noDelete = permisos.eliminar;

                return `
                    <div class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>

                            <ul class="dropdown-menu">

                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3"
                                    href="javascript:void(0)"
                                    @click='window.bitacoraDispensario.detalle(${JSON.stringify(row)})'>
                                        <i class="ti ti-eye"></i>Detalle
                                    </a>
                                </li>


                                <li>
                                    <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='window.bitacoraDispensario.eliminar(${row.id}, ${row.no_dispensario})'
                                    `}>
                                        <i class="ti ti-trash"></i>Eliminar
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

    $("#table-bitacora-dispensario tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


