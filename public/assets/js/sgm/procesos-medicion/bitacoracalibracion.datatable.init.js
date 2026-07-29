document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};

    table1 = $('#table-bitacora-calibracion-equipos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sgm/procesos-medicion/bitacora-calibracion-equipos/datatable',
            type: 'GET',
            dataSrc: function (json) {

                //guardas permisos globalmente
                permisos = json.permisos;
                return json.data;
            }
        },
        columns: [
            { data: 'equipo'},
            { data: 'periodicidad'},

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
                    <div class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                                    ${!noEdit ? '' : `
                                    @click='goTo("/sgm/procesos-medicion/bitacora-calibracion-equipos/${row.id}")'
                                    `}>
                                        <i class="ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!data.acciones.detalle ? 'disabled text-muted' : ''}"
                                    ${!data.acciones.detalle ? '' : `@click="bitacoraCalibracion.abrir(${row.id})"`}
                                    >
                                        <i class="ti ti-eye"></i>Detalle
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!data.acciones.descargar ? 'disabled text-muted' : ''}"
                                    ${!data.acciones.descargar ? '' : `
                                      href="/sgm/procesos-medicion/bitacora-calibracion-equipos/pdf/${row.id}"`} download>
                                        <i class="ti ti-download"></i>Descargar
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

    $("#table-bitacora-calibracion-equipos tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
