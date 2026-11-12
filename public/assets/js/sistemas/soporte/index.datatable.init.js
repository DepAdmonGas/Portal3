// ============================================================
// Soporte (Sistemas) — Inicialización de la tabla.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const tabla = $('#tabla-soporte');

    if (!tabla.length) return;

    const contenedor = document.getElementById('soporte');
    const puedeEditar = contenedor?.dataset.puedeEditar === 'true';
    const puedeEliminar = contenedor?.dataset.puedeEliminar === 'true';

    /** Dropdown de acciones de la fila (mismo patrón que el resto del portal).
     *  Detalle, Editar y Eliminar siempre visibles; se deshabilitan según el
     *  estado del ticket (heredado: Editar y Eliminar sólo en "Creando") y los
     *  permisos del usuario.
     *
     *  Los items habilitados llevan data-bs-dismiss="dropdown" para que el
     *  menú se cierre al pulsarlos. */
    function renderAcciones(row) {
        const enCreacion = row.estado === 0;

        const puedeEditarFila = enCreacion && puedeEditar;
        const puedeEliminarFila = enCreacion && puedeEliminar;

        const editar = '<a class="dropdown-item pointer btn-sop-editar' + (puedeEditarFila ? '' : ' disabled') + '"'
                     + ' data-id="' + row.id_ticket + '"'
                     + (puedeEditarFila ? ' data-bs-dismiss="dropdown"' : ' aria-disabled="true" tabindex="-1"') + '>'
                     + '<i class="ti ti-pencil me-1"></i> Editar</a>';

        const eliminar = '<a class="dropdown-item pointer btn-sop-eliminar' + (puedeEliminarFila ? '' : ' disabled') + '"'
                       + ' data-id="' + row.id_ticket + '"'
                       + (puedeEliminarFila ? ' data-bs-dismiss="dropdown"' : ' aria-disabled="true" tabindex="-1"') + '>'
                       + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        return '<div class="dropdown dropstart">'
             + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
             + '<div class="dropdown-menu dropdown-menu-end">'
             + '<a class="dropdown-item pointer btn-sop-detalle" data-id="' + row.id_ticket + '" data-bs-dismiss="dropdown">'
             + '<i class="ti ti-eye me-1"></i> Detalle</a>'
             + editar
             + eliminar
             + '</div></div>';
    }

    /** Texto plano: si el valor viene vacío se muestra "S/I". */
    function renderTexto(v) {
        const valor = (v === null || v === undefined) ? '' : String(v).trim();

        return valor === '' ? 'S/I' : valor;
    }

    /** Prioridad dentro de un badge: Baja (verde), Media (amarillo), Alta (rojo).
     *  Sigue el patrón de badges del portal (badge bg-success / bg-warning / bg-danger). */
    function renderPrioridad(row) {
        const prioridad = (row.prioridad || '').trim();

        if (prioridad === '') {
            return '<span class="text-muted">S/I</span>';
        }

        if (prioridad === 'Baja') {
            return '<span class="badge bg-success text-white">' + prioridad + '</span>';
        }

        if (prioridad === 'Media') {
            return '<span class="badge bg-warning text-white">' + prioridad + '</span>';
        }

        return '<span class="badge bg-danger text-white">' + prioridad + '</span>';
    }

    /** Estatus dentro de un badge: Finalizado (verde), Creando (amarillo),
     *  el resto (Pendiente, En proceso, Cancelado) en rojo. */
    function renderEstatus(row) {
        const estado = Number(row.estado);
        const texto = (row.estado_texto || '').trim() || 'S/I';

        if (estado === 3) {
            return '<span class="badge bg-success text-white">' + texto + '</span>';
        }

        if (estado === 0) {
            return '<span class="badge bg-warning text-white">' + texto + '</span>';
        }

        return '<span class="badge bg-danger text-white">' + texto + '</span>';
    }

    /** Celda de comentarios con el badge histórico (mismo que el resto del portal). */
    function renderComentarios(row) {
        const total = row.total_comentarios || 0;
        const ocultoBadge = total > 0 ? '' : ' d-none';
        const title = total === 1 ? '1 comentario' : total + ' comentarios';

        return '<a class="btn-sop-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center pointer"'
             + ' data-id="' + row.id_ticket + '"'
             + ' title="' + title + '">'
             + '<i class="ti ti-message fs-7"></i>'
             + '<span class="badge-historico position-absolute top-0 start-100 translate-middle' + ocultoBadge + '">' + total + '</span>'
             + '</a>';
    }

    /** Aplica un color de fondo a la fila (tr) y a sus celdas.
     *
     *  Bootstrap 5.3 pinta el fondo de las celdas con
     *  `box-shadow: inset 0 0 0 9999px var(--bs-table-bg-type)`, de modo que
     *  un `background-color` solo queda tapado por el table-striped.
     *  Por eso se fijan la variable de Bootstrap y el box-shadow, además del
     *  color de fondo, para que el color siempre se imponga. */
    function pintarFila($row, color) {
        $row.css('--bs-table-bg-type', color);

        $row.find('td').css({
            'background-color': color,
            'box-shadow': 'inset 0 0 0 9999px ' + color
        });
    }

    tabla.DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sistemas/soporte/data',
            type: 'GET',
            dataSrc: function (json) {
                // Se conservan las filas para pintar el detalle de inmediato
                // (sin esperar la respuesta del servidor).
                window.soporteFilas = json.data || [];

                return window.soporteFilas;
            }
        },
        columns: [
            {
                title: '# Ticket',
                data: 'folio',
                className: 'align-middle text-center',
                width: '96px',
                render: function (v) { return '<b>' + (v || '') + '</b>'; }
            },
            { title: 'Descripción', data: 'descripcion', className: 'align-middle text-start', render: renderTexto },
            {
                title: 'Fecha Creación',
                data: 'fecha_creacion',
                className: 'align-middle text-center',
                render: renderTexto
            },
            {
                title: 'Prioridad',
                data: 'prioridad',
                className: 'align-middle text-center',
                render: function (v, t, row) {
                    return renderPrioridad(row);
                }
            },
            {
                title: 'Fecha inicio',
                data: 'fecha_inicio',
                className: 'align-middle text-center',
                render: renderTexto
            },
            {
                title: 'Fecha término (Programado)',
                data: 'fecha_termino',
                className: 'align-middle text-center',
                render: renderTexto
            },
            {
                title: 'Porcentaje',
                data: 'porcentaje',
                className: 'align-middle text-center',
                render: function (v) { return (v ?? 0) + ' %'; }
            },
            {
                title: 'Responsable',
                data: 'responsable',
                className: 'align-middle text-center',
                render: renderTexto
            },
            {
                title: 'Fecha término real',
                data: 'fecha_termino_real',
                className: 'align-middle text-center',
                render: function (v) {
                    return v ? '<div class="text-success fw-semibold">' + v + '</div>' : 'S/I';
                }
            },
            {
                title: 'Estatus',
                data: 'estado_texto',
                className: 'align-middle text-center',
                render: function (v, t, row) {
                    return renderEstatus(row);
                }
            },

            {
                title: '<i class="ti ti-message fs-7"></i>',
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                searchable: false,
                width: '24px',
                render: function (v, t, row) { return renderComentarios(row); }
            },
            {
                title: '<i class="ti ti-dots-vertical fs-5"></i>',
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                searchable: false,
                width: '40px',
                render: function (v, t, row) { return renderAcciones(row); }
            }
        ],
        createdRow: function (row, data) {
            if (data.color_fila) {
                pintarFila($(row), data.color_fila);
            }

            // Ticket que espera la validación del solicitante: se marca
            // con el tooltip para que sea fácil de identificar.
            if (data.puede_validar) {
                $(row)
                    .addClass('se-fila-validar')
                    .attr('title', 'Este ticket espera tu validación');
            }
        }
    });

    // ---- Detalle ----
    tabla.off('click', '.btn-sop-detalle').on('click', '.btn-sop-detalle', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('soporte:detalle', { detail: { id } }));
    });

    // ---- Comentarios ----
    tabla.off('click', '.btn-sop-comentarios').on('click', '.btn-sop-comentarios', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('soporte:comentarios', { detail: { id } }));
    });

    // ---- Editar ----
    tabla.off('click', '.btn-sop-editar').on('click', '.btn-sop-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('soporte:editar', { detail: { id } }));
    });

    // ---- Eliminar ----
    tabla.off('click', '.btn-sop-eliminar').on('click', '.btn-sop-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('soporte:eliminar', { detail: { id } }));
    });

});
