$(document).ready(function () {

    var c = document.getElementById('container');
    if (!c) return;

    var permisos = {};

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function esContextoEspecifico() {
        if (c.dataset.multiestacion === 'true') {
            var sel = document.getElementById('module-station-selector-permisos');
            return !!(sel && sel.value);
        }
        return parseInt(c.dataset.idEstacion || '0') > 0;
    }

    function statusBadge(v, t, d) {
        if (t === 'display') {
            var cls = 'bg-danger text-white';
            if (d.estado === 0) cls = 'bg-danger text-white';
            else if (d.estado === 1) cls = 'bg-warning text-white';
            else if (d.estado === 2) cls = 'bg-success';
            return '<span class="badge rounded-pill ' + cls + '">' + escHtml(d.estado_label) + '</span>';
        }
        return d.estado_label;
    }

    function cls(enabled) { return enabled ? '' : ' disabled'; }

    function renderAcciones(row) {
        var html = '<div class="dropdown">';
        html += '<a href="javascript:void(0)" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="ti ti-dots-vertical fs-6"></i></a>';
        html += '<div class="dropdown-menu dropdown-menu-end">';

        html += '<a class="dropdown-item pointer" href="javascript:void(0)" data-action="detalle" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';

        html += '<a class="dropdown-item pointer' + cls(row.puede_editar) + '" href="' + (row.puede_editar ? '/departamento-operativo/recursos-humanos/permisos-editar/' + row.id : 'javascript:void(0)') + '">';
        html += '<i class="ti ti-pencil me-1"></i> Editar</a>';

        html += '<a class="dropdown-item pointer' + cls(row.puede_eliminar) + '" href="javascript:void(0)" data-action="eliminar" data-id="' + row.id + '" data-name="Permiso #' + row.id + '">';
        html += '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';
        return html;
    }

    var table = $('#tabla-permisos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        searching: true,
        searchable: true,
        lengthChange: true,
        paging: true,
        info: true,
        ordering: true,
        initComplete: function () {
            aplicarVisibilidadColumnaEstacion();
        },
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/departamento-operativo/recursos-humanos/permisos/data',
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data;
            }
        },
        createdRow: function (row, data) {
            var colors = { 0: '#ffb6af', 1: '#fcfcda', 2: '#b0f2c2' };
            $(row).css('background-color', colors[data.estado] || '#ffffff');
        },
        columns: [
            {
                data: 'id',
                title: '#',
                width: '64px',
                className: 'text-center align-middle fw-bold',
                render: function (d) {
                    return String(d).padStart(3, '0');
                }
            },
            { data: 'estacion', title: 'Estación / Departamento', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'nombre_colaborador', title: 'Colaborador', className: 'align-middle', render: function (d) { return escHtml(d); } },
            { data: 'cubre_nombre', title: 'Quien cubre', className: 'align-middle', render: function (d) { return escHtml(d); } },
            { data: 'estacion_cubre_nombre', title: 'Estación de quien cubre', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'fecha_inicio_label', title: 'Del', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'fecha_termino_label', title: 'Al', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'dias_tomados', title: 'Días', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'motivo', title: 'Motivo', className: 'align-middle', render: function (d) { return escHtml(d); } },
            {
                data: 'observaciones',
                title: 'Observaciones',
                className: 'align-middle',
                render: function (d) {
                    return '<div style="white-space: normal; min-width: 160px; max-width: 280px;">' + escHtml(d) + '</div>';
                }
            },
            {
                data: null,
                title: 'Firmar',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (d, t, row) {
                    if (row.estado === 2) {
                        return '<i class="ti ti-signature text-success fs-8" style="filter:grayscale(1) opacity(0.5);cursor:default;"></i>';
                    }
                    var activo = row.tiene_firma_a && (row.puede_firmar_cubre || row.puede_firmar_vobo);
                    if (activo) {
                        return '<a href="/departamento-operativo/recursos-humanos/permisos/firmar/' + row.id + '" class="firma-link" title="Firmar"><i class="ti ti-writing text-primary fs-8"></i></a>';
                    }
                    return '<i class="ti ti-writing text-dark fs-8" style="filter:grayscale(1) opacity(0.5);cursor:default;"></i>';
                }
            },
            {
                data: 'estado_label',
                title: 'Estatus',
                className: 'text-center align-middle',
                render: function (v, t, d) { return statusBadge(v, t, d); }
            },
            {
                data: null,
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                width: '48px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (d) { return renderAcciones(d); }
            }
        ],
        drawCallback: function () {
            $('#tabla-permisos [data-bs-toggle="dropdown"]').each(function () {
                try { new bootstrap.Dropdown(this, { popperConfig: { strategy: 'fixed' } }); } catch (e) {}
            });
            if (window.Alpine) {
                Alpine.initTree(document.querySelector('#tabla-permisos'));
            }
        }
    });

    window.tablaPermisos = table;

    function aplicarVisibilidadColumnaEstacion() {
        try {
            table.column(1).visible(!esContextoEspecifico());
        } catch (e) {}
    }

    table.on('xhr.dt', function () {
        document.dispatchEvent(new CustomEvent('tabla-recargada'));
    });

    $('#tabla-permisos tbody').on('click', '[data-action]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $el = $(this);
        if ($el.hasClass('disabled')) return;

        var action = $el.data('action');
        var id = parseInt($el.data('id'));

        if (action === 'detalle') {
            document.dispatchEvent(new CustomEvent('ver-detalle-permiso', { detail: { id: id } }));
        } else if (action === 'eliminar') {
            document.dispatchEvent(new CustomEvent('eliminar-permiso', { detail: { id: id, name: $el.data('name') } }));
        }
    });

    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init('permisos', {
            customReload: function () {
                try {
                    aplicarVisibilidadColumnaEstacion();
                    window.tablaPermisos.ajax.reload(null, false);
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }
});