document.addEventListener('DOMContentLoaded', () => {

    const tabla = $('#tabla-constancia-situacion-fiscal');

    if (!tabla.length) return;

    const contenedor = document.getElementById('constancia-situacion-fiscal');
    const puedeEliminar = contenedor?.dataset.puedeEliminar === 'true';
    const puedeDescargar = contenedor?.dataset.puedeDescargar === 'true';
    const claveModulo = contenedor?.dataset.modulo || 'constancia-situacion-fiscal';

    let mostrarEstacion = contenedor?.dataset.mostrarEstacion !== 'false';

    /** Icono de descarga del PDF. */
    const columnaDescargar = {
        title: '<i class="ti ti-download fs-6 text-primary"></i>',
        data: null,
        className: 'align-middle text-center',
        orderable: false,
        searchable: false,
        width: '48px',
        render: function (v, t, row) {
            if (!puedeDescargar) return '';

            if (!row.existe_archivo) {
                return `<i class="ti ti-file-off fs-6 text-muted"
                            title="El archivo no se encuentra en el servidor"></i>`;
            }

            return `<a class="pointer" title="Descargar constancia"
                       href="/download?tipo=constancia-fiscal&file=${encodeURIComponent(row.archivo)}"
                       download>
                    <i class="ti ti-download fs-6 text-primary"></i>
                </a>`;
        }
    };

    /** Icono de eliminación. */
    const columnaEliminar = {
        title: '<i class="ti ti-trash fs-6 text-danger"></i>',
        data: null,
        className: 'align-middle text-center',
        orderable: false,
        searchable: false,
        width: '48px',
        render: function (v, t, row) {
            return `<a class="pointer text-danger cs-eliminar" data-id="${row.id}" title="Eliminar constancia">
                        <i class="ti ti-trash fs-6"></i>
                    </a>`;
        }
    };

    /**
     * Arma la lista de columnas según lo que corresponde mostrar.
     */
    function construirColumnas(conEstacion) {
        const columnas = [
            { title: '#', data: 'id', className: 'align-middle text-center', width: '96px', orderable: true, searchable: true }
        ];

        if (conEstacion) {
            columnas.push({
                title: 'Estacion/Departamento',
                data: 'estacion',
                className: 'align-middle text-center',
                orderable: false,
                searchable: false
            });
        }

        columnas.push({
            title: 'Fecha de actualizacion',
            data: 'fecha',
            className: 'align-middle text-center',
            orderable: true,
            searchable: true
        });

        columnas.push(columnaDescargar);

        if (puedeEliminar) {
            columnas.push(columnaEliminar);
        }

        return columnas;
    }

    let dt = null;

    function crearTabla() {
        return tabla.DataTable({
            processing: true,
            serverSide: false,
            autoWidth: false,
            stateSave: false,
            // CAMBIO AQUÍ: Ordenar siempre por la columna 0 (#) de forma descendente (el ID más alto / último arriba)
            order: [[0, 'desc']],
            lengthMenu: [10, 25, 50, 100],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json'
            },
            ajax: {
                url: '/constancia-situacion-fiscal/data',
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

    tabla.on('click', '.cs-eliminar', function () {
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('cs-eliminar', { detail: { id } }));
    });

    if (window.ModuleStationSelector) {
        ModuleStationSelector.dataTableMap[claveModulo] = '#tabla-constancia-situacion-fiscal';
        ModuleStationSelector.init(claveModulo);
    }

    window.addEventListener('load', () => {
        if (window.Alpine) {
            Alpine.initTree(document.getElementById('constancia-situacion-fiscal'));
        }
    });

});