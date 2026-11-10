document.addEventListener('DOMContentLoaded', () => {
    const base = '/sgm/auditorias-internas-externas-atencion-hallazgos';
    const pdfLink = (url, available) => available
        ? `<a class="pointer" aria-label="Descargar PDF" title="Descargar PDF" href="${url}" download><i class="ti ti-file-type-pdf fs-6 text-danger"></i></a>`
        : '<i class="ti ti-x fs-6 text-muted" aria-label="Sin archivo"></i>';
    const editLink = (url) => `<a class="pointer" href="${url}" aria-label="Editar"><i class="ti ti-edit fs-6 text-warning"></i></a>`;

    $('#table-auditorias-sgm').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: `${base}/table`,
            type: 'GET',
            dataSrc: json => json?.success && Array.isArray(json.data) ? json.data : []
        },
        columns: [
            {
                data: 'numero',
                width: '96px',
                className: 'text-center align-middle'
            },
            {
                data: 'year',
                width: '90px',
                className: 'text-center align-middle'
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => editLink(`${base}/plan-auditoria/${Number(row.id)}`)
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => pdfLink(`${base}/plan-auditoria/pdf/${Number(row.id)}`, row.plan18?.pdf === true)
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => editLink(`${base}/reporte-hallazgos-auditoria/${Number(row.id)}`)
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => pdfLink(`${base}/reporte-hallazgos-auditoria/pdf/${Number(row.id)}`, row.hallazgo19?.pdf === true)
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => editLink(`${base}/plan-atencion-hallazgos/${Number(row.id)}`)
            },
            {
                data: null,
                className: 'text-center align-middle',
                orderable: false,
                searchable: false,
                render: (data, type, row) => pdfLink(`${base}/plan-atencion-hallazgos/pdf/${Number(row.id)}`, row.plan20?.pdf === true)
            }
        ],
        drawCallback: function () {
            const api = this.api();
            $('#table-auditorias-sgm tbody tr').removeClass('selected');
            $('#table-auditorias-sgm tbody').off('click', 'tr').on('click', 'tr', function () {
                if (!$(this).hasClass('selected')) {
                    api.$('tr.selected').removeClass('selected');
                    $(this).addClass('selected');
                }
            });
        }
    });
});
