document.addEventListener('DOMContentLoaded', () => {

    $('#table-grupos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/grupos/datatable',
            type: 'GET',          // ✅ GET
            dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
            { data: 'nombre' },
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
                    const disabled = row.estatus === 0
                        ? 'disabled opacity-50 pointer-events-none'
                        : '';

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                            <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-edit" data-id="${row.id}">
                                        <i class="fs-4 ti ti-gas-station"></i>Estaciones
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-edit ${disabled}" 
                                    data-id="${row.id}" 
                                    data-nombre="${row.nombre}">
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-delete ${disabled}" data-id="${row.id}">
                                        <i class="fs-4 ti ti-trash"></i>Cancelar
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
