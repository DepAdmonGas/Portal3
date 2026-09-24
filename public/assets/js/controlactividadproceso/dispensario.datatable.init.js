document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-dispensario').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-dispensario/datatable',
            type: 'GET',
            dataSrc: function (json) {
         
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

             { data: 'no_dispensario', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'marca', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'modelo', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'serie', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'producto1', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'producto2', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

             { data: 'producto3', className: 'text-center align-middle',
                defaultContent: 'S/I'
             },

    {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center align-middle',
        render: function (data, type, row) {

            const noDelete = permisos.eliminar;
        
            return `<a class="pointer ${!noDelete ? 'disabled text-muted' : ''}"
            ${!noDelete ? '' : `
            @click='window.dispensario.eliminar(${row.id}, ${row.no_dispensario})'
            `}
            ><i class="ti ti-trash fs-6 text-danger"></i></a>`;
        }
    }
]
    });

    $("#table-dispensario tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


