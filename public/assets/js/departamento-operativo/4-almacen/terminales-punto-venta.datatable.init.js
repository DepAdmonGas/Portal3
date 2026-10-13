// ============================================================
// Terminales Punto de Venta — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-terminales-punto-venta');
    if (!$tabla.length) return;

    const BASE_URL = c.dataset.baseUrl || '/departamento-operativo/almacen/terminales-punto-venta';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const puedeEditar    = c.dataset.puedeEditar === 'true';
    // Eliminar una terminal es exclusivo de multiestación: a los demás la
    // opción les aparece en el dropdown pero deshabilitada.
    const puedeEliminar  = c.dataset.puedeEliminarRegistro === 'true';

    /**
     * true cuando el contexto está en "Todas las estaciones", es decir cuando el
     * usuario no tiene una estación fija y el selector no está en una estación
     * concreta. Los usuarios pertenecientes a una estación (que no reciben
     * selector) nunca muestran la columna: estacionFija llega en true desde el
     * servidor.
     */
    function estacionVisible() {
        return c.dataset.estacionFija === 'false';
    }

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    function clase(activo) { return activo ? '' : ' disabled'; }

    function renderReparaciones(count) {
        const total = parseInt(count, 10) || 0;
        const badge = total > 0
            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + total + '</span>'
            : '';
        return '<span class="btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-tpv-reparaciones" '
             + 'title="Reparaciones">'
             + '<i class="ti ti-tool fs-8"></i>' + badge + '</span>';
    }

    function renderAcciones(row) {
        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer btn-tpv-detalle" data-id="' + row.id + '">'
              + '<i class="ti ti-eye me-1"></i> Detalle</a>';

        html += '<a class="dropdown-item pointer btn-tpv-editar' + clase(puedeEditar) + '"'
              + ' data-id="' + row.id + '">'
              + '<i class="ti ti-pencil me-1"></i> Editar</a>';

        // La falla se puede reportar siempre: la lleva el módulo de fallas.
        html += '<a class="dropdown-item pointer btn-tpv-fallas" data-id="' + row.id + '">'
              + '<i class="ti ti-alert-triangle me-1"></i> Falla TPV</a>';

        html += '<a class="dropdown-item pointer btn-tpv-eliminar' + clase(puedeEliminar) + '"'
              + ' data-id="' + row.id + '" data-nombre="' + (row.tpv || '') + '"'
              + (puedeEliminar ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
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
                if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
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
        order: [[0, 'asc']],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: [
            {
                title: '#',
                data: 'id',
                className: 'align-middle text-center text-nowrap',
                width: '96px'
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
                title: 'Modelo',
                data: 'modelo',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de lote',
                data: 'no_lote',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Tipo de conexión',
                data: 'tipo_conexion',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de afiliación',
                data: 'no_afiliacion',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Teléfono',
                data: 'telefono',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Activas',
                data: 'estado',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Rollos',
                data: 'rollos',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Cargadores',
                data: 'cargadores',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Pedestales',
                data: 'pedestales',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de impresiones',
                data: 'no_impresiones_label',
                className: 'align-middle text-center text-nowrap'
            },
            {
            title: 'Condición',
            data: 'estatus_tpv',
            className: 'align-middle text-center text-nowrap',
            render: (v) => v || ''
            },
            {
                title: 'Tipo de TPV',
                data: 'tipo_tpv',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. de <br> reparaciones',
                data: 'total_reparaciones',
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                render: (v) => renderReparaciones(v)
            },
            {
                title: 'Estatus',
                data: 'estatus_label',
                className: 'align-middle text-center text-nowrap',
                render: (v, t, row) => '<span class="badge rounded-pill ' + (row.estatus_badge || 'bg-success text-white') + '">' + v + '</span>'
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
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.getElementById('tabla-terminales-punto-venta'));
            }
        }
    });

    function reloadTable() {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
            $tabla.DataTable().ajax.reload(null, false);
        }
    }

    // ---- Detalle ----
    $tabla.off('click', '.btn-tpv-detalle').on('click', '.btn-tpv-detalle', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('tpv:detalle', { detail: { id: id } }));
    });

    // ---- Editar ----
    $tabla.off('click', '.btn-tpv-editar').on('click', '.btn-tpv-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('tpv:editar', { detail: { id: id } }));
    });

    // ---- Fallas ----
    $tabla.off('click', '.btn-tpv-fallas').on('click', '.btn-tpv-fallas', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('tpv:fallas', { detail: { id: id } }));
    });

    // ---- Eliminar ----
    $tabla.off('click', '.btn-tpv-eliminar').on('click', '.btn-tpv-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const nombre = this.dataset.nombre;
        document.dispatchEvent(new CustomEvent('tpv:eliminar', { detail: { id: id, nombre: nombre } }));
    });

    // ---- Selector de estación ----
    // Recarga la página para que View.php re-renderice el selector y la columna
    // Estación aparezca o se oculte server-side, igual que en el resto del proyecto.
    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {
            customReload: function () {
                window.location.reload();
            }
        });
    }

    window.reloadTerminalesTPV = reloadTable;
});
