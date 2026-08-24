document.addEventListener('DOMContentLoaded', () => {

  const layout = document.getElementById('container').dataset.layout;

    table1 = $('#table-personal').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/personal/datatable',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
        { data: 'id', className: 'text-center' },
        { data: 'nombre', className: 'text-center' },
        
        { data: 'puesto', className: 'text-center' },
        { data: 'telefono', className: 'text-center' },
        { data: 'email', className: 'text-center' },
        { data: 'usuario', className: 'text-center' },

        ...(layout === 'sgm'
        ? [{
            data: 'responsabilidad_sgm',
            className: 'text-center'
        }]
        : []),
        
           
        {
            data: 'estatus',
            className: 'text-center align-middle',
            render: function (data, type) {

                if (!data || !data.titulo) return '';

                return `
                    <span class="badge rounded-pill ${data.color_css}">
                        ${data.titulo}
                    </span>
                `;
            }
        },
        {
        data: null,
        width: '1%',
        orderable: false,
        searchable: false,
        className: 'text-center align-middle td-small',

        render: function (data, type, row) {

            const canEdit = permisos.editar;
            const canDelete = row.estatus.estatus;
            // const canDownload = permisos.descargar;

            return `
                <div class="dropdown dropstart">

                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical fs-6"></i>
                    </a>

                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal/${row.id}">
                                <i class="ti ti-eye fs-4"></i>
                                Ficha Personal
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!canEdit ? 'disabled text-muted' : ''}"
                            ${canEdit ? `onclick="personal.openEditar(${JSON.stringify(row).replace(/"/g, '&quot;')})"` : ''}>
                                <i class="ti ti-edit fs-4"></i>
                                Editar
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!canDelete ? 'disabled text-muted' : ''}"
                            ${canDelete ? `onclick="personal.delete(${row.id})"` : ''}>
                                <i class="ti ti-trash fs-4"></i>
                                Eliminar
                            </a>
                        </li>

                    </ul>

                </div>
            `;
        }
    }
        ]
    });

     $("#table-personal tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
