// ============================================================
// Comunicados — Inicialización de la tabla.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const tabla = $('#tabla-comunicados');

    if (!tabla.length) return;

    const contenedor = document.getElementById('comunicados');
    const puedeEditar = contenedor?.dataset.puedeEditar === 'true';
    const puedeEliminar = contenedor?.dataset.puedeEliminar === 'true';
    const puedeDescargar = contenedor?.dataset.puedeDescargar === 'true';
    const puedeAcusar = contenedor?.dataset.puedeAcusar === 'true';
    const tipoDescarga = contenedor?.dataset.tipoDescarga || 'comunicados';

    /** Dropdown de acciones de la fila (mismo patrón que el resto del DO). */
    function renderAcciones(row) {
        const clase = (activo) => activo ? '' : ' disabled';

        let html = '<div class="dropdown dropstart">'
                 + '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>'
                 + '<div class="dropdown-menu dropdown-menu-end">';

        html += '<a class="dropdown-item pointer btn-com-detalle" data-id="' + row.id + '">'
              + '<i class="ti ti-eye me-1"></i> Detalle</a>';

        if (puedeDescargar) {
            if (row.existe_archivo) {
                html += '<a class="dropdown-item pointer btn-com-descargar" href="/download?tipo='
                      + encodeURIComponent(tipoDescarga)
                      + '&file=' + encodeURIComponent(row.archivo) + '" download>'
                      + '<i class="ti ti-download me-1"></i> Descargar</a>';
            } else {
                html += '<a class="dropdown-item disabled" aria-disabled="true">'
                      + '<i class="ti ti-file-off me-1"></i> Descargar</a>';
            }
        }

        if (puedeAcusar && !row.acusado) {
            html += '<a class="dropdown-item pointer btn-com-acuse" data-id="' + row.id + '"'
                  + ' data-titulo="' + String(row.titulo || '').replace(/"/g, '&quot;') + '">'
                  + '<i class="ti ti-check me-1"></i> De Acuerdo</a>';
        }

        html += '<a class="dropdown-item pointer btn-com-editar' + clase(puedeEditar) + '"'
              + ' data-id="' + row.id + '"'
              + (puedeEditar ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-pencil me-1"></i> Editar</a>';

        html += '<a class="dropdown-item pointer btn-com-eliminar' + clase(puedeEliminar) + '"'
              + ' data-id="' + row.id + '" data-titulo="' + String(row.titulo || '').replace(/"/g, '&quot;') + '"'
              + (puedeEliminar ? '' : ' aria-disabled="true" tabindex="-1"') + '>'
              + '<i class="ti ti-trash me-1"></i> Eliminar</a>';

        html += '</div></div>';

        return html;
    }

    const columnas = [
        {
            title: '#',
            data: 'id',
            className: 'align-middle text-center',
            width: '80px',
            orderable: false,
            searchable: true
        },
        {
            title: 'Fecha',
            data: 'fecha_fmt',
            className: 'align-middle text-center',
            orderable: false,
            searchable: true
        },
        {
            title: 'Título',
            data: 'titulo',
            className: 'align-middle text-start',
            orderable: false,
            searchable: true
        }
    ];

    // La columna de acuse sólo aplica al personal que confirma el comunicado.
    if (puedeAcusar) {
        columnas.push({
            title: 'Acuse',
            width: '100px',
            data: 'acusado',
            className: 'align-middle text-center',
            orderable: false,
            searchable: false,
            render: function (v) {
                return v
                    ? '<span class="badge bg-success">De Acuerdo</span>'
                    : '<span class="badge bg-danger">Pendiente</span>';
            }
        });
    }

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

    const dt = tabla.DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [],
        lengthMenu: [10, 25, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/comunicados/data',
            type: 'GET',
            dataSrc: function (json) {
                return json.data || [];
            }
        },
        columns: columnas
    });

    // ---- Detalle ----
    tabla.off('click', '.btn-com-detalle').on('click', '.btn-com-detalle', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('comunicados:detalle', { detail: { id } }));
    });

    // ---- Editar ----
    tabla.off('click', '.btn-com-editar').on('click', '.btn-com-editar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        document.dispatchEvent(new CustomEvent('comunicados:editar', { detail: { id } }));
    });

    // ---- Acuse ----
    tabla.off('click', '.btn-com-acuse').on('click', '.btn-com-acuse', function (e) {
        e.preventDefault();
        const id = parseInt($(this).data('id'), 10);
        const titulo = this.dataset.titulo || '';
        document.dispatchEvent(new CustomEvent('comunicados:acuse', { detail: { id, titulo } }));
    });

    // ---- Eliminar ----
    tabla.off('click', '.btn-com-eliminar').on('click', '.btn-com-eliminar', function (e) {
        e.preventDefault();
        if ($(this).hasClass('disabled')) return;
        const id = parseInt($(this).data('id'), 10);
        const titulo = this.dataset.titulo || '';
        document.dispatchEvent(new CustomEvent('comunicados:eliminar', { detail: { id, titulo } }));
    });

});
