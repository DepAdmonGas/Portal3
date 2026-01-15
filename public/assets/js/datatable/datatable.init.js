document.addEventListener('DOMContentLoaded', () => {

    $('#table-grupos').DataTable({
        processing: true,
        serverSide: false, // sigue siendo false si no hay server-side
        autoWidth: false,  // evita que DataTables recalculen los anchos
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/grupos/datatable',
            type: 'POST',
            contentType: 'application/json',
            data: function(d) {
                // d es el objeto que DataTables envía
                return JSON.stringify(d); 
            },
            dataSrc: 'data' // indica dónde está el array de filas en la respuesta JSON
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
            { data: 'nombre' },
            {
                data: 'estatus',
                width: '120px',
                className: 'text-center',
                render: function(data) {
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
                render: function(data, type, row) {
                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown">
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
