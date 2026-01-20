document.addEventListener('DOMContentLoaded', () => {

    $('#table-estaciones').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/estaciones/datatable',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            { data: 'id', width: '50px', className: 'text-center' },
            { data: 'nombre' },
            { data: 'permisocre' },
            { data: 'razonsocial' },
            { data: 'rfc' },
            {
                data: 'estatus',
                width: '80px',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="mb-1 badge text-bg-success">Activo</span>'
                        : '<span class="mb-1 badge text-bg-danger">Cancelado</span>';
                }
            },
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                             <li>
                                    <a href="/usuarios?idEstacion=${row.id}" class="dropdown-item d-flex align-items-center gap-3 btn-edit" data-id="${row.id}">
                                        <i class="fs-4 ti ti-users"></i>Usuarios
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-edit" data-id="${row.id}">
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-delete" data-id="${row.id}">
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    `;
                }
            }
        ]
    });

});
