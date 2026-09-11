document.addEventListener('DOMContentLoaded', function () {

    var permisos = {};

    function getContainer() {
        return document.getElementById('container');
    }

    function getModuleStationKey() {
        var c = getContainer();
        return c ? (c.dataset.moduleStationKey || 'mediciones') : 'mediciones';
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

    function renderAcciones(row) {
 
        return '<a href="javascript:void(0)" class="text-danger pointer" data-action="eliminar-medicion" data-id="' + row.id + '" data-name="Medición #' + row.id + '">' +
'<i class="ti ti-trash fs-6 text-danger"></i>' +
'</a>';
    }

    var table = $('#tabla-mediciones').DataTable({
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
        initComplete: function () {
            aplicarVisibilidad();
        },
        drawCallback: function () {
            aplicarVisibilidad();
        },
        ajax: {
            url: '/departamento-operativo/importacion/mediciones/data',
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data;
            }
        },
        columns: [
            {
                data: 'id',
                title: '#',
                width: '96px',
                className: 'text-center align-middle fw-bold',
                render: function (data) {
                    return String(data).padStart(3, '0');
                }
            },
            { data: 'estacion', title: 'Estación', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'fecha', title: 'Fecha', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'factura', title: 'Factura', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
            { data: 'neto', title: 'Neto', className: 'text-end align-middle', render: function (d) { return escHtml(d); } },
            { data: 'bruto', title: 'Bruto', className: 'text-end align-middle', render: function (d) { return escHtml(d); } },
            { data: 'cuenta_litros', title: 'Cuenta litros', className: 'text-end align-middle', render: function (d) { return escHtml(d); } },
            { data: 'proveedor', title: 'Proveedor', className: 'text-center align-middle', render: function (d) { return escHtml(d); } },
{
    data: 'id',
    title: '<i class="ti ti-trash fs-6 text-danger"></i>',
    width: '48px',
    orderable: false,
    searchable: false,
    className: 'text-center align-middle',
    render: function (data, type, row) {
        return renderAcciones(row);
    }
}
        ]
    });

    window.tablaMediciones = table;

    // Índice de la columna de acciones a partir de las columnas definidas
    var COLUMNA_ACCIONES = 8;

    function aplicarVisibilidad() {
        try {
            table.column(1).visible(!isSeleccionEspecifica());
            table.column(COLUMNA_ACCIONES).visible(Boolean(permisos.puedeEliminar));
        } catch (e) {}
    }

    $(document).on('click', '[data-action="eliminar-medicion"]', function (e) {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('eliminar-medicion', {
            detail: { id: $(this).data('id'), name: $(this).data('name') }
        }));
    });

    // Selector de estación: recargar la tabla cuando se cambia contexto
    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(getModuleStationKey(), {
            customReload: function () {
                try {
                    aplicarVisibilidad();
                    window.tablaMediciones.ajax.reload(null, false);
                } catch (e) {
                    window.location.reload();
                }
            }
        });
    }
});