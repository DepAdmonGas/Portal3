document.addEventListener('DOMContentLoaded', () => {

    table2 = $('#table-operacion-mantenimiento').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/mejores-practicas-estandares/datatable-operacion-mantenimiento',
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
        render: function (data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            }
        },

            {
            data: 'fecha',
            render: function (data, type) {

                if (!data) return '';

                const fecha = new Date(data);

                const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                // Para búsqueda y display usar el texto formateado
                if (type === 'display' || type === 'filter') {
                    return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${fechaFormateada}</div>`;
                }

                // Para ordenamiento usar la fecha real
                return data;
            },
            orderable: true,
            searchable: true
        },

             { data: 'norma',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },

             { data: 'nombre',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:100%; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },

             { data: 'link',
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
            const detalle = JSON.stringify(row.norma ?? '');
        
            return `<a href="javascript:void(0)" class="${!noDelete ? 'disabled' : ''}"
            ${!noDelete ? '' : `
            @click='window.mejoresPracticasEstandares.eliminarOM(${row.id}, ${detalle})'
            `}
            ><i class="ti ti-trash fs-6 text-danger"></i></a>`;
        }
    }
]
    });

    $("#table-operacion-mantenimiento tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


