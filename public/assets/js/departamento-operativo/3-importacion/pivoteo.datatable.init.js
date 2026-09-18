document.addEventListener('DOMContentLoaded', function () {

    function getContainer() {
        return document.getElementById('container');
    }

    function getModuleStationKey() {
        var c = getContainer();
        return c ? (c.dataset.moduleStationKey || 'pivoteo') : 'pivoteo';
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

    function actualizarBadgePendientes() {
        var wrapper = document.getElementById('pivoteo-pending-wrapper');
        var countEl = document.getElementById('pivoteo-pending-count');
        if (!wrapper || !countEl) return;

        var sel = document.getElementById('module-station-selector-' + getModuleStationKey());
        if (sel) {
            var opt = sel.options[sel.selectedIndex];
            var match = opt ? opt.textContent.match(/\((\d+)\)/) : null;
            if (match) {
                countEl.textContent = parseInt(match[1], 10);
                return;
            }
        }

        var dataEl = document.getElementById('pivoteo-pendientes-data');
        if (!dataEl) return;
        var raw = dataEl.textContent;
        if (!raw || raw === '{}') return;

        var pendientesMap;
        try { pendientesMap = JSON.parse(raw); } catch (e) { return; }

        var c = getContainer();
        var key = 'total';
        if (sel && sel.value) {
            key = sel.value;
        } else if (c && (parseInt(c.dataset.idEstacion, 10) || 0) > 0) {
            key = 'estacion_' + parseInt(c.dataset.idEstacion, 10);
        }

        var count = pendientesMap[key];
        countEl.textContent = (count === undefined) ? 0 : count;
    }

    function renderEstatus(row) {
        var mapa = {
            0: '<span class="badge bg-danger">Pendiente</span>',
            1: '<span class="badge bg-warning text-white">En proceso</span>',
            2: '<span class="badge bg-success">Finalizado</span>'
        };
        return mapa[row.estatus] || escHtml(row.estatus_texto);
    }

    function renderFecha(row) {
        return row.fecha ? escHtml(row.fecha) : 'Sin información';
    }

    function renderFirma(row) {
        var est = row.estatus;

        if (est === 2) {
            return '<i class="ti ti-signature text-success fs-8" title="Firmado"></i>';
        }

        if (est === 1 && row.puedeFirmar) {
            return '<a href="/departamento-operativo/importacion/pivoteo-firma/' + row.id + '" class="firma-link" title="Firmar pivoteo">' +
                '<i class="ti ti-writing text-primary fs-8"></i></a>';
        }

        var deshabilitado = est === 1 ? ' opacity-25' : '';
        return '<i class="ti ti-writing text-dark fs-8' + deshabilitado + '"></i>';
    }

    function renderAcciones(row) {
        var urlBase = '/departamento-operativo/importacion/pivoteo/' + row.id;

        var ver = row.puedeVer
            ? '<a href="javascript:void(0)" class="dropdown-item" data-action="pivoteo-detalle" data-id="' + row.id + '"><i class="ti ti-eye fs-6"></i> Detalle</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-eye fs-6"></i> Detalle</a>';

        var pdf = row.puedePDF
            ? '<a class="dropdown-item" target="_blank" href="/departamento-operativo/importacion/pivoteo/pdf/' + row.id + '"><i class="ti ti-file-type-pdf fs-6"></i> Descargar PDF</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-file-type-pdf fs-6"></i> Descargar PDF</a>';

        var gmail = row.puedeGmail
            ? '<a href="javascript:void(0)" class="dropdown-item" data-action="pivoteo-gmail" data-id="' + row.id + '" data-nocontrol="' + escHtml(row.nocontrol_txt || '') + '"><i class="ti ti-mail fs-6"></i> Envío por correo</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-mail fs-6"></i> Envío por correo</a>';

        var editar = row.puedeEditar
            ? '<a class="dropdown-item" href="' + urlBase + '"><i class="ti ti-pencil fs-6"></i> Editar</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-pencil fs-6"></i> Editar</a>';

        var eliminar = row.puedeEliminar
            ? '<a href="javascript:void(0)" class="dropdown-item" data-action="pivoteo-eliminar" data-id="' + row.id + '" data-name="#' + row.id + '"><i class="ti ti-trash fs-6"></i> Eliminar</a>'
            : '<a class="dropdown-item disabled"><i class="ti ti-trash fs-6"></i> Eliminar</a>';

        return '<div class="dropdown dropstart">' +
            '<a href="javascript:void(0)" data-bs-toggle="dropdown">' +
            '<i class="ti ti-dots-vertical fs-6"></i>' +
            '</a>' +
            '<div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">' +
            ver + pdf + gmail + editar + eliminar +
            '</div>' +
            '</div>';
    }

    var COLUMNA_ESTACION = 1;
    var COLUMNA_ACCIONES = 7;

    var table = $('#tabla-pivoteo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
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
            actualizarBadgePendientes();
        },
        drawCallback: function () {
            aplicarVisibilidad();
        },
        ajax: {
            url: '/departamento-operativo/importacion/pivoteo/data',
            type: 'GET',
            dataSrc: function (json) {
                return json.data || [];
            }
        },
        columns: [
            {
                data: 'id',
                title: '#',
                width: '80px',
                className: 'text-center align-middle fw-bold',
                render: function (data, type, row) {
                    return escHtml(data);
                }
            },
            { data: 'estacion', title: 'Estación', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'fecha', title: 'Fecha', className: 'text-center align-middle', render: function (d, t, row) { return t === 'display' ? renderFecha(row) : escHtml(d); } },
            { data: 'sucursal', title: 'Sucursal', className: 'text-center align-middle', render: function (d) { return escHtml(d) || 'Sin información'; } },
            { data: 'causa', title: 'Causa', className: 'text-center align-middle', render: function (d) { return escHtml(d) || 'Sin información'; } },
            { data: 'estatus', title: 'Estatus', className: 'text-center align-middle', width: '110px', render: function (data, type, row) { return type === 'display' ? renderEstatus(row) : escHtml(row.estatus_texto); } },
            { data: 'firmas', title: 'Firma', className: 'text-center align-middle', width: '90px', orderable: false, searchable: false, render: function (data, type, row) { return type === 'display' ? renderFirma(row) : ''; } },
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

    window.tablaPivoteo = table;

    function aplicarVisibilidad() {
        try {
            table.column(COLUMNA_ESTACION).visible(!isSeleccionEspecifica());
            table.column(COLUMNA_ACCIONES).visible(true);
        } catch (e) {}
    }

    $(document).on('click', '[data-action="pivoteo-eliminar"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('pivoteo-eliminar', {
            detail: { id: $(this).data('id'), name: $(this).data('name') }
        }));
    });

    $(document).on('click', '[data-action="pivoteo-detalle"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('pivoteo-detalle', {
            detail: { id: $(this).data('id') }
        }));
    });

    $(document).on('click', '[data-action="pivoteo-gmail"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('pivoteo-gmail', {
            detail: {
                id: $(this).data('id'),
                nocontrol: $(this).data('nocontrol')
            }
        }));
    });

    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(getModuleStationKey(), {
            customReload: function () {
                try {
                    aplicarVisibilidad();
                    window.tablaPivoteo.ajax.reload(null, false);
                    actualizarBadgePendientes();
                    document.dispatchEvent(new CustomEvent('pivoteo-estacion-change'));
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }

    document.addEventListener('pivoteo-estacion-change', function () {
        actualizarBadgePendientes();
    });
});