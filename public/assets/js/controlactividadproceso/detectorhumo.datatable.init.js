document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-detector-humo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/control-actividades-procesos/detector-humo/datatable',
            type: 'GET',
            dataSrc: function (json) {
         
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

             { data: 'no_detector',
                defaultContent: 'S/I'
             },

             { data: 'ubicacion',
                defaultContent: 'S/I'
             },

    {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center align-middle',
        render: function (data, type, row) {

            const noDelete = permisos.eliminar;
        
            return `<a href="javascript:void(0)" class="${!noDelete ? 'disabled text-muted' : ''}"
            ${!noDelete ? '' : `
            @click='window.detectorHumo.eliminar(${row.id}, ${row.no_detector})'
            `}
            ><i class="ti ti-trash fs-6 text-danger"></i></a>`;
        }
    }
]
    });

    $("#table-detector-humo tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


