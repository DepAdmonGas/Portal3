document.addEventListener('DOMContentLoaded', () => {

    $('#table-lista-registro-comunicacion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/datatable-lista-registro-comunicacion',
            type: 'GET',
            dataSrc: function (json) {
                return json.data;
            },
            error: function (xhr) {
                
            }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
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

                    if (type === 'display' || type === 'filter') {
                        return fechaFormateada;
                    }

                    return data;
                },
                orderable: true,
                searchable: true
            },
            { data: 'tema' },
            { data: 'encargado_comunicacion' },
            { data: 'tipo_comunicacion' },
            { data: 'material' },
            { data: 'seguimiento' },

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
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-edit 
                                    data-id="${row.id}">
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-delete data-id="${row.id}">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 btn-delete data-id="${row.id}">
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
