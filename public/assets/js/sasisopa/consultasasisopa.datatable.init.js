document.addEventListener('DOMContentLoaded', () => {
    const selector = '#table-consulta-sasisopa';
    if (!document.querySelector(selector) || !window.jQuery?.fn?.DataTable) return;

    const textoSeguro = $.fn.dataTable.render.text();
    const escapar = value => textoSeguro.display(value ?? '');

    window.tableConsultaSasisopa = $(selector).DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json',
            emptyTable: 'No se encontró información para mostrar'
        },
        ajax: {
            url: '/sasisopa/consulta/datatable',
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
                data: 'permisocre',
                className: 'text-center align-middle',
                render: textoSeguro
            },
            {
                data: 'razonsocial',
                className: 'text-center align-middle',
                render: textoSeguro
            },
            {
                data: 'version',
                className: 'text-center align-middle',
                render: textoSeguro
            },
            {
                data: 'documento',
                className: 'text-center align-middle',
                width: '60px',
                orderable: false,
                searchable: false,
                render: (documento, tipo) => {
                    if (tipo !== 'display') return '';
                    if (typeof documento !== 'string' || documento === '') {
                        return '<i class="ti ti-file-type-pdf fs-7 text-danger" aria-label="Documento no disponible"></i>';
                    }

                    const nombreCrudo = documento.split('/').pop() || 'documento.pdf';
                    let nombreArchivo = nombreCrudo;
                    try {
                        nombreArchivo = decodeURIComponent(nombreCrudo);
                    } catch (_) {}

                    return `<a class="pointer d-inline-flex align-items-center justify-content-center" href="${escapar(documento)}" download="${escapar(nombreArchivo)}" aria-label="Descargar documento">
                        <i class="ti ti-file-type-pdf fs-7 text-danger"></i>
                    </a>`;
                }
            }
        ]
    });

});
