document.addEventListener('DOMContentLoaded', function () {

    function getContainer() {
        return document.getElementById('container');
    }

    function getModuleStationKey() {
        var c = getContainer();
        return c ? (c.dataset.moduleStationKey || 'formato-descarga-merma') : 'formato-descarga-merma';
    }

    function getIdYear() {
        var c = getContainer();
        return c ? (c.dataset.idYear || '') : '';
    }

    function getIdMes() {
        var c = getContainer();
        return c ? (c.dataset.idMes || '') : '';
    }

    function getPuedeEditar() {
        var c = getContainer();
        return c ? c.dataset.puedeEditar === 'true' : false;
    }

    function getPuedeEliminar() {
        var c = getContainer();
        return c ? c.dataset.puedeEliminar === 'true' : false;
    }

    function getShowEstacionInicial() {
        var c = getContainer();
        if (!c || c.dataset.multiestacion !== 'true') return false;
        var sel = document.getElementById('module-station-selector-' + getModuleStationKey());
        if (!sel) return false;
        var v = sel.value;
        return !v || v.indexOf('depto_') === 0;
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function cls(enabled) { return enabled ? '' : ' disabled'; }

    function renderAcciones(row) {
        var puedeEditar = getPuedeEditar();
        var puedeEliminar = getPuedeEliminar();
        var html = '<div class="dropdown">';
        html += '<a href="javascript:void(0)" data-bs-toggle="dropdown" data-bs-popper-config=\'{"strategy":"fixed"}\' aria-haspopup="true" aria-expanded="false"><i class="ti ti-dots-vertical fs-6"></i></a>';
        html += '<div class="dropdown-menu dropdown-menu-end">';
        html += '<a class="dropdown-item pointer" href="/departamento-operativo/importacion/formato-descarga-merma-detalle/' + row.id + '"><i class="ti ti-eye me-1"></i> Detalle</a>';
        html += '<a class="dropdown-item pointer" href="/departamento-operativo/importacion/formato-descarga-merma/pdf/' + row.id + '" target="_blank"><i class="ti ti-file-type-pdf me-1"></i> Descargar PDF</a>';
        html += '<a class="dropdown-item pointer" href="/departamento-operativo/importacion/formato-descarga-merma/excel/' + row.id + '"><i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel</a>';
        html += '<a class="dropdown-item pointer' + cls(puedeEditar) + '" href="' + (puedeEditar ? '/departamento-operativo/importacion/formato-descarga-merma-editar/' + row.id : 'javascript:void(0)') + '"><i class="ti ti-pencil me-1"></i> Editar</a>';
        html += '<a class="dropdown-item pointer btn-eliminar' + cls(puedeEliminar) + '" href="#" data-id="' + row.id + '" data-name="Formato #00' + escHtml(row.folio) + '"><i class="ti ti-trash me-1"></i> Eliminar</a>';
        html += '</div></div>';
        return html;
    }

    var showEstacion = getShowEstacionInicial();

    var table = $('#tabla-merma').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [25, 50, 75, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/departamento-operativo/importacion/formato-descarga-merma/data',
            type: 'GET',
            data: function () {
                var params = {};
                var y = getIdYear();
                var m = getIdMes();
                if (y) params.year = y;
                if (m) params.mes = m;
                return params;
            },
            dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
           {
    data: 'folio',
    title: 'Folio',
    width: '80px',
    className: 'text-center align-middle fw-semibold',
    render: function (data) {
        return '00' + escHtml(data);
    }
},
            {
                data: 'estacion',
                title: 'Estación',
                visible: showEstacion,
                className: 'text-center align-middle',
                render: function (d) { return escHtml(d); }
            },
 { title: 'Fecha y hora', data: 'fecha_hora', className: 'align-middle text-center text-nowrap' },
            {
                data: 'responsable',
                title: 'Responsable',
                className: 'text-center align-middle',
                render: function (d) { return escHtml(d); }
            },
            {
                data: 'producto',
                title: 'Producto',
                className: 'text-center align-middle',
                render: function (d) { return escHtml(d); }
            },
            {
                data: 'num_comentarios',
                title: '<i class="ti ti-message fs-7"></i>',
                width: '50px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (d, type, row) {
                    var badge = row.num_comentarios > 0
                        ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + row.num_comentarios + '</span>'
                        : '';
                    return '<a href="" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-id="' + row.id + '" title="Comentarios">'
                        + '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
                }
            },
            {
                data: 'id',
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                width: '50px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                    return renderAcciones(row);
                }
            }
        ]
    });

    window.tablaMerma = table;

    $table = $('#tabla-merma');
    $table.on('click', '.btn-comentarios', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id);
        const event = new CustomEvent('abrir-comentarios', { detail: { id: id } });
        document.dispatchEvent(event);
    });

    $table.on('click', '.btn-eliminar', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id);
        const name = this.dataset.name || 'Formato #' + id;
        document.dispatchEvent(new CustomEvent('eliminar-merma', { detail: { id: id, name: name } }));
    });

    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(getModuleStationKey(), {
            customReload: function (inst) {
                try {
                    var v = inst ? inst.getValue() : { id_estacion: null, id_depto: null };
                    var showCol = !v.id_estacion;
                    window.tablaMerma.column(1).visible(showCol);
                    window.tablaMerma.ajax.reload(null, false);
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }
});

function descargarExcel() {
    var c = document.getElementById('container');
    if (!c) return;
    var year = c.dataset.idYear;
    var mes = c.dataset.idMes;
    window.location.href = '/departamento-operativo/importacion/formato-descarga-merma/excel-general?year=' + year + '&mes=' + mes;
}
