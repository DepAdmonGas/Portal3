$(document).ready(function () {

    var permisos = {};

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function getFiltros() {
        var c = document.getElementById('container');
        return {
            fecha_inicio: c ? (c.dataset.fechaInicio || '') : '',
            fecha_fin: c ? (c.dataset.fechaFin || '') : ''
        };
    }

    function renderComentarios(row) {
        var conteo = parseInt(row.total_comentarios) || 0;
        var badge = conteo > 0
            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + conteo + '</span>'
            : '';
        return '<a href="javascript:void(0)" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-action="comentarios" data-id="' + row.id + '" title="Comentarios">' +
            '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
    }

    function renderAcciones(row) {
        return renderComentarios(row);
    }

    var table = $('#tabla-lista-negra').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/departamento-operativo/recursos-humanos/lista-negra/get-data',
            type: 'GET',
            data: getFiltros,
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data;
            }
        },
        columns: [
            {
                data: 'id',
                title: '#',
                width: '64px',
                className: 'text-center align-middle fw-bold',
                render: function (data) {
                    return String(data).padStart(3, '0');
                }
            },
            { data: 'nombre_completo', title: 'Nombre completo', className: 'align-middle' },
            { data: 'puesto', title: 'Puesto', className: 'align-middle', render: function (data) { return escHtml(data); } },
            {
                data: 'fecha',
                title: 'Fecha de baja',
                className: 'text-center align-middle',
                render: function (data) { return escHtml(data); }
            },
            { data: 'estacion', title: 'Estación', className: 'text-center align-middle', render: function (data) { return escHtml(data); } },
            { data: 'motivo', title: 'Motivo', className: 'text-center align-middle', render: function (data) { return escHtml(data); } },
            {
                data: 'detalle',
                title: 'Descripción',
                className: 'align-middle',
                render: function (data) {
                    return '<div style="white-space: normal; min-width: 180px; max-width: 320px;">' + escHtml(data) + '</div>';
                }
            },
            {
                data: 'total_comentarios',
                title: '<i class="ti ti-message fs-7"></i>',
                width: '48px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return renderAcciones(row);
                }
            }
        ],
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.querySelector('#tabla-lista-negra'));
            }
        }
    });

    window.tablaListaNegra = table;

    $(document).on('click', '[data-action="comentarios"], .btn-comentarios', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        document.dispatchEvent(new CustomEvent('ver-comentarios-lista', { detail: { id: id } }));
    });

    $(document).on('click', '[data-action="pruebas"]', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        document.dispatchEvent(new CustomEvent('ver-pruebas-lista', { detail: { id: id } }));
    });

    $(document).on('click', '[data-action="eliminar"]', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        document.dispatchEvent(new CustomEvent('eliminar-lista-negra', { detail: { id: id, name: name } }));
    });
});