document.addEventListener('DOMContentLoaded', () => {

    const id = document
    .getElementById('container')
    .dataset.id;

    let permisos = {};
    let url = '';

    table2 = $('#table-lista-asistencia-firma').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/table-lista-asistencia-firma/id/' + id,
            type: 'GET',
            dataSrc: function (json) {
                //guardas permisos globalmente
                permisos = json.permisos;
                url = json.urlFirma;
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
            { data: 'usuario', className: 'text-center' },
            { data: 'puesto', className: 'text-center' },
            {
            data: 'firma',
            className: 'text-center',
            render: function (data) {

                if (!data) return '';

                return `<img src="${url}${data}" style="width:60px;">`;
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
                <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                    ${!noDelete ? '' : `
                    @click="eliminarPersonal(${row.id}, '${row.usuario}')"
                    `}>
                    <i class="fs-6 ti ti-trash"></i>
                </a>
            `;
                }
            }
        ]
    });

    $("#table-lista-asistencia-firma").on("click", "tr", function () {
        if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
