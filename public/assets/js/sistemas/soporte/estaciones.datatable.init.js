// ============================================================
// Soporte (Estaciones) / Actividades (Sistemas) — Inicialización
// de la tabla. Es un componente COMPARTIDO por ambos módulos:
// la ruta base, el identificador de la tabla y el prefijo de los
// eventos se leen de los data-attributes del contenedor.
//
// Reutiliza el patrón de /sistemas/soporte (badges, dropdown,
// badge histórico de comentarios y columna Estación opcional).
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const contenedor = document.getElementById('soporte-estaciones')
        || document.getElementById('soporte-actividades');

    if (!contenedor) return;

    const tablaId = contenedor.dataset.tablaId || 'tabla-soporte-estaciones';
    const endpoint = contenedor.dataset.endpoint || '/sistemas/estaciones';
    const eventos = contenedor.dataset.eventos || 'soporte-estaciones';

    const tabla = $('#' + tablaId);

    if (!tabla.length) return;

    const mostrarEstacion = contenedor?.dataset.mostrarEstacion === 'true';
    const puedeEliminarTicket = contenedor?.dataset.puedeEliminar === 'true';
    // En Actividades (Sistemas) el responsable se asigna al capturar la
    // actividad, por lo que la fila no es asignable desde el listado.
    const filasAsignables = contenedor?.dataset.filasAsignables !== 'false';

    /** Azul table-info: filas pendientes de asignar responsable. */
    const COLOR_ASIGNABLE = '#cff4fc';

    /** Texto plano: si el valor viene vacío se muestra "S/I". */
    function renderTexto(v) {
        const valor = (v === null || v === undefined) ? '' : String(v).trim();

        return valor === '' ? 'S/I' : valor;
    }

    /** Prioridad dentro de un badge: Baja (verde), Media (amarillo), Alta (rojo). */
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

    /** Estatus dentro de un badge: Finalizado (verde), los demás en rojo. */
    function renderEstatus(row) {
        const estado = Number(row.estado);
        const texto = (row.estado_texto || '').trim() || 'S/I';

        if (estado === 3) {
        return '<span class="badge bg-success text-white">' + texto + '</span>';
        }

        if (estado === 2) {
        return '<span class="badge bg-warning text-white">' + texto + '</span>';
        }

        if (estado === 0) {
        return '<span class="badge bg-warning text-white">' + texto + '</span>';
        }

        return '<span class="badge bg-danger text-white">' + texto + '</span>';
    }

    /** Dropdown de acciones: Detalle, Seguimiento y Eliminar.
     *
     *  Las tres opciones siempre se muestran; cada una se deshabilita según
     *  el estado del ticket y los permisos:
     *  - Detalle: siempre disponible (abre el modal con la información).
     *  - Seguimiento: siempre disponible mientras tenga responsable
     *    asignado (la página se abre en sólo lectura si el trabajo ya
     *    terminó).
     *  - Eliminar: requiere ticket abierto (Pendiente / En proceso). */
    function renderAcciones(row) {
        const estado = Number(row.estado);
        const cerrado = estado === 3 || estado === 5;
        const conResponsable = Number(row.id_personal_soporte) > 0;

        const puedeSeguimiento = conResponsable;
        const puedeEliminar = !cerrado && puedeEliminarTicket;

        const seguimiento = '<a class="dropdown-item pointer btn-se-seguimiento' + (puedeSeguimiento ? '' : ' disabled') + '"'
                          + ' data-id="' + row.id_ticket + '"'
                          + (puedeSeguimiento ? ' data-bs-dismiss="dropdown"' : ' aria-disabled="true" tabindex="-1"')
                          + ' title="' + (conResponsable ? 'Ver el seguimiento del ticket' : 'Primero asigna un responsable') + '">'
                          + '<i class="ti ti-timeline me-1"></i> Seguimiento</a>';

        const eliminar = '<a class="dropdown-item pointer btn-se-eliminar' + (puedeEliminar ? '' : ' disabled') + '"'
                       + ' data-id="' + row.id_ticket + '"'
                       + (puedeEliminar ? ' data-bs-dismiss="dropdown"' : ' aria-disabled="true" tabindex="-1"') + '>'
                       + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        return '<div class="dropdown dropstart">'
             + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
             + '<div class="dropdown-menu dropdown-menu-end">'
             + '<a class="dropdown-item pointer btn-se-detalle" data-id="' + row.id_ticket + '" data-bs-dismiss="dropdown">'
             + '<i class="ti ti-eye me-1"></i> Detalle</a>'
             + seguimiento
             + eliminar
             + '</div></div>';
    }

    /** Cierra el menú desplegable de la fila (los <a> no lo hacen solos). */
    function cerrarDropdown(elemento) {
        const menu = elemento.closest('.dropdown');

        if (!menu) return;

        const toggle = menu.querySelector('[data-bs-toggle="dropdown"]');
        const inst = toggle ? bootstrap.Dropdown.getInstance(toggle) : null;

        if (inst) {
            inst.hide();
        }
    }

    /** Celda de comentarios con el badge histórico (mismo que el resto del portal). */
    function renderComentarios(row) {
        const total = row.total_comentarios || 0;
        const ocultoBadge = total > 0 ? '' : ' d-none';
        const title = total === 1 ? '1 comentario' : total + ' comentarios';

        return '<a class="btn-se-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center pointer"'
             + ' data-id="' + row.id_ticket + '"'
             + ' title="' + title + '">'
             + '<i class="ti ti-message fs-7"></i>'
             + '<span class="badge-historico position-absolute top-0 start-100 translate-middle' + ocultoBadge + '">' + total + '</span>'
             + '</a>';
    }

    // Columnas: la columna Estación sólo existe cuando se consultan todos
    // los contextos (si no, sería redundante).
    const columnas = [
        {
            title: '# Ticket',
            data: 'folio',
            className: 'align-middle text-center',
            width: '96px',
            render: function (v) { return '<b>' + (v || '') + '</b>'; }
        }
    ];

    if (mostrarEstacion) {
        columnas.push({
            title: 'Estación / Departamento',
            data: 'estacion',
            className: 'align-middle text-center',
            render: renderTexto
        });
    }

    columnas.push(
        {
            title: 'Fecha Creación',
            data: 'fecha_creacion',
            className: 'align-middle text-center',
            render: renderTexto
        },
        { title: 'Descripción', data: 'descripcion', className: 'align-middle text-start', render: renderTexto },
        {
            title: 'Prioridad',
            data: 'prioridad',
            className: 'align-middle text-center',
            render: function (v, t, row) { return renderPrioridad(row); }
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
            render: function (v, t, row) { return renderEstatus(row); }
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
    );

    /** Aplica un color de fondo a la fila (tr) y a sus celdas.
     *
     *  Bootstrap 5.3 pinta el fondo de las celdas con
     *  `box-shadow: inset 0 0 0 9999px var(--bs-table-bg-type)`, de modo que
     *  un `background-color`alone queda tapado por el table-striped.
     *  Por eso se fijan la variable de Bootstrap y el box-shadow, además del
     *  color de fondo, para que el color siempre se imponga. */
    function pintarFila($row, color) {
        $row.css('--bs-table-bg-type', color);

        $row.find('td').css({
            'background-color': color,
            'box-shadow': 'inset 0 0 0 9999px ' + color
        });
    }

    /** La fila es asignable cuando NO tiene responsable y el ticket NO
     *  está concluido: si ya está finalizado no se pinta ni se puede
     *  pulsar (el estado manda sobre el responsable). */
    function esAsignable(data) {
        return filasAsignables && !!data && !data.tiene_responsable && !data.esta_concluido;
    }

    /** Pinta las filas asignables en azul table-info y les indica que se
     *  pueden pulsar. */
    function pintarFilasAsignables() {
        $('#' + tablaId + ' tbody tr').each(function () {
            const data = tabla.DataTable().row(this).data();

            if (!esAsignable(data)) {
                return;
            }

            $(this)
                .addClass('se-fila-asignable pointer')
                .attr('title', 'Pulsa la fila para asignar un responsable');

            pintarFila($(this), COLOR_ASIGNABLE);
        });
    }

    tabla.DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        // Sin orden propio: se conserva el del servidor (responsable,
        // estado y fecha de creación), que deja primero los tickets
        // pendientes de asignar responsable, igual que el sistema anterior.
        order: [0, 'desc'],
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: endpoint + '/data',
            type: 'GET',
            dataSrc: function (json) {
                window[eventos + 'Filas'] = json.data || [];

                return window[eventos + 'Filas'];
            }
        },
        columns: columnas,
        drawCallback: function () {
            pintarFilasAsignables();
        },
        createdRow: function (row, data) {
            // Mientras el ticket no tenga responsable y no esté concluido
            // se puede pulsar toda la fila para asignarlo.
            if (esAsignable(data)) {
                $(row)
                    .addClass('se-fila-asignable pointer')
                    .attr('title', 'Pulsa la fila para asignar un responsable');

                pintarFila($(row), COLOR_ASIGNABLE);

                return;
            }

            if (data.color_fila) {
                pintarFila($(row), data.color_fila);
            }
        }
    });

    // ---- Fila completa: asignar responsable ----
    tabla.off('click', 'tbody tr.se-fila-asignable').on('click', 'tbody tr.se-fila-asignable', function (e) {
        // Los controles de la fila (dropdown y comentarios) no abren la asignación.
        if ($(e.target).closest('.dropdown, .btn-se-comentarios, a, button, input, select').length) {
            return;
        }

        const data = tabla.DataTable().row(this).data();

        // Los tickets ya concluidos no se pueden pulsar (no requieren
        // responsable y no se muestran con el color de asignación).
        if (!esAsignable(data)) {
            return;
        }

        document.dispatchEvent(new CustomEvent(eventos + ':asignar', { detail: { id: data.id_ticket } }));
    });

    // ---- Detalle (modal) ----
    tabla.off('click', '.btn-se-detalle').on('click', '.btn-se-detalle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        cerrarDropdown(this);
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent(eventos + ':detalle', { detail: { id } }));
    });

    // ---- Seguimiento ----
    tabla.off('click', '.btn-se-seguimiento').on('click', '.btn-se-seguimiento', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).hasClass('disabled')) return;
        cerrarDropdown(this);
        const id = parseInt($(this).data('id'), 10);
        window.location.href = endpoint + '/seguimiento/' + id;
    });

    // ---- Comentarios ----
    tabla.off('click', '.btn-se-comentarios').on('click', '.btn-se-comentarios', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent(eventos + ':comentarios', { detail: { id } }));
    });

    // ---- Eliminar (cancelar ticket) ----
    tabla.off('click', '.btn-se-eliminar').on('click', '.btn-se-eliminar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).hasClass('disabled')) return;
        cerrarDropdown(this);
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent(eventos + ':eliminar', { detail: { id } }));
    });

});
