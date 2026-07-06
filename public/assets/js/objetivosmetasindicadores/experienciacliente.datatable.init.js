document.addEventListener('DOMContentLoaded', () => {

    const idSasisopa = document
    .getElementById('container')
    .dataset.idsasisopa;

    table1 = $('#table-experiencia-cliente').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/objetivos-metas-indicadores/datatable-experiencia-cliente',
            type: 'GET',
            dataSrc: function (json) {
                //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
            }
        },
        columns: [
            { data: 'num', width: '60px', className: 'text-center' },
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
        { data: 'encuestados', className: 'text-center' },
        { data: 'excelente_total', className: 'text-center text-primary' },
        { 
            data: 'excelente_porcentaje',
            className: 'text-center text-primary',
            render: function(data, type, row) {
                if (type === 'display') {
                return data + ' %';
                }
                return data;
            }
            },
            { data: 'bueno_total', className: 'text-center text-success' },
            { 
                data: 'bueno_porcentaje',
                className: 'text-center text-success',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data + ' %';
                    }
                    return data;
                }
            },
            { data: 'regular_total', className: 'text-center text-warning' },
            { 
                data: 'regular_porcentaje',
                className: 'text-center text-warning',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data + ' %';
                    }
                    return data;
                }
            },
            { data: 'malo_total', className: 'text-center text-danger' },
            { 
                data: 'malo_porcentaje',
                className: 'text-center text-danger',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data + ' %';
                    }
                    return data;
                }
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
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                             <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3"
                                     @click="openView(${row.id})">
                                        <i class="fs-4 ti ti-eye"></i>Detalle
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                                    @click="openEditar(${row.id})">
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                    url: "/sasisopa/objetivos-metas-indicadores/delete-experiencia-cliente",
                                    id: ${row.id},
                                    name: "${row.id}",
                                    table: "#table-experiencia-cliente"
                                    });
                                    
                                        if (res && res.success) {
                                            await this.getExperienciaCliente();
                                        }

                                    }'
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

     $("#table-experiencia-cliente tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
