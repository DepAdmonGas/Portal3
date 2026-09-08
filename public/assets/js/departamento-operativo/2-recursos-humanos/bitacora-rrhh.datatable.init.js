$(document).ready(function () {

    var permisos = {};

    function getContainer() {
        return document.getElementById('container');
    }

    function getYearMes() {
        var c = getContainer();
        return {
            idYear: c ? (parseInt(c.dataset.idYear) || 0) : 0,
            idMes: c ? (parseInt(c.dataset.idMes) || 0) : 0
        };
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function rowColor(row) {
        var color = '';
        if (parseInt(row.estatus) === 1) {
            color = '#b0f2c2';
        } else if (parseInt(row.diff_dias) >= 3) {
            color = '#ffb6af';
        }
        return color;
    }

    function renderEstatus(row) {
        if (parseInt(row.estatus) === 1) {
            return '<span class="badge bg-success">Finalizado</span>';
        }
        return '<span class="badge bg-danger">Pendiente</span>';
    }

    function cls(enabled) { return enabled ? '' : ' disabled'; }

    function renderComentarios(row) {
        var conteo = parseInt(row.total_comentarios) || 0;
        var badge = conteo > 0
            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + conteo + '</span>'
            : '';
        return '<a href="javascript:void(0)" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-action="comentarios" data-id="' + row.id + '" title="Comentarios">' +
            '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
    }

    function renderVisualizaciones(row) {
        if (!permisos.puedeVerVisualizaciones) return '';
        var conteo = parseInt(row.total_visualizaciones) || 0;
        return '<a href="javascript:void(0)" class="btn-visualizaciones d-inline-flex align-items-center justify-content-center mx-1" data-action="visualizaciones" data-id="' + row.id + '" title="Visualizaciones">' +
            '<span class="badge rounded-pill bg-primary">' + conteo + '</span>' +
            '</a>';
    }

    function renderAcciones(row) {
        var html = '<div class="dropdown dropstart d-inline-block">';
        html += '<a href="javascript:void(0)" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="ti ti-dots-vertical fs-6"></i></a>';
        html += '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer" href="javascript:void(0)" data-action="detalle" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';

        html += '<a class="dropdown-item pointer' + cls(permisos.puedeEditar) + '" href="' + (permisos.puedeEditar ? '/departamento-operativo/recursos-humanos/bitacora-rrhh/formulario/' + row.id : 'javascript:void(0)') + '">';
        html += '<i class="ti ti-pencil me-1"></i> Editar</a>';

        if (parseInt(row.estatus) === 0 && permisos.puedeFinalizar) {
            html += '<a class="dropdown-item pointer" href="javascript:void(0)" data-action="finalizar" data-id="' + row.id + '"><i class="ti ti-circle-check me-1"></i> Finalizar</a>';
        }

        html += '<a class="dropdown-item pointer' + cls(permisos.puedeEliminar) + ' text-danger" href="javascript:void(0)" data-action="eliminar" data-id="' + row.id + '" data-name="Bitácora #' + row.id + '">';
        html += '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';
        return html;
    }

    var table = $('#tabla-bitacora-rrhh').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        searching: false,
        initComplete: function () {
            aplicarVisibilidadColumnaEstacion();
        },
        order: [],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/departamento-operativo/recursos-humanos/bitacora-rrhh/data',
            type: 'GET',
            data: function () {
                return getYearMes();
            },
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data;
            }
        },
        createdRow: function (row, data) {
            var color = rowColor(data);
            if (color) {
                $(row).css('background-color', color);
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
            { data: 'nombre_solicitante', title: 'Solicitante', className: 'align-middle', render: function (d) { return escHtml(d); } },
            { data: 'estacion', title: 'Estación / Departamento', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            {
                data: 'descripcion',
                title: 'Descripción',
                className: 'align-middle',
                render: function (d) {
                    return '<div style="white-space: normal; min-width: 200px; max-width: 350px;">' + escHtml(d) + '</div>';
                }
            },
            { data: 'fecha', title: 'Fecha', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            {
                data: null,
                title: 'Visualizaciones',
                width: '110px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return renderVisualizaciones(row);
                }
            },
            {
                data: null,
                title: '<i class="ti ti-message fs-7"></i>',
                width: '56px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return renderComentarios(row);
                }
            },

            {
                data: 'estatus',
                title: 'Estatus',
                width: '110px',
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return renderEstatus(row);
                }
            },
            {
                data: 'id',
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
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
                Alpine.initTree(document.querySelector('#tabla-bitacora-rrhh'));
            }
        }
    });

    window.tablaBitacoraRrhh = table;

    function isSeleccionEspecifica() {
        var sel = document.getElementById('module-station-selector-bitacora-rrhh');
        return !!(sel && sel.value);
    }

    function aplicarVisibilidadColumnaEstacion() {
        try {
            table.column(2).visible(!isSeleccionEspecifica());
        } catch (e) {}
    }

    $table = table;
    $table.on('xhr.dt', function () {
        document.dispatchEvent(new CustomEvent('tabla-recargada'));
    });

    $(document).on('click', '[data-action="comentarios"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('ver-comentarios-bitacora', { detail: { id: $(this).data('id') } }));
    });

    $(document).on('click', '[data-action="detalle"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('ver-detalle-bitacora', { detail: { id: $(this).data('id') } }));
    });

    $(document).on('click', '[data-action="visualizaciones"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('ver-visualizaciones-bitacora', { detail: { id: $(this).data('id') } }));
    });

    $(document).on('click', '[data-action="finalizar"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('finalizar-bitacora', { detail: { id: $(this).data('id') } }));
    });

    $(document).on('click', '[data-action="eliminar"]', function (e) {
        e.preventDefault();
        if (!permisos.puedeEliminar) {
            e.stopImmediatePropagation();
            return;
        }
        document.dispatchEvent(new CustomEvent('eliminar-bitacora', { detail: { id: $(this).data('id'), name: $(this).data('name') } }));
    });

    // Selector de estación: recargar la tabla cuando se cambia contexto
    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init('bitacora-rrhh', {
            customReload: function () {
                try {
                    aplicarVisibilidadColumnaEstacion();
                    window.tablaBitacoraRrhh.ajax.reload(null, false);
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }
});