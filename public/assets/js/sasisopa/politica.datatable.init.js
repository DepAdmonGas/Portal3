document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-lista-comprobacion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/politica/datatable-lista-comprobacion',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
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

                    const noEdit = permisos.editar;
                    const noDelete = permisos.eliminar;
                    const noDownload = permisos.descargar;
                    

                    return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled' : ''}" 
                                    data-id="${row.id}" 
                                    @click="$dispatch('open-edit', ${row.id})">
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    href="/sasisopa/politica/lista-comprobacion/pdf/${row.id}" target="_blank">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/sasisopa/politica/lista-comprobacion/delete",
                                        id: ${row.id},
                                        name: "${row.id}",
                                        table: "#table-lista-comprobacion"
                                    });
                                    }'
                                    `}>
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    `;
                }
            }
        ]
    });

     $("#table-lista-comprobacion tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });


});


