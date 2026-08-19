document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-lista-requisitos-legales-configuracion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/requisitos-legales/datatable-configuracion',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
            { data: 'nivel_gobierno' },
            { data: 'dependencia' },
            {
            data: 'permiso',
            render: function (data) {
                return `<div style="max-width:400px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
            },
            {
            data: 'fundamento',
            render: function (data) {
                return `<div style="max-width:400px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
            },
            
             
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {

                    const noDelete = permisos.eliminar;                    

                    return `
                    <a class="${!noDelete ? 'disabled text-muted' : ''}"
                    ${!noDelete ? '' : `
                    @click='async () => {
                    const res = await deleteAction({
                    url: "/sasisopa/requisitos-legales/delete-configuracion",
                    id: ${row.id},
                    name: "${row.dependencia}",
                    table: "#table-lista-requisitos-legales-configuracion"
                    });
                    }'
                    `}>
                    <i class="fs-6 ti ti-trash pointer"></i>
                </a>
                    `;
                }
            }
        ]
    });

     $("#table-lista-requisitos-legales-configuracion tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });


});


