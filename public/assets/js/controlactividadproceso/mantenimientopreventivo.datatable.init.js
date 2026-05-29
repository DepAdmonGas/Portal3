document.addEventListener('DOMContentLoaded', () => {

const year = new Date().getFullYear();

    table1 = $('#table-mantenimiento-preventivo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: true,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/mantenimiento-preventivo/datatable?year=' + year,
            type: 'GET',
            dataSrc: function (json) {
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'folio'
            }, 

             { data: 'detalle'
             },
            {
                data: 'fechacreacion',
                render: function (data, type) {
                    if (!data) return '';

                    const fecha = new Date(data);
                    const formateada = fecha.toLocaleDateString('es-MX', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });

                    if (type === 'display') return formateada;
                    if (type === 'filter') return formateada + ' ' + data;

                    return data;
                }
            },

            {
                data: 'horacreacion'
            }, 
            {   
            data: 'estado', className: 'text-center',
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
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data, type, row) {

                const bloqueado = Number(row.estado) === 0;

                return `
                    <div class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>

                            <ul class="dropdown-menu">

                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3"
                                    href="javascript:void(0)"
                                    @click='window.mantenimientoPreventivo.openModalDetalle(${JSON.stringify(row)})'>
                                        <i class="ti ti-eye"></i>Detalle
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3
                                    ${bloqueado ? 'disabled text-muted' : ''}"
                                    href="javascript:void(0)"
                                    ${bloqueado ? '' : `@click='window.mantenimientoPreventivo.evidencia(${JSON.stringify(row)})'`}>
                                        <i class="ti ti-camera"></i>Evidencia
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3
                                    ${bloqueado ? 'disabled text-muted' : ''}"
                                    ${bloqueado ? '' : `href="/sasisopa/control-actividades-procesos/mantenimiento-preventivo/pdf?id=${row.id}"`}>
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

    $("#table-mantenimiento-preventivo tbody").on("click", "tr", function () {

    const data = table1.row(this).data();

    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});