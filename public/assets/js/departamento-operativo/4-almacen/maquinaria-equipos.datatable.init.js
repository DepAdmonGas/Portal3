// ============================================================
// Maquinaria y Equipos — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-maquinaria-equipos');
    if (!$tabla.length) return;

    const BASE_URL       = c.dataset.baseUrl || '/departamento-operativo/almacen/maquinaria-equipos';
    const TIPO_DESCARGA  = 'maquinaria-equipo';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const puedeDescargar = c.dataset.puedeDescargar === 'true';
    const puedeEditar    = c.dataset.puedeEditar === 'true';
    const puedeEliminar  = c.dataset.puedeEliminar === 'true';

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    function clase(activo) { return activo ? '' : ' disabled'; }

    /**
     * La columna Estación/Departamento solo aparece en "TODAS LAS ESTACIONES Y
     * DEPARTAMENTOS". Cuando hay una estación o un departamento seleccionado se
     * oculta, igual que el legacy (ocultarTB).
     */
    function estacionVisible() {
        if (!moduleStationKey) return false;
        if (typeof ModuleStationSelector === 'undefined') return false;

        const instancia = ModuleStationSelector._instances[moduleStationKey];
        if (!instancia) return false;

        const v = instancia.getValue();
        return !v.id_estacion && !v.id_depto;
    }

    /**
     * Estado de la descarga de un archivo (factura/manual).
     * Se habilita sólo si hay permiso, el registro tiene archivo y éste existe
     * físicamente en el servidor.
     */
    function estadoDescarga(row, tipo) {
        const archivo = tipo === 'factura' ? row.factura : row.manual;
        const existe  = tipo === 'factura' ? row.factura_existe : row.manual_existe;

        if (!puedeDescargar) {
            return { habilitado: false, motivo: 'No tienes permiso de descarga.' };
        }

        if (!archivo) {
            return { habilitado: false, motivo: 'Este registro no tiene ' + (tipo === 'factura' ? 'factura' : 'manual') + '.' };
        }

        if (!existe) {
            return { habilitado: false, motivo: 'El archivo no está disponible en el servidor.' };
        }

        return { habilitado: true, motivo: 'Descargar ' + (tipo === 'factura' ? 'factura' : 'manual') };
    }

    function renderArchivo(row, tipo) {
        const descarga = estadoDescarga(row, tipo);

        if (descarga.habilitado) {
            return '<a href="javascript:void(0)" class="pointer btn-mq-descargar"'
                 + ' data-tipo="' + tipo + '"'
                 + ' data-file="' + encodeURIComponent(tipo === 'factura' ? row.factura : row.manual) + '"'
                 + ' title="' + descarga.motivo + '">'
                 + '<i class="ti ti-file-download fs-6 text-primary"></i></a>';
        }

        return '<i class="ti ti-file-off fs-6 text-muted" title="' + descarga.motivo + '"></i>';
    }

    function renderComentarios(row) {
        const count = row.comentarios || 0;
        const badge = count > 0
            ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>'
            : '';
        return '<a class="btn-comentarios pointer btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center btn-mq-comentarios"'
             + ' data-id="' + row.id + '" title="Comentarios">'
             + '<i class="ti ti-message fs-7"></i>' + badge + '</a>';
    }

    function renderAcciones(row) {
        const puedeEdR = puedeEditar && row.acciones_disponibles;
        const puedeElR = puedeEliminar && row.acciones_disponibles;
        // Legacy: con la maquinaria dada de baja (estatus 1) la Bitácora se
        // muestra deshabilitada (grayscale), IgUAL que Editar/Eliminar.
        const bitacoraActiva = (row.estatus | 0) === 0;

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

    html += '<a class="dropdown-item pointer btn-mq-detalle" data-id="' + row.id + '">'
           + '<i class="ti ti-eye me-1"></i> Detalle</a>';

    html += '<a class="dropdown-item pointer btn-mq-bitacora' + clase(bitacoraActiva) + '"'
           + ' data-id="' + row.id + '"'
           + ' title="' + (bitacoraActiva ? 'Bitácora' : 'La maquinaria está dada de baja.') + '">'
           + '<i class="ti ti-clipboard-list me-1"></i> Bitácora</a>';

    html += '<a class="dropdown-item pointer btn-mq-editar' + clase(puedeEdR) + '"'
           + ' data-id="' + row.id + '">'
           + '<i class="ti ti-pencil me-1"></i> Editar</a>';

    html += '<a class="dropdown-item pointer btn-mq-eliminar' + clase(puedeElR) + '"'
           + ' data-id="' + row.id + '" data-nombre="' + row.maquinaria + '">'
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
                data: null,
                className: 'align-middle text-center text-nowrap fw-normal',
                orderable: false,
                searchable: false,
                width: '48px',
                render: (v, t, row, meta) => meta.row + 1
            },
            {
                title: 'Estación / Departamento',
                data: 'contexto_label',
                className: 'align-middle text-center text-nowrap',
                visible: estacionVisible(),
                render: (v) => v || ''
            },
            {
                title: 'Maquinaria',
                data: 'maquinaria',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Descripción',
                data: 'descripcion',
                className: 'align-middle text-center'
            },
            {
                title: 'Marca',
                data: 'marca',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Modelo',
                data: 'modelo',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'No. Serie',
                data: 'no_serie',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Fecha de compra',
                data: 'fecha_compra',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Fecha de instalación',
                data: 'fecha_instalacion',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Proveedor',
                data: 'proveedor',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Costo de compra',
                data: 'costo_label',
                className: 'align-middle text-end text-nowrap'
            },
            {
                title: 'Garantía',
                data: 'garantia',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Factura',
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                render: (v, t, row) => renderArchivo(row, 'factura')
            },
            {
                title: 'Manual',
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                render: (v, t, row) => renderArchivo(row, 'manual')
            },
            {
                title: '<i class="ti ti-message fs-7"></i>',
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center text-nowrap',
                width: '48px',
                render: (v, t, row) => renderComentarios(row)
            },
            {
                title: 'Estatus',
                data: 'estatus',
                className: 'align-middle text-center text-nowrap',
                render: (v, t, row) => '<span class="badge rounded-pill ' + row.estatus_badge + '">' + row.estatus_label + '</span>'
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
        // Color de fila para maquinaria dada de baja, igual que el legacy.
        rowCallback: function (row, data) {
            if ((data.estatus | 0) === 1) {
                $(row).css('background-color', '#ffb6af');
            }
        },
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.getElementById('tabla-maquinaria-equipos'));
            }
        }
    });

    // ---- Descargar factura/manual (usa el Download global) ----
    $tabla.off('click', '.btn-mq-descargar').on('click', '.btn-mq-descargar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).hasClass('disabled')) return;
        window.open('/download?tipo=' + encodeURIComponent(TIPO_DESCARGA)
                  + '&file=' + this.dataset.file, '_blank');
    });

    // ---- Detalle ----
    $tabla.off('click', '.btn-mq-detalle').on('click', '.btn-mq-detalle', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('mq:detalle', { detail: { id: id } }));
    });

    // ---- Bitácora ----
    $tabla.off('click', '.btn-mq-bitacora').on('click', '.btn-mq-bitacora', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('mq:bitacora', { detail: { id: id } }));
    });

    // ---- Editar ----
    $tabla.off('click', '.btn-mq-editar').on('click', '.btn-mq-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('mq:editar', { detail: { id: id } }));
    });

    // ---- Eliminar ----
    $tabla.off('click', '.btn-mq-eliminar').on('click', '.btn-mq-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const nombre = this.dataset.nombre;
        document.dispatchEvent(new CustomEvent('mq:eliminar', { detail: { id: id, nombre: nombre } }));
    });

    // ---- Comentarios ----
    $tabla.off('click', '.btn-mq-comentarios').on('click', '.btn-mq-comentarios', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('mq:comentarios', { detail: { id: id } }));
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