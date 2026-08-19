document.addEventListener('DOMContentLoaded', () => {

    const idSasisopa = document
    .getElementById('container')
    .dataset.idsasisopa;

    table2 = $('#table-trabajador-autorizado').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/control-actividades-procesos/datatable-configuracion-bitacora',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

        {
            data: 'id_usuario',
            className: 'text-center align-middle fw-bold'
        },
        {
            data: 'nombre'
        },
        {
            data: 'puesto',
            className: 'text-center align-middle'
        },

         {
            data: 'categoria_badge'
        },
                
           {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {

                    const noDelete = permisos.eliminar;     
                    const nombre = JSON.stringify(row.nombre ?? ''); 
                    const categoria = JSON.stringify(row.categoria ?? '');               

                    return `
                        <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                        href="javascript:void(0)"
                            ${!noDelete ? '' : `
                            @click='window.configuracionBitacora.modalEliminarOpen(${row.id_firma},${nombre},${categoria})'
                            `}>
                            <i class="fs-4 ti ti-trash fs-6 text-danger"></i>
                            </a>
                    `;
                }
            }
        ]
    });

     $("#table-trabajador-autorizado tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
