document.addEventListener('DOMContentLoaded', function () {

    var permisos = {};

    function getContainer() {
        return document.getElementById('container');
    }

    function getModuleStationKey() {
        var c = getContainer();
        return c ? (c.dataset.moduleStationKey || 'cuenta-litros') : 'cuenta-litros';
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function isSeleccionEspecifica() {
        var sel = document.getElementById('module-station-selector-' + getModuleStationKey());
        if (sel) {
            return !!sel.value;
        }
        var c = getContainer();
        return (parseInt(c ? c.dataset.idEstacion : '0') || 0) > 0;
    }

    function renderEstatus(row) {
        return row.estatus === 1
            ? '<span class="badge bg-success">Finalizado</span>'
            : '<span class="badge bg-warning">En proceso</span>';
    }

    function renderAcciones(row) {
        var detalle = row.puedeDetalle
            ? '<a class="dropdown-item" href="/departamento-operativo/importacion/cuenta-litros-detalle/' + row.id + '"><i class="ti ti-eye fs-6"></i> Detalle</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-eye fs-6"></i> Detalle</a>';

        var editar = '';
        if (row.puedeEditar) {
            editar = '<a class="dropdown-item" href="/departamento-operativo/importacion/cuenta-litros-formato/' + row.id + '"><i class="ti ti-pencil fs-6"></i> Editar</a>';
        } else if (row.puedeHabilitar) {
            editar = '<a class="dropdown-item pointer" data-action="cuenta-litros-habilitar" data-id="' + row.id + '" data-name="' + row.id + '"><i class="ti ti-pencil fs-6"></i> Editar</a>';
        } else {
            editar = '<a class="dropdown-item disabled"><i class="ti ti-pencil fs-6"></i> Editar</a>';
        }

        var eliminar = row.puedeEliminar
            ? '<a class="dropdown-item pointer" data-action="cuenta-litros-eliminar" data-id="' + row.id + '" data-name="#' + row.id + '"><i class="ti ti-trash fs-6"></i> Eliminar</a>'
            : '';

        return '<div class="dropdown dropstart">' +
            '<a class="pointer" data-bs-toggle="dropdown">' +
            '<i class="ti ti-dots-vertical fs-6"></i>' +
            '</a>' +
            '<div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">' +
            detalle + editar + eliminar +
            '</div>' +
            '</div>';
    }

    var COLUMNA_ACCIONES = 4;

    var table = $('#tabla-cuenta-litros').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[2, 'asc']],
        pageLength: 15,
        lengthMenu: [15, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        createdRow: function (row, data) {
            if (data && data.rowClass) {
                $(row).css('background-color', data.rowClass);
            }
        },
        initComplete: function () {
            aplicarVisibilidad();
        },
        drawCallback: function () {
            aplicarVisibilidad();
        },
        ajax: {
            url: '/departamento-operativo/importacion/cuenta-litros/data',
            type: 'GET',
            data: function (d) {
                var c = getContainer();
                if (c) {
                    d.year = c.dataset.idYear;
                    d.mes = c.dataset.idMes;
                }
            },
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data || [];
            }
        },
        columns: [
            {
                data: 'id',
                title: '#',
                width: '70px',
                className: 'text-center align-middle fw-bold',
                render: function (data) {
                    return String(data).padStart(3, '0');
                }
            },
            { data: 'estacion', title: 'Estación', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'fecha', title: 'Fecha', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'estatus', title: 'Estatus', className: 'text-center align-middle', width: '100px', render: function (data, type, row) { return type === 'display' ? renderEstatus(row) : escHtml(row.estatus_texto); } },
            {
                data: 'id',
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                width: '48px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return type === 'display' ? renderAcciones(row) : '';
                }
            }
        ]
    });

    window.tablaCuentaLitros = table;

    function aplicarVisibilidad() {
        try {
            table.column(1).visible(!isSeleccionEspecifica());
            table.column(COLUMNA_ACCIONES).visible(true);
        } catch (e) {}
    }

    $(document).on('click', '[data-action="cuenta-litros-habilitar"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('cuenta-litros-habilitar', {
            detail: { id: $(this).data('id'), name: $(this).data('name') }
        }));
    });

    $(document).on('click', '[data-action="cuenta-litros-eliminar"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('cuenta-litros-eliminar', {
            detail: { id: $(this).data('id'), name: $(this).data('name') }
        }));
    });

    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(getModuleStationKey(), {
            customReload: function () {
                try {
                    aplicarVisibilidad();
                    window.tablaCuentaLitros.ajax.reload(null, false);
                    document.dispatchEvent(new CustomEvent('cuenta-litros-estacion-change'));
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }
});