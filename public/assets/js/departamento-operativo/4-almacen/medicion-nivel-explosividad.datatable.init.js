// ============================================================
// Medición Nivel de Explosividad — Almacén
// Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const c = document.getElementById('container');
    if (!c) return;

    const $tabla = $('#tabla-medicion-nivel-explosividad');
    if (!$tabla.length) return;

    const BASE_URL = c.dataset.baseUrl || '/departamento-operativo/almacen/medicion-nivel-explosividad';
    const FORM_BASE_URL = '/departamento-operativo/almacen/medicion-nivel-explosividad-formulario';
    const DETALLE_BASE_URL = '/departamento-operativo/almacen/medicion-nivel-explosividad-detalle';
    const moduleStationKey = c.dataset.moduleStationKey || '';
    const puedeEditar = c.dataset.puedeEditar === 'true';
    const puedeEliminar = c.dataset.puedeEliminar === 'true';

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tabla)) {
        $tabla.DataTable().destroy();
    }

    function clase(activo) { return activo ? '' : ' disabled'; }

    /**
     * La columna Estación sólo aparece cuando el usuario está en
     * "TODAS LAS ESTACIONES"; con una estación fija se oculta.
     */
    function estacionVisible() {
        if (!moduleStationKey) return false;
        if (typeof ModuleStationSelector === 'undefined') return false;

        const instancia = ModuleStationSelector._instances[moduleStationKey];
        if (!instancia) return false;

        return !instancia.getValue().id_estacion;
    }

    /**
     * Semántica de estados heredada del legacy (inversa de lo habitual):
     *   estado 0 = borrador rosa → sólo se puede editar o eliminar, sin detalle
     *   estado 1 = finalizado verde → sólo se puede consultar
     */
function renderAcciones(row) {
        const borrador = Number(row.estado) === 0;

        // Se pueden clickear solo si tiene permiso y el registro está en borrador
        const activoEditar = puedeEditar && borrador;
        const activoEliminar = puedeEliminar && borrador;

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu">';

        // Detalle (solo si no es borrador)
        html += '<a class="dropdown-item pointer btn-ne-ver' + clase(!borrador) + '"'
              + ' data-id="' + row.id + '"'
              + (borrador ? ' title="El registro sigue en borrador y aún no tiene detalle."' : '')
              + (borrador ? ' aria-disabled="true" tabindex="-1"' : '')
              + '><i class="ti ti-eye me-1"></i> Detalle</a>';

        // Editar (visible siempre, pero inhabilitado igual que Detalle si no cumple condición)
        html += '<a class="dropdown-item pointer btn-ne-editar' + clase(activoEditar) + '"'
              + ' data-id="' + row.id + '"'
              + (!puedeEditar ? ' title="Sin permisos para editar."' : (!borrador ? ' title="Los registros finalizados sólo pueden consultarse."' : ''))
              + (!activoEditar ? ' aria-disabled="true" tabindex="-1"' : '')
              + '><i class="ti ti-pencil me-1"></i> Editar</a>';

        // Eliminar (visible siempre, pero inhabilitado igual que Detalle si no cumple condición)
        html += '<a class="dropdown-item pointer btn-ne-eliminar' + clase(activoEliminar) + '"'
              + ' data-id="' + row.id + '"'
              + ' data-nombre="Folio ' + row.folio_texto + '"'
              + (!puedeEliminar ? ' title="Sin permisos para eliminar."' : (!borrador ? ' title="Sólo se pueden eliminar borradores."' : ''))
              + (!activoEliminar ? ' aria-disabled="true" tabindex="-1"' : '')
              + '><i class="ti ti-trash me-1"></i> Eliminar</a>';

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
                title: 'Folio',
                data: 'folio',
                className: 'align-middle text-center text-nowrap fw-bold',
                width: '96px',
                type: 'num',
                render: (v, t, row) => row.folio_texto
            },
            {
                title: 'Estación',
                data: 'estacion_nombre',
                className: 'align-middle text-center text-nowrap',
                visible: estacionVisible(),
                render: (v) => v || ''
            },
            {
                title: 'Fecha',
                data: 'fecha',
                className: 'align-middle text-center text-nowrap',
                render: (v) => v || 'Sin información'
            },
            {
                title: 'Estatus',
                data: 'estado',
                className: 'align-middle text-center text-nowrap',
                width: '96px',
                render: function (v) {
                    return Number(v) === 0
                        ? '<span class="badge bg-danger">Pendiente</span>'
                        : '<span class="badge bg-success">Finalizado</span>';
                }
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
        // El legacy pintaba la fila completa: rosa el borrador, verde el finalizado.
        createdRow: function (tr, row) {
            tr.style.backgroundColor = Number(row.estado) === 0 ? '#ffb6af' : '#b0f2c2';
        },
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.getElementById('tabla-medicion-nivel-explosividad'));
            }
        }
    });

    // ---- Detalle (sólo finalizados) ----
    $tabla.off('click', '.btn-ne-ver').on('click', '.btn-ne-ver', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        window.location = DETALLE_BASE_URL + '/' + this.dataset.id;
    });

    // ---- Editar (sólo borradores) ----
    $tabla.off('click', '.btn-ne-editar').on('click', '.btn-ne-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        window.location = FORM_BASE_URL + '/' + this.dataset.id;
    });

    // ---- Eliminar (sólo borradores) ----
    $tabla.off('click', '.btn-ne-eliminar').on('click', '.btn-ne-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        document.dispatchEvent(new CustomEvent('ne:eliminar', {
            detail: { id: parseInt(this.dataset.id, 10), nombre: this.dataset.nombre }
        }));
    });

    // ---- Selector de estación ----
    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {
            customReload: function () {
                window.location.reload();
            }
        });
    }
});