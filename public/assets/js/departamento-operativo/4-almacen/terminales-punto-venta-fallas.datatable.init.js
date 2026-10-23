// ============================================================
// Fallas de Terminales Punto de Venta — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-fallas-tpv');
    if (!$tabla.length) return;

    const BASE_URL = c.dataset.baseUrl || '/departamento-operativo/almacen/terminales-punto-venta-fallas';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const idTpv = parseInt(c.dataset.idTpv || '0', 10);
    const puedeEditar    = c.dataset.puedeEditar === 'true';
    const puedeEliminar  = c.dataset.puedeEliminar === 'true';
    const puedeDescargar = c.dataset.puedeDescargar === 'true';
    const TIPO_DESCARGA  = 'tpv';

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    function clase(activo) { return activo ? '' : ' disabled'; }

    /**
     * La columna Estación sólo aparece en la vista por estación (sin id de
     * terminal) y cuando el contexto está en "Todas las estaciones".
     */
    function estacionVisible() {
        if (idTpv > 0) return false;
        return c.dataset.estacionFija === 'false';
    }

    function renderDocumento(nombre, etiqueta) {
        if (!nombre) {
            return '<i class="ti ti-file-off fs-7 text-muted" title="Documento no disponible"></i>';
        }

        if (!puedeDescargar) {
            return '<i class="ti ti-file-off fs-7 text-muted" title="No tienes permiso de descarga."></i>';
        }

        return '<a href="javascript:void(0)" class="pointer btn-falla-descargar"'
             + ' data-file="' + encodeURIComponent(nombre) + '"'
             + ' title="Descargar ' + etiqueta + '">'
             + '<i class="ti ti-file-download fs-7 text-primary"></i></a>';
    }

    function renderComentarios(count, idFalla) {
        const total = parseInt(count, 10) || 0;
        const badge = total > 0
            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + total + '</span>'
            : '';

        return '<a href="javascript:void(0)" class="btn-comentarios pointer btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-falla-comentarios"'
             + ' data-id="' + idFalla + '" title="Comentarios">'
             + '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
    }

    function renderAcciones(row) {
        // El backend ya calculó quién puede tocar cada acción según el estado
        // de la falla y si el usuario es multiestación.
        const puedeActualizar = puedeEditar && row.puede_actualizar;
        const puedeBorrar = puedeEliminar && row.puede_eliminar;

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer btn-falla-detalle" data-id="' + row.id + '">'
              + '<i class="ti ti-eye me-1"></i> Detalle</a>';

        html += '<a class="dropdown-item pointer btn-falla-anexos" data-id="' + row.id + '">'
              + '<i class="ti ti-paperclip me-1"></i> Anexos</a>';

        const esEnReparacion = Number(row.status) === 1;
        const texto = esEnReparacion ? 'Marcar como finalizada' : 'Actualizar estatus';

        html += '<a class="dropdown-item pointer btn-falla-editar' + clase(puedeActualizar) + '"'
              + ' data-id="' + row.id + '" data-modo="' + (esEnReparacion ? 'finalizar' : 'editar') + '"'
              + (puedeActualizar ? '' : ' aria-disabled="true" tabindex="-1"')
              + ' title="' + (puedeActualizar ? texto : 'No tienes permiso para actualizar el estatus de esta falla.') + '">'
              + '<i class="ti ti-refresh me-1"></i> ' + texto + '</a>';

        html += '<a class="dropdown-item pointer btn-falla-eliminar' + clase(puedeBorrar) + '"'
              + ' data-id="' + row.id + '" data-nombre="' + (row.id || row.falla || '') + '"'
              + (puedeBorrar ? '' : ' aria-disabled="true" tabindex="-1"')
              + ' title="' + (puedeBorrar ? 'Eliminar' : 'No tienes permiso para eliminar esta falla.') + '">'
              + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';

        return html;
    }

    $tabla.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: BASE_URL + '/data',
            type: 'POST',
            data: function (d) {
                if (idTpv > 0) {
                    d.id_tpv = idTpv;
                } else if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
                    const instancia = ModuleStationSelector._instances[moduleStationKey];
                    const v = instancia ? instancia.getValue() : { id_estacion: null };
                    if (v.id_estacion) d.id_estacion = v.id_estacion;
                }
            },
            dataSrc: function (json) {
                return json && json.success ? (json.data || []) : [];
            }
        },
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: [
            {
                title: '#',
                data: 'id',
                className: 'align-middle text-center text-nowrap fw-normal',
                width: '96px',
            },
            {
                title: 'Estación',
                data: 'estacion_nombre',
                className: 'align-middle text-center text-nowrap',
                visible: estacionVisible(),
                render: (v) => v || ''
            },
            {
                title: 'TPV',
                data: 'tpv',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de serie',
                data: 'no_serie',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Personal que reportó la falla',
                data: 'reporta',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Fecha de falla',
                data: 'fecha_label',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de reporte',
                data: 'no_reporte',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Persona que atendió',
                data: 'atiende',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Falla',
                data: 'falla',
                className: 'align-middle text-center'
            },
            {
                title: 'Vida útil / garantía',
                data: 'vida_util',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Cotización',
                data: 'cotizacion',
                className: 'align-middle text-center text-nowrap',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v) => renderDocumento(v, 'cotización')
            },
            {
                title: 'Factura',
                data: 'factura',
                className: 'align-middle text-center text-nowrap',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v) => renderDocumento(v, 'factura')
            },
            {
                title: 'Estado',
                data: 'status_label',
                className: 'align-middle text-center text-nowrap',
                render: (v, t, row) => '<span class="badge rounded-pill ' + row.status_badge + '">' + v + '</span>'
            },
            {
                title: '<i class="ti ti-message fs-7"></i>',
                data: 'comentarios',
                className: 'align-middle text-center text-nowrap',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v, t, row) => renderComentarios(v, row.id)
            },
            {
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                data: null,
                className: 'align-middle text-center text-nowrap',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v, t, row) => renderAcciones(row)
            }
        ],
        // Color de fila por estatus, igual que el legacy.
        rowCallback: function (row, data) {
            if (Number(data.status) === 0) {
                $(row).css('background-color', '#ffb6af');
            } else if (Number(data.status) === 1) {
                $(row).css('background-color', '#fff3cd');
            } else if (Number(data.status) === 2) {
                $(row).css('background-color', '#d1e7dd');
            }
        },
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.getElementById('tabla-fallas-tpv'));
            }
        }
    });

    function reloadTable() {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
            $tabla.DataTable().ajax.reload(null, false);
        }
    }

    // ---- Descargar documento ----
    $tabla.off('click', '.btn-falla-descargar').on('click', '.btn-falla-descargar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        window.open('/download?tipo=' + encodeURIComponent(TIPO_DESCARGA)
                  + '&file=' + this.dataset.file, '_blank');
    });

    // ---- Detalle ----
    $tabla.off('click', '.btn-falla-detalle').on('click', '.btn-falla-detalle', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('falla:detalle', { detail: { id: id } }));
    });

    // ---- Anexos ----
    $tabla.off('click', '.btn-falla-anexos').on('click', '.btn-falla-anexos', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('falla:anexos', { detail: { id: id } }));
    });

    // ---- Editar / finalizar ----
    $tabla.off('click', '.btn-falla-editar').on('click', '.btn-falla-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);

        // Una falla "En reparación" ya tiene sus datos capturados: sólo falta
        // marcarla como finalizada, así que se confirma en lugar de reabrir el
        // modal de captura.
        if ($(this).data('modo') === 'finalizar') {
            document.dispatchEvent(new CustomEvent('falla:finalizar', { detail: { id: id } }));
            return;
        }

        document.dispatchEvent(new CustomEvent('falla:editar', { detail: { id: id } }));
    });

    // ---- Eliminar ----
    $tabla.off('click', '.btn-falla-eliminar').on('click', '.btn-falla-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const nombre = this.dataset.nombre;
        document.dispatchEvent(new CustomEvent('falla:eliminar', { detail: { id: id, nombre: nombre } }));
    });

    // ---- Comentarios ----
    $tabla.off('click', '.btn-falla-comentarios').on('click', '.btn-falla-comentarios', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('falla:comentarios', { detail: { id: id } }));
    });

    // ---- Selector de estación ----
    // Sólo tiene sentido en la vista por estación; con terminal fija se recarga
    // igual que el resto de los módulos para repintar el contexto.
    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {
            customReload: function () {
                window.location.reload();
            }
        });
    }

    window.reloadFallasTPV = reloadTable;
});