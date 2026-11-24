document.addEventListener('DOMContentLoaded', () => {
    const selector = '#tabla-comunicados';
    const element = document.querySelector(selector);

    if (!element || !window.jQuery?.fn?.DataTable) return;

    const textoSeguro = $.fn.dataTable.render.text();
    const escapar = value => textoSeguro.display(value ?? '');

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
            url: '/sasisopa/comunicados/datatable',
            type: 'GET',
            dataSrc: json => json?.success && Array.isArray(json.data) ? json.data : []
        },
        columns: [
            {
                data: 'id',
                className: 'text-center align-middle fw-bolder',
                width: '60px',
                render: textoSeguro
            },
            {
                data: 'fecha_larga',
                className: 'text-center align-middle',
                render: (fecha, tipo, row) => tipo === 'display' || tipo === 'filter'
                    ? escapar(fecha)
                    : (row.fecha || '')
            },
            {
                data: 'tema',
                className: 'text-center align-middle',
                render: textoSeguro
            },
            {
                data: 'detalle_corto',
                className: 'text-center align-middle',
                render: textoSeguro
            },
            {
                data: 'dirigidoa',
                className: 'text-center align-middle',
                orderable: false,
                render: puestos => Array.isArray(puestos)
                    ? puestos.map(puesto => `<span class="badge rounded-pill text-bg-info me-2 mb-1">${escapar(puesto.puesto)}</span>`).join(' ')
                    : ''
            },
            {
                data: null,
                className: 'text-center align-middle',
                width: '45px',
                orderable: false,
                searchable: false,
                render: (_data, _type, row) => {
                    const id = Number(row.id);
                    const archivo = typeof row.archivo === 'string' ? row.archivo : '';
                    let descarga = '';

                    if (archivo) {
                        const nombreCrudo = archivo.split('/').pop() || 'archivo';
                        let nombreArchivo = nombreCrudo;
                        try {
                            nombreArchivo = decodeURIComponent(nombreCrudo);
                        } catch (_) {}
                        descarga = `
                            <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    href="${escapar(archivo)}"
                                    download="${escapar(nombreArchivo)}"
                                    data-download-loader
                                    data-download-button-loader>
                                    <i class="ti ti-download fs-4"></i>Descargar
                                </a>
                            </li>`;
                    }

                    return `
                        <div class="dropdown dropstart">
                            <a class="pointer" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones del comunicado">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3" data-comunicado-accion="detalle" data-id="${id}">
                                        <i class="fs-4 ti ti-eye"></i>Detalle
                                    </a>
                                </li>
                                ${descarga}
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3" data-comunicado-accion="eliminar" data-id="${id}">
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>`;
                }
            }
        ]
    });

    window.tableComunicados = table;

    $(selector).on('click.comunicados', '[data-comunicado-accion]', event => {
        event.preventDefault();
        const row = table.row($(event.currentTarget).closest('tr')).data();
        const component = window.comunicadosInstance;
        if (!row || !component) return;

        if (event.currentTarget.dataset.comunicadoAccion === 'detalle') {
            component.verDetalle(row);
        } else if (event.currentTarget.dataset.comunicadoAccion === 'eliminar') {
            component.eliminar(row);
        }
    });
});
