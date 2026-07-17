document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    table1 = $('#table-seguimiento-objetivos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/datatable',
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
            {
            data: 'fecha',
            render: function (data, type) {

                if (!data || data === 'S/I') return 'S/I';

                const partes = data.split('-');

                if (partes.length !== 3) return 'S/I';

                const fecha = new Date(partes[0], partes[1] - 1, partes[2]);

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
            data: 'hora',
            render: function (data, type) {

                if (!data) return '';

                if (type !== 'display') {
                    return data;
                }

                const hora = new Date('1970-01-01T' + data);

                return hora.toLocaleTimeString('es-MX', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            }
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
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                                    ${!noEdit ? '' : `
                                    @click='async () => {
                                    const res = await goTo("/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/${row.id}");
                                    }'
                                    `}>
                                        <i class="ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    href="/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/pdf/${row.id}" target="_blank">
                                        <i class="ti ti-download"></i>Descargar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='async () => {
                                    const res = await deleteAction({
                                        url: "/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/delete",
                                        id: ${row.id},
                                        name: "${row.id}",
                                        table: "#table-seguimiento-objetivos"
                                    });
                                    }'
                                    `}>
                                        <i class="ti ti-trash"></i>Eliminar
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

    $("#table-seguimiento-objetivos tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
