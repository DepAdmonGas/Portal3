document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $table = $('#tabla-programar-horario');
    if (!$table.length) return;

    const moduleStationKey = c.dataset.moduleStationKey || '';

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
        $table.DataTable().destroy();
    }

    function getEstacionId() {
        var sel = document.getElementById('module-station-selector-programar-horario');
        if (sel && sel.value) {
            var p = sel.value.split('_');
            if (p.length === 2 && p[1]) return parseInt(p[1]);
        }
        return parseInt(c.dataset.idEstacion || '0');
    }

    function isTodasEstaciones() {
        var sel = document.getElementById('module-station-selector-programar-horario');
        return sel && sel.value === '';
    }

    function buildUrl() {
        return '/departamento-operativo/recursos-humanos/programar-horario/get-data';
    }

    var EMPTY_URL = '/departamento-operativo/recursos-humanos/programar-horario/get-data';
    var initialUrl = buildUrl();

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function estatusBadge(estado) {
        if (estado === 1) return '<span class="badge bg-success">Finalizado</span>';
        return '<span class="badge bg-danger text-white">Pendiente</span>';
    }

    function renderAcciones(row) {
        var esPendiente = row.estado == 0;

        var html = '<div class="dropdown dropstart">';
        html += '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a>';
        html += '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer btn-detalle" data-id="' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';

        html += '<a class="dropdown-item pointer btn-editar' + (esPendiente ? '' : ' disabled') + '" data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';

        html += '<a class="dropdown-item pointer btn-eliminar' + (esPendiente ? '' : ' disabled') + '" data-id="' + row.id + '" data-name="Reporte #' + row.id + '" data-action="eliminar"><i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';
        return html;
    }

    var columns = [
        { title: '#', data: 'id', className: 'align-middle text-center', width: '48px' },
        { title: 'Estación/Departamento', data: 'nombre_estacion', className: 'align-middle text-start text-nowrap', visible: false },
        { title: 'Fecha programada', data: 'fecha', className: 'align-middle text-start' },
        { title: 'Estatus', data: 'estado', className: 'align-middle text-center text-nowrap',
            render: function(v, t, row) { return estatusBadge(row.estado); }
        },
        {
            title: '<i class="fas fa-ellipsis-v"></i>',
            data: null,
            className: 'align-middle text-center',
            orderable: false,
            searchable: false,
            width: '48px',
            render: function(v, t, row) { return renderAcciones(row); }
        }
    ];

    window.tablaProgramarHorario = $table.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            type: 'GET',
            url: initialUrl,
            dataSrc: function(json) {
                if (!json.success) return [];
                return json.data || [];
            }
        },
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: columns,
        drawCallback: function() {
            if (window.Alpine) {
                Alpine.initTree(document.querySelector('#tabla-programar-horario'));
            }
            var dropdowns = document.querySelectorAll('#tabla-programar-horario .dropdown-toggle');
            dropdowns.forEach(function(el) {
                new bootstrap.Dropdown(el);
            });
            toggleAgregarButton();
        }
    });

    $table.on('click', '.btn-detalle', function(e) {
        e.preventDefault();
        var id = parseInt(this.dataset.id);
        window.location.href = '/departamento-operativo/recursos-humanos/programar-horario-detalle/' + id;
    });

    $table.on('click', '.btn-editar', function(e) {
        e.preventDefault();
        if (this.classList.contains('disabled')) return;
        var id = parseInt(this.dataset.id);
        window.location.href = '/departamento-operativo/recursos-humanos/programar-horario-formulario/' + id;
    });

    $table.on('click', '.btn-eliminar', function(e) {
        e.preventDefault();
        if (this.classList.contains('disabled')) return;
        var id = parseInt(this.dataset.id);
        var name = this.dataset.name || 'Reporte #' + id;
        if (window.programarHorarioComponentInstance) {
            window.programarHorarioComponentInstance.eliminarReporte(id, name);
        }
    });

    function toggleAgregarButton() {
        var wrapper = document.getElementById('ph-agregar-wrapper');
        if (!wrapper) return;
        if (isTodasEstaciones()) {
            wrapper.style.display = 'none';
        } else {
            wrapper.style.display = '';
        }
    }

    function recargarTabla() {
        var dt = window.tablaProgramarHorario;
        if (!dt) return;
        dt.ajax.url(EMPTY_URL).load();
    }

    function toggleEstacionColumn(dt) {
        if (!dt) return;
        var col = dt.column(1);
        col.visible(isTodasEstaciones());
    }

    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {
            customReload: function(ms) {
                try {
                    recargarTabla();
                } catch (e) {
                    console.error('[ProgramarHorario] Error recargando tabla:', e);
                }
                toggleEstacionColumn(window.tablaProgramarHorario);
                toggleAgregarButton();
                document.dispatchEvent(new Event('ph:estacion-cambio'));
            }
        });
    }

    toggleEstacionColumn(window.tablaProgramarHorario);
    toggleAgregarButton();

});
