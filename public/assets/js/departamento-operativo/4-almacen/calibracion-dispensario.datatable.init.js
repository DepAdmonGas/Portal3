// ============================================================
// Calibración de Dispensarios — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-calibracion-dispensarios');
    if (!$tabla.length) return;

    const BASE_URL       = c.dataset.baseUrl || '/departamento-operativo/almacen/calibracion-dispensarios';
    const TIPO_DESCARGA  = 'calibracion-dispensarios';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const puedeDescargar = c.dataset.puedeDescargar === 'true';
    const puedeEditar    = c.dataset.puedeEditar === 'true';
    const puedeEliminar  = c.dataset.puedeEliminar === 'true';

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

    /**
     * Estado de la descarga de una fila.
     * El ítem del dropdown se habilita sólo si hay permiso, el registro tiene
     * archivo y ese archivo existe físicamente en el servidor.
     */
    function estadoDescarga(row) {
        if (!puedeDescargar) {
            return { habilitado: false, motivo: 'No tienes permiso de descarga.' };
        }

        if (!row.archivo) {
            return { habilitado: false, motivo: 'Este registro no tiene archivo.' };
        }

        if (!row.archivo_existe) {
            return { habilitado: false, motivo: 'El archivo no está disponible en el servidor.' };
        }

        return { habilitado: true, motivo: 'Descargar PDF' };
    }

    function renderAcciones(row) {
        const descarga = estadoDescarga(row);

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

        html += '<a class="dropdown-item pointer btn-cd-editar' + clase(puedeEditar) + '"'
              + ' data-id="' + row.id + '"><i class="ti ti-pencil me-1"></i> Editar</a>';

        html += '<a class="dropdown-item pointer btn-cd-descargar' + clase(descarga.habilitado) + '"'
              + ' data-file="' + (descarga.habilitado ? encodeURIComponent(row.archivo) : '') + '"'
              + ' title="' + descarga.motivo + '"'
              + (descarga.habilitado ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-download me-1"></i> Descargar archivo</a>';

        html += '<a class="dropdown-item pointer btn-cd-eliminar' + clase(puedeEliminar) + '"'
              + ' data-id="' + row.id + '" data-nombre="' + row.periodo + ' ' + row.year + '">'
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
        order: [[0, 'desc']],
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
                title: 'Estación',
                data: 'estacion_nombre',
                className: 'align-middle text-center text-nowrap',
                visible: estacionVisible(),
                render: (v) => v || ''
            },
            {
                title: 'Año',
                data: 'year',
                className: 'align-middle text-center text-nowrap',
                width: '96px',
            },
            {
                title: 'Periodo',
                data: 'periodo',
                className: 'align-middle text-center text-nowrap'
            },
            {
                title: 'Fecha de captura',
                data: 'fecha',
                className: 'align-middle text-center text-nowrap'
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
                Alpine.initTree(document.getElementById('tabla-calibracion-dispensarios'));
            }
        }
    });

    // ---- Descargar (usa el Download global) ----
    $tabla.off('click', '.btn-cd-descargar').on('click', '.btn-cd-descargar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).hasClass('disabled')) return;
        window.open('/download?tipo=' + encodeURIComponent(TIPO_DESCARGA)
                  + '&file=' + this.dataset.file, '_blank');
    });

    // ---- Editar ----
    $tabla.off('click', '.btn-cd-editar').on('click', '.btn-cd-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        document.dispatchEvent(new CustomEvent('cd:editar', { detail: { id: id } }));
    });

    // ---- Eliminar ----
    $tabla.off('click', '.btn-cd-eliminar').on('click', '.btn-cd-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt(this.dataset.id, 10);
        const nombre = this.dataset.nombre;
        document.dispatchEvent(new CustomEvent('cd:eliminar', { detail: { id: id, nombre: nombre } }));
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
