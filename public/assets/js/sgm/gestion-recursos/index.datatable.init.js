let tablaResponsablesSgm;

document.addEventListener('DOMContentLoaded', () => {
    const selector = '#table-responsables-sgm';
    if (!document.querySelector(selector)) return;

    tablaResponsablesSgm = $(selector).DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sgm/gestion-recursos/responsable/table',
            type: 'GET',
            dataSrc: json => Array.isArray(json?.data) ? json.data : []
        },
        columns: [
            {
                data: null,
                className: 'text-center align-middle',
                searchable: false,
                render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
            },
            {
                data: 'fecha',
                className: 'text-center align-middle'
            },
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: (data, type, row) => `
                    <div class="dropdown dropstart">
                        <a class="pointer" data-bs-toggle="dropdown" aria-label="Acciones">
                            <i class="ti ti-dots-vertical fs-6"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-3"
                                   aria-label="Descargar PDF"
                                   href="/sgm/gestion-recursos/responsable/pdf/${Number(row.id)}" download>
                                    <i class="fs-4 ti ti-download"></i>Descargar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-3 pointer"
                                   @click="eliminar(${Number(row.id)})">
                                    <i class="fs-4 ti ti-trash"></i>Eliminar
                                </a>
                            </li>
                        </ul>
                    </div>`
            }
        ]
    });
});
