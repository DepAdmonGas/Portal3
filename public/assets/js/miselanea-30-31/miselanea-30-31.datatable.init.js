// ============================================================
// Miscelánea 30, 31 — Inicialización de la tabla de documentos de
// una etapa (documental o certificación). Consume el endpoint
// /miselanea-30-31/data y muestra únicamente la etapa de la página.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const contenedor = document.getElementById('miselanea-30-31');
    if (!contenedor) return;

    const tieneEstacion = contenedor.dataset.idEstacion !== '';
    if (!tieneEstacion) return;

    const year = contenedor.dataset.year || '';
    const etapa = contenedor.dataset.etapa || 'documental';

    const tabla = $('#tabla-miselanea-documentos');
    if (!tabla.length) return;

    tabla.DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/miselanea-30-31/data',
            type: 'GET',
            data: function (d) {
                d.year = year;
            },
            dataSrc: function (json) {
                return (json && json.data && json.data[etapa]) ? json.data[etapa] : [];
            }
        },
        columns: [
            {
                title: '#',
                data: 'id_lista',
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                orderable: true,
                searchable: true
            },
            {
                title: 'Documento',
                data: 'documento',
                className: 'align-middle text-start',
                orderable: true,
                searchable: true
            },
            {
                title: '<i class="ti ti-paperclip text-primary fs-7"></i>',
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                searchable: false,
                width: '48px',
                render: function (v, t, row) {
                    const badge = Number(row.total) > 0
                        ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.total + '</span>'
                        : '';
                    return '<a class="pointer btn-mis-documento btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center"'
                         + ' data-id="' + row.id + '"'
                         + ' data-lista="' + (row.id_lista || '') + '"'
                         + ' data-documento="' + (row.documento || '').replace(/"/g, '&quot;') + '">'
                         + '<i class="ti ti-paperclip fs-7 text-primary"></i>' + badge + '</a>';
                }
            }
        ]
    });

    // Abre el modal de gestión de archivos del documento.
    tabla.off('click', '.btn-mis-documento').on('click', '.btn-mis-documento', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        const lista = $(this).data('lista');
        const documento = $(this).data('documento');
        document.dispatchEvent(new CustomEvent('miselanea:documento', {
            detail: { id, lista, documento }
        }));
    });

    // Recarga la tabla tras un alta o baja de archivos.
    window.__miselaneaRecargarTablas = function () {
        if (tabla.length && $.fn.DataTable.isDataTable('#tabla-miselanea-documentos')) {
            tabla.DataTable().ajax.reload(null, false);
        }
    };
});
