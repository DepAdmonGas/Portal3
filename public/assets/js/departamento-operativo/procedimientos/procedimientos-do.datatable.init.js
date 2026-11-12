document.addEventListener('DOMContentLoaded', () => {

    const contenedor = document.getElementById('procedimientos-do');
    if (!contenedor) return;

    const tabla = document.getElementById('tabla-procedimientos-do');
    if (!tabla) return;

    if (!window.jQuery || !jQuery.fn.DataTable) return;

    const modulo = contenedor.dataset.modulo || '';
    if (!modulo) return;

    const $tabla = jQuery(tabla);

    if (jQuery.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    const escapar = (valor) => jQuery('<div>').text(valor === null || valor === undefined ? '' : valor).html();

    const urlDatos = '/departamento-operativo/procedimientos/' + modulo + '/data';

    const columnas = [
        {
            title: '#',
            data: 'id',
            className: 'align-middle text-center',
            width: '96px'

        },
        {
            title: 'Fecha',
            data: 'fecha_iso',
            className: 'align-middle text-center',
            render: (valor, tipo, fila) => escapar(fila.fecha)
        },
        {
            title: 'Título',
            data: 'titulo',
            className: 'align-middle',
            render: (valor) => escapar(valor)
        },
        {
            title: '<i class="ti ti-download text-primary fs-6"></i>',
            data: null,
            className: 'align-middle text-center text-nowrap',
            orderable: false,
            searchable: false,
            width: '48px',
            render: (valor, tipo, fila) => {
                if (!fila.archivo_disponible) {
                    return '<span class="text-muted" title="Archivo no disponible"><i class="ti ti-file-off fs-5"></i></span>';
                }

                const archivo = encodeURIComponent(fila.archivo || '');
                const url = '/download?tipo=procedimientos-modulos&file=' + archivo;

                return '<a class="pointer text-decoration-none" href="' + url + '" title="Descargar archivo">'
                    + '<i class="ti ti-download text-primary fs-6"></i></a>';
            }
        },
        {
            title: '<i class="ti ti-dots-vertical fs-5"></i>',
            data: null,
            className: 'align-middle text-center text-nowrap',
            orderable: false,
            searchable: false,
             width: '48px',
            render: (valor, tipo, fila) => {
                let html = '<div class="dropdown dropstart"><a class="pointer" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></a><div class="dropdown-menu">';

                html += '<a class="dropdown-item pointer btn-procedimiento-detalle"><i class="ti ti-eye me-1"></i> Detalle</a>';

                if (fila.archivo_disponible) {
                    const archivo = encodeURIComponent(fila.archivo || '');
                    html += '<a class="dropdown-item" href="/download?tipo=procedimientos-modulos&file=' + archivo + '"><i class="ti ti-download me-1"></i> Descargar documento</a>';
                } else {
                    html += '<a class="dropdown-item disabled"><i class="ti ti-file-off me-1"></i> Archivo no disponible</a>';
                }

                html += '<a class="dropdown-item pointer btn-procedimiento-eliminar"><i class="ti ti-trash me-1"></i> Eliminar</a>';
                html += '</div></div>';

                return html;
            }
        }
    ];

    $tabla.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            type: 'GET',
            url: urlDatos,
            dataSrc: (json) => (json && json.success ? (json.data || []) : [])
        },
        autoWidth: false,
        stateSave: false,
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: columnas
    });

    const filaActual = (elemento) => $tabla.DataTable().row(jQuery(elemento).closest('tr')).data();

    $tabla.on('click', '.btn-procedimiento-detalle', function (e) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('procedimientos-do:detalle', { detail: filaActual(this) }));
    });

    $tabla.on('click', '.btn-procedimiento-eliminar', function (e) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('procedimientos-do:eliminar', { detail: filaActual(this) }));
    });

});
