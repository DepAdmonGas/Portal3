document.addEventListener('DOMContentLoaded', () => {
    const selector = '#table-atencion-hallazgos';

    if (!window.jQuery?.fn?.DataTable || !document.querySelector(selector)) {
        return;
    }

    const table = $(selector).DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: 'No se encontró información para mostrar'
        },
        ajax: {
            url: '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/datatable',
            type: 'GET',
            dataSrc: json => Array.isArray(json.data) ? json.data : []
        },
        columns: [
            { data: 'numero', width: '96px', className: 'text-center fw-bold' },
            {
                data: 'fecha_larga',
                className: 'text-center',
                render: (fecha, tipo, fila) => tipo === 'display' || tipo === 'filter'
                    ? $.fn.dataTable.render.text().display(fecha)
                    : (fila.fecha || fecha)
            },
            {
                data: 'no_control',
                className: 'text-center',
                render: $.fn.dataTable.render.text()
            },
            {
                data: 'tipo_auditoria',
                className: 'text-center',
                render: $.fn.dataTable.render.text()
            },
            {
                data: 'id',
                width: '96px',
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: id => {
                    const idSeguro = Number(id);
                    return `
                        <div class="dropdown dropstart">
                            <a class="pointer" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones del registro">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item pointer d-flex align-items-center gap-3" data-hallazgo-accion="editar" data-id="${idSeguro}"><i class="ti ti-edit"></i> Editar</a></li>
                                <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/pdf/${idSeguro}" data-download-loader><i class="ti ti-download"></i> Descargar</a></li>
                                <li><a class="dropdown-item pointer d-flex align-items-center gap-3" data-hallazgo-accion="eliminar" data-id="${idSeguro}"><i class="ti ti-trash"></i> Eliminar</a></li>
                            </ul>
                        </div>`;
                }
            }
        ]
    });

    window.tableAtencionHallazgos = table;

    $(selector).on('click.atencionHallazgos', '[data-hallazgo-accion]', event => {
        event.preventDefault();
        const id = Number(event.currentTarget.dataset.id);
        const accion = event.currentTarget.dataset.hallazgoAccion;
        const componente = window.atencionHallazgos;

        if (!componente) return;
        if (accion === 'editar') componente.editar(id);
        if (accion === 'eliminar') componente.eliminar(id);
    });
});
