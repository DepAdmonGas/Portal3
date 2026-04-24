document.addEventListener('DOMContentLoaded', () => {

    const params = new URLSearchParams(window.location.search);
    const idestacion = params.get('idEstacion');

    $('#table-usuarios').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/usuarios/datatable',
            type: 'GET',
            data: function (d) {
                d.idestacion = idestacion;
            },             
            dataSrc: function (json) {
                return json.data;  
            }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
            { data: 'nombre' },
            { data: 'email' },
            { data: 'telefono' },
            { data: 'puesto' },
            { data: 'razonsocial',
              render: function (data) {
                    return data && data.trim() !== '' ? data : 'Todas';
                }
             },
            {
                data: 'estatus',
                width: '80px',
                className: 'text-center',
                render: function (data) {
                    return data == 0
                        ? '<span class="mb-1 badge text-bg-success">Activo</span>'
                        : '<span class="mb-1 badge text-bg-danger">Eliminado</span>';
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
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
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
