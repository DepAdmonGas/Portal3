document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-diseno-construccion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/mejores-practicas-estandares/datatable-diseno-construccion',
            type: 'GET',
            dataSrc: function (json) {
         
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

             { data: 'valor1',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },

             { data: 'valor2',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },

    {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center align-middle',
        render: function (data, type, row) {

            const noDelete = permisos.eliminar;
            const detalle = JSON.stringify(row.valor1 ?? '');
        
            return `<a href="javascript:void(0)" class="${!noDelete ? 'disabled' : ''}"
            ${!noDelete ? '' : `
            @click='window.mejoresPracticasEstandares.eliminarDC(${row.id}, ${detalle})'
            `}
            ><i class="ti ti-trash fs-6 text-danger"></i></a>`;
        }
    }
]
    });

    $("#table-diseno-construccion tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


