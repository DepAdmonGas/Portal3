document.addEventListener('DOMContentLoaded', () => {

    table2 = $('#table-quejas-sugerencia').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/comunicacion-participacion-consulta/datatable-quejas-sugerencias',
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos;
                return json.data;
            },
            error: function (xhr) {
                
            }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
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

                    if (type === 'display' || type === 'filter') {
                        return fechaFormateada;
                    }

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

                    const noDelete = permisos.eliminar;
                    const noDownload = permisos.descargar;
                    

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                 <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    href="/sasisopa/comunicacion-participacion-consulta/pdf-quejas-sugerencias/${row.id}">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='window.comunicacionParticipacionConsulta.eliminarQS(${row.id}, ${row.id})'
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

      $("#table-quejas-sugerencia tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
