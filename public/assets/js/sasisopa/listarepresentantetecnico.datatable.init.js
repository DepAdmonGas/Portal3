document.addEventListener('DOMContentLoaded', () => {

    const idSasisopa = document
    .getElementById('container')
    .dataset.idsasisopa;

    table1 = $('#table-lista-representante-tecnico').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[2, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/funciones-responsabilidades-autoridad/datatable-lista-representante-tecnico',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
            }
        },
        columns: [
           {
                data: null,
                width: '60px',
                className: 'text-center',
                render: function (data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            { data: 'nom_representante' },
            {
            data: 'fecha',
            render: function (data, type) {

                if (!data) return '';

                const fecha = new Date(data);

                const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                // Para búsqueda y display usar el texto formateado
                if (type === 'display' || type === 'filter') {
                    return fechaFormateada;
                }

                // Para ordenamiento usar la fecha real
                return data;
            },
            orderable: true,
            searchable: true
        },
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                    
                    const disabled = row.estado === 0;
                    const noEdit = !permisos.editar || disabled;
                    const noDelete = !permisos.eliminar || disabled;
                    const noDownload = !permisos.descargar || disabled;

                    const archivo = row.archivo.split('/').pop();

                    
                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${noDownload ? 'disabled' : ''}"
                                    ${noDownload ? '' : `
                                    @click="download('representante-tecnico','${archivo}')"
                                    `}>
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3  ${noDelete ? 'disabled' : ''}"
                                    ${noDelete ? '' : `
                                    @click='window.representanteTecnico.eliminar(${row.id})'
                                    `}>
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    `;
                }
            }
        ]
    });

    $("#table-lista-representante-tecnico tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
