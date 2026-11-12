// ============================================================
// Incidencias — Inicialización de la tabla y del selector de estación.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const tabla = $('#tabla-incidencias');

    if (!tabla.length) return;

    const contenedor = document.getElementById('incidencias');
    const puedeEditar = contenedor?.dataset.puedeEditar === 'true';
    const puedeEliminar = contenedor?.dataset.puedeEliminar === 'true';
    const puedeDescargar = contenedor?.dataset.puedeDescargar === 'true';
    const claveModulo = contenedor?.dataset.modulo || 'incidencias';
    const tipoDescarga = contenedor?.dataset.tipoDescarga || claveModulo;

    let mostrarEstacion = contenedor?.dataset.mostrarEstacion !== 'false';

    /** Dropdown de acciones de la fila (mismo patrón que el resto del DO). */
    function renderAcciones(row) {
        const clase = (activo) => activo ? '' : ' disabled';

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu dropdown-menu-end">';

        html += '<a class="dropdown-item pointer btn-inc-detalle" data-id="' + row.id + '">'
              + '<i class="ti ti-eye me-1"></i> Detalle</a>';

        if (puedeDescargar) {
            if (row.existe_archivo) {
                html += '<a class="dropdown-item pointer btn-inc-descargar" href="/download?tipo='
                      + encodeURIComponent(tipoDescarga)
                      + '&file=' + encodeURIComponent(row.archivo) + '" download>'
                      + '<i class="ti ti-download me-1"></i> Descargar archivo</a>';
            } else {
                html += '<a class="dropdown-item disabled" aria-disabled="true">'
                      + '<i class="ti ti-file-off me-1"></i> Descargar archivo</a>';
            }
        }

        html += '<a class="dropdown-item pointer btn-inc-editar' + clase(puedeEditar) + '"'
              + ' data-id="' + row.id + '"'
              + (puedeEditar ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-pencil me-1"></i> Editar</a>';

        html += '<a class="dropdown-item pointer text-danger btn-inc-eliminar' + clase(puedeEliminar) + '"'
              + ' data-id="' + row.id + '" data-asunto="' + (row.asunto || '') + '"'
              + (puedeEliminar ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';

        return html;
    }

    /** Lista de columnas según lo que corresponde mostrar. */
    function construirColumnas(conEstacion) {
        const columnas = [
            { title: '#', data: 'id', className: 'align-middle text-center', width: '96px', orderable: true, searchable: true }
        ];

        if (conEstacion) {
            columnas.push({
                title: 'Estación',
                data: 'estacion',
                className: 'align-middle text-center',
                orderable: false,
                searchable: false
            });
        }

        columnas.push({
            title: 'Fecha y hora',
            data: 'fecha_hora_fmt',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true
        });

        columnas.push({
            title: 'Incidente',
            data: 'incidente',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true
        });

        columnas.push({
            title: 'Responsable',
            data: 'responsable',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true
        });

        columnas.push({
            title: 'Asunto',
            data: 'asunto',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true
        });

        columnas.push({
            title: 'Comentarios',
            data: 'comentarios',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true,
            render: function (v) {
                const texto = v || '';
                const corto = texto.length > 60 ? texto.substring(0, 60) + '…' : texto;
                return '<span title="' + String(texto).replace(/"/g, '&quot;') + '">' + corto + '</span>';
            }
        });

        columnas.push({
            title: '<i class="ti ti-dots-vertical fs-6"></i>',
            data: null,
            className: 'align-middle text-center',
            orderable: false,
            searchable: false,
            width: '48px',
            render: function (v, t, row) {
                return renderAcciones(row);
            }
        });

        return columnas;
    }

    let dt = null;

    function crearTabla() {
        return tabla.DataTable({
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
                url: '/incidencias/data',
                type: 'GET',
                dataSrc: function (json) {
                    if (typeof json.mostrar_fila_estacion === 'boolean'
                        && json.mostrar_fila_estacion !== mostrarEstacion) {
                        mostrarEstacion = json.mostrar_fila_estacion;
                        recrear();
                        return json.data || [];
                    }

                    return json.data || [];
                }
            },
            columns: construirColumnas(mostrarEstacion)
        });
    }

    function recrear() {
        if ($.fn.DataTable.isDataTable(tabla)) {
            tabla.DataTable().destroy();
        }
        dt = crearTabla();
        if ($.fn.DataTable.isDataTable(tabla)) {
            tabla.DataTable().ajax.reload(null, false);
        }
    }

    dt = crearTabla();

    // ---- Detalle ----
    tabla.off('click', '.btn-inc-detalle').on('click', '.btn-inc-detalle', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('incidencias:detalle', { detail: { id } }));
    });

    // ---- Editar ----
    tabla.off('click', '.btn-inc-editar').on('click', '.btn-inc-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('incidencias:editar', { detail: { id } }));
    });

    // ---- Eliminar ----
    tabla.off('click', '.btn-inc-eliminar').on('click', '.btn-inc-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        const asunto = this.dataset.asunto || '';
        document.dispatchEvent(new CustomEvent('incidencias:eliminar', { detail: { id, asunto } }));
    });

    // ---- Selector de estación ----
    if (window.ModuleStationSelector) {
        ModuleStationSelector.dataTableMap[claveModulo] = '#tabla-incidencias';
        ModuleStationSelector.init(claveModulo);
    }

});
