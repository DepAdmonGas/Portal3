// ============================================================
// Mantenimiento Preventivo — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-mantenimiento-preventivo');
    if (!$tabla.length) return;

    const BASE_URL       = c.dataset.baseUrl || '/departamento-operativo/almacen/mantenimiento-preventivo';
    const YEAR           = c.dataset.idYear || '';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const puedeDescargar = c.dataset.puedeDescargar === 'true';
    const puedeEditar    = c.dataset.puedeEditar === 'true';
    const puedeEliminar  = c.dataset.puedeEliminar === 'true';

    const FONDOS = { 0: '#ffb6af', 1: '#fcfcda', 2: '#b0f2c2' };

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    function clase(activo) { return activo ? '' : ' disabled'; }

    /**
     * La columna Estación sólo aparece cuando el usuario está en "TODAS LAS ESTACIONES".
     * Cuando hay una estación seleccionada se oculta, igual que el legacy (ocultarTB).
     */
    function estacionVisible() {
        if (!moduleStationKey) return false;
        if (typeof ModuleStationSelector === 'undefined') return false;

        const instancia = ModuleStationSelector._instances[moduleStationKey];
        if (!instancia) return false;

        return !instancia.getValue().id_estacion;
    }

    function estadoDescarga(row) {
        if (!puedeDescargar) {
            return { habilitado: false, motivo: 'No tienes permiso de descarga.' };
        }

        if (!row.orden_servicio) {
            return { habilitado: false, motivo: 'Este registro no tiene orden de servicio.' };
        }

        if (!row.orden_servicio_existe) {
            return { habilitado: false, motivo: 'El archivo no está disponible en el servidor.' };
        }

        return { habilitado: true, motivo: 'Descargar orden de servicio' };
    }

    function renderOrden(row) {
        const descarga = estadoDescarga(row);

        if (descarga.habilitado) {
            return '<a href="javascript:void(0)" class="pointer btn-mp-descargar"'
                 + ' data-file="' + encodeURIComponent(row.orden_servicio) + '" title="Descargar orden de servicio">'
                 + '<i class="ti ti-download fs-6 text-primary"></i></a>';
        }

        if (row.orden_servicio) {
            return '<i class="ti ti-file-off fs-6 text-muted" title="' + descarga.motivo + '"></i>';
        }

            return '<i class="ti ti-file-off fs-6 text-muted" title="' + descarga.motivo + '"></i>';
    }

    function renderAcciones(row) {
        const puedeStatus  = puedeEditar && row.status < 2;
        const puedeEditarR = puedeEditar && row.status === 0;
        const puedeElimR   = puedeEliminar && row.status < 2;
        const descarga     = estadoDescarga(row);

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer btn-mp-status' + clase(puedeStatus) + '"'
              + ' data-id="' + row.id + '" data-folio="' + row.folio_label + '" data-status="' + row.status + '">'
              + '<i class="ti ti-rotate me-1"></i> Actualizar Status</a>';

        html += '<a class="dropdown-item pointer btn-mp-editar' + clase(puedeEditarR) + '"'
              + ' data-id="' + row.id + '">'
              + '<i class="ti ti-pencil me-1"></i> Editar</a>';

              /*
        html += '<a class="dropdown-item pointer btn-mp-descargar' + clase(descarga.habilitado) + '"'
              + ' data-file="' + (descarga.habilitado ? encodeURIComponent(row.orden_servicio) : '') + '"'
              + ' title="' + descarga.motivo + '"'
              + (descarga.habilitado ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-download me-1"></i> Descargar orden</a>';
                */

        html += '<a class="dropdown-item pointer btn-mp-eliminar' + clase(puedeElimR) + '"'
              + ' data-id="' + row.id + '" data-nombre="' + row.folio_label + '">'
              + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';

        return html;
    }

    $tabla.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: BASE_URL + '/data/' + YEAR,
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
        order: [[2, 'desc']],
        pageLength: 15,
        lengthMenu: [15, 30, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: [
            {
                title: '#',
                data: null,
                className: 'align-middle text-center text-nowrap fw-normal',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v, t, row, meta) => meta.row + 1
            },
                        {
                title: 'Folio',
                data: 'folio_label',
                className: 'align-middle text-center text-nowrap',
                width: '64px'
            },
            {
                title: 'Estación / Departamento',
                data: 'estacion_nombre',
                className: 'align-middle text-center text-nowrap',
                visible: estacionVisible(),
                render: (v) => v || ''
            },

            {
                title: 'Encargado',
                data: 'encargado',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Fecha mantenimiento',
                data: 'fecha_mantenimiento',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Próxima fecha',
                data: 'proxima_fecha',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Próxima prueba',
                data: 'proxima_prueba',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Orden de servicio',
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                render: (v, t, row) => renderOrden(row)
            },
            {
                title: 'Tipo de mantenimiento',
                data: 'tipo_mantenimiento',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Costo',
                data: 'costo_label',
                className: 'align-middle text-end text-nowrap'
            },
            {
                title: 'Observaciones',
                data: 'observaciones',
                className: 'align-middle text-center'
            },
            {
                title: 'Estatus',
                data: 'status',
                className: 'align-middle text-center text-nowrap',

                render: (v, t, row) => '<span class="badge rounded-pill ' + row.status_badge + '">' + row.status_label + '</span>'
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
        // Colores de fila por status, como el legacy.
        rowCallback: function (row, data) {
            const fondo = FONDOS[data.status];
            if (fondo) {
                $(row).css('background-color', fondo);
            }
        },
        footerCallback: function (row, data, start, end, display) {
            let total = 0;
            display.forEach((idx) => {
                total += (data[idx].costo || 0);
            });
            const moneda = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            $(this.api().table().footer()).html(
                '<tr><th class="text-end text-white" colspan="8">Costo total:</th><th class="text-end text-white"> $' + moneda + '</th><th colspan="4"></th></tr>'
            );
        },
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.getElementById('tabla-mantenimiento-preventivo'));
            }
        }
    });

    // ---- Contador de pendientes (status 0) del alcance actual ----
    // La tabla devuelve todas las filas del año/estación filtrado; al recargarse
    // (guardar, estatus, eliminar) se recalcula y se sincroniza el badge.
    $tabla.on('xhr.dt', function (e, settings, json) {
        const filas = (json && json.data) ? json.data : [];
        let pend = 0;
        for (let i = 0; i < filas.length; i++) {
            if ((filas[i].status | 0) === 0) pend++;
        }
        document.dispatchEvent(new CustomEvent('mp:pendientes', { detail: { total: pend } }));
    });

    // ---- Descargar (usa el Download global) ----
    $tabla.off('click', '.btn-mp-descargar').on('click', '.btn-mp-descargar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).hasClass('disabled')) return;
        window.open('/download?tipo=mantenimiento-preventivo&file=' + this.dataset.file, '_blank');
    });

    // ---- Actualizar Status ----
    $tabla.off('click', '.btn-mp-status').on('click', '.btn-mp-status', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const folio = this.dataset.folio;
        const status = parseInt(this.dataset.status, 10);
        document.dispatchEvent(new CustomEvent('mp:status', { detail: { id: id, folio: folio, status: status } }));
    });

    // ---- Editar ----
    $tabla.off('click', '.btn-mp-editar').on('click', '.btn-mp-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('mp:editar', { detail: { id: id } }));
    });

    // ---- Eliminar ----
    $tabla.off('click', '.btn-mp-eliminar').on('click', '.btn-mp-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const nombre = this.dataset.nombre;
        document.dispatchEvent(new CustomEvent('mp:eliminar', { detail: { id: id, nombre: nombre } }));
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
});