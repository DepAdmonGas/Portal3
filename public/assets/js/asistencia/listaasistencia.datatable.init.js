document.addEventListener('DOMContentLoaded', () => {

    const idElemento = document
    .getElementById('container')
    .dataset.elemento;

    let permisos = {};

    table2 = $('#table-lista-asistencia').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/datatable-lista-asistencia/elemento/' + idElemento,
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
            {data: 'estado', width: '100px', className: 'text-center align-middle',
            render: function (data) {
            const estatus = Number(data);

            let clase = '';
            let texto = '';

            switch (estatus) {

            case 0:
            clase = 'danger';
            texto = 'Pendiente';
            break;

            case 1:
            clase = 'success';
            texto = 'Finalizado';
            break;

            }

            return `<span class="badge rounded-pill bg-${clase}">${texto}</span>`;
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
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" class="text-muted" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled' : ''}"
                                    ${!noEdit ? '' : `
                                    @click='async () => {
                                    const res = await goTo("/lista-asistencia/${row.id}");
                                    }'
                                    `}>
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    href="/lista-asistencia/pdf/${row.id}" target="_blank">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/lista-asistencia/delete",
                                        id: ${row.id},
                                        name: "${row.id}",
                                        table: "#table-lista-asistencia"
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

    $("#table-lista-asistencia tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
