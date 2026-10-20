// ============================================================
// Acuses de Recepción — Inicialización de la tabla.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const tabla = $('#tabla-acuses-recepcion');

    if (!tabla.length) return;

    const contenedor = document.getElementById('acuses-recepcion');
    const puedeEditar = contenedor?.dataset.puedeEditar === 'true';
    const puedeEliminar = contenedor?.dataset.puedeEliminar === 'true';

    /** Dropdown de acciones de la fila. */
    function renderAcciones(row) {
        const estado = Number(row.estado);

        // 0 = Pendiente -> Editar | 1 = En proceso -> Entrega | 2 = Finalizado
        const accionPrincipal = estado === 0
            ? { cls: 'btn-acr-editar', icon: 'ti-pencil', txt: 'Editar' }
            : (estado === 1
                ? { cls: 'btn-acr-entrega', icon: 'ti-package-export', txt: 'Entrega' }
                : { cls: '', icon: 'ti-check', txt: 'Finalizado' });

        // Cada acción siempre visible; deshabilitada según estado y permiso.
        const principalActiva = estado !== 2 && puedeEditar;
        const eliminarActiva = estado !== 2 && puedeEliminar;

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu dropdown-menu-end">';

        // Detalle: siempre disponible en todos los estados.
        html += '<a class="dropdown-item pointer btn-acr-detalle" data-id="' + row.id + '">'
              + '<i class="ti ti-eye me-1"></i> Detalle</a>';

        // Acción principal (Editar / Entrega / Finalizado).
        html += '<a class="dropdown-item ' + (principalActiva ? 'pointer ' + accionPrincipal.cls : 'disabled') + '"'
              + ' data-id="' + row.id + '"'
              + (principalActiva ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ' + accionPrincipal.icon + ' me-1"></i> ' + accionPrincipal.txt + '</a>';

        // Eliminar: visible siempre; no aplica a acuses finalizados.
        html += '<a class="dropdown-item ' + (eliminarActiva ? 'pointer btn-acr-eliminar' : 'disabled') + '"'
              + ' data-id="' + row.id + '"'
              + (eliminarActiva ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';

        return html;
    }

    tabla.DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/acuses-recepcion/data',
            type: 'GET',
            data: function (d) {
                d.buscar = document.getElementById('acuses-recepcion')?._filtro ?? '';
            },
            dataSrc: function (json) {
                return json.data || [];
            }
        },
        columns: [
            { title: '#', data: 'id', className: 'align-middle text-center', width: '96px', orderable: true, searchable: true },
            {
                title: 'Fecha',
                data: 'fecha_creacion_fmt',
                className: 'align-middle text-center text-nowrap',
                orderable: true,
                searchable: true
            },
            {
                title: 'Documentos',
                data: 'documentos_resumen',
                className: 'align-middle text-start',
                orderable: true,
                searchable: true
            },
            {
                title: 'Estatus',
                data: 'estado_texto',
                className: 'align-middle text-center',
                orderable: true,
                searchable: true,
                width: '100px',
                render: function (v, t, row) {
                    return '<span class="badge bg-' + (row.estado_color || 'secondary') + '">'
                         + (row.estado_texto || '') + '</span>';
                }
            },
            {
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                searchable: false,
                width: '48px',
                render: function (v, t, row) {
                    return renderAcciones(row);
                }
            }
        ]
    });

    const dt = tabla.DataTable();

    // ---- Editar (acuse Pendiente) ----
    tabla.off('click', '.btn-acr-editar').on('click', '.btn-acr-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('acuses-recepcion:editar', { detail: { id } }));
    });

    // ---- Entrega (acuse En proceso) ----
    tabla.off('click', '.btn-acr-entrega').on('click', '.btn-acr-entrega', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('acuses-recepcion:entrega', { detail: { id } }));
    });

    // ---- Detalle (acuse finalizado) ----
    tabla.off('click', '.btn-acr-detalle').on('click', '.btn-acr-detalle', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('acuses-recepcion:detalle', { detail: { id } }));
    });

    // ---- Eliminar ----
    tabla.off('click', '.btn-acr-eliminar').on('click', '.btn-acr-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('acuses-recepcion:eliminar', { detail: { id } }));
    });

});
