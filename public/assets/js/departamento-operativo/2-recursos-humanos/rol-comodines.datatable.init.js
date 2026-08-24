document.addEventListener('DOMContentLoaded', function () {

    var permisos = {};

    function cls(enabled) { return enabled ? '' : ' disabled'; }

    function estatusBadge(status) {
        if (status == 1) return '<span class="badge bg-success">Finalizado</span>';
        return '<span class="badge bg-danger">Pendiente</span>';
    }

function renderAcciones(row) {
    var esBorrador = row.status == 0;
    var noEdit = !esBorrador; 

    var html = '<div x-data="actions()" class="d-flex gap-1 justify-content-center">';
    html += '<div class="dropdown dropstart">';
    html += '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>';
    html += '<ul class="dropdown-menu">';

    html += '<li><a class="dropdown-item d-flex align-items-center pointer gap-3' + cls(!esBorrador) + '" href="javascript:void(0)" data-action="detalle" data-id="' + row.id + '">';
    html += '<i class="ti ti-eye fs-4"></i>Detalle</a></li>';

    // Opción Editar
    html += '<li><a class="dropdown-item d-flex align-items-center gap-3 ' + (noEdit ? 'disabled text-muted' : '') + '" ';
    html += 'data-id="' + row.id + '" ';
    html += '@click="$dispatch(\'open-edit\', ' + row.id + ')" ';
    html += 'href="' + (noEdit ? 'javascript:void(0)' : '/departamento-operativo/recursos-humanos/rol-comodines/' + row.id) + '">';
    html += '<i class="fs-4 ti ti-edit"></i>Editar</a></li>';

    html += '<li><a class="dropdown-item d-flex align-items-center pointer gap-3' + cls(!esBorrador) + '" href="javascript:void(0)" data-action="pdf" data-id="' + row.id + '">';
    html += '<i class="ti ti-file-text fs-4"></i>Descargar PDF</a></li>';

    // Opción Eliminar con la validación aplicada
    html += '<li><a class="dropdown-item d-flex align-items-center pointer gap-3 text-danger ' + (!esBorrador ? 'disabled text-muted' : '') + '" ';
    html += 'href="javascript:void(0)" ';
    html += (!esBorrador ? '' : 'data-action="eliminar" ') + 'data-id="' + row.id + '" data-name="Rol #' + row.id + '">';
    html += '<i class="ti ti-trash fs-4"></i>Eliminar</a></li>';

    html += '</ul></div></div>';
    return html;
}

    var table = $('#tabla-rol-comodines').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/departamento-operativo/recursos-humanos/rol-comodines/data',
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
    width: '48px', 
    className: 'text-center align-middle fw-bold',
    render: function(data) {
        return String(data).padStart(3, '0');
    }
},            { data: 'fecha_inicio', title:'Fecha de Inicio' , className: 'align-middle' },
            { data: 'fecha_fin', title:'Fecha de termino' , className: 'align-middle' },
            { data: 'status_label', title:'Estatus', width: '100px', className: 'text-center align-middle',
              render: function (data, type, row) { return estatusBadge(row.status); }
            },
            { data: null, title:'<i class="ti ti-dots-vertical fs-6">', width: '1%', orderable: false, searchable: false, className: 'text-center align-middle',
              render: function (data, type, row) { return renderAcciones(row); }
            }
        ],
        rowCallback: function (row, data) {
            if (data.status == 0) {
                $(row).css('background-color', '#fcfcda');
            } else {
                $(row).css('background-color', '#b0f2c2');
            }
        },
        drawCallback: function () {
            $('#tabla-rol-comodines [data-bs-toggle="dropdown"]').each(function () {
                new bootstrap.Dropdown(this, { popperConfig: { strategy: 'fixed' } });
            });
        }
    });

    $('#tabla-rol-comodines tbody').on('click', '[data-action]', function (e) {
        e.preventDefault();
        var $el = $(this);
        if ($el.hasClass('disabled')) return;

        var action = $el.data('action');
        var id = parseInt($el.data('id'));

        if (action === 'detalle' && typeof rolComodinesActions.verDetalle === 'function') {
            rolComodinesActions.verDetalle(id);
        } else if (action === 'pdf' && typeof rolComodinesActions.descargarPDF === 'function') {
            rolComodinesActions.descargarPDF(id);
        } else if (action === 'eliminar' && typeof rolComodinesActions.eliminarRol === 'function') {
            rolComodinesActions.eliminarRol(id);
        }
    });

    window.rolComodinesReload = function () { table.ajax.reload(null, false); };
});
