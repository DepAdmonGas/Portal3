document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-bitacora-calibracion-equipos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos/datatable',
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
                data: 'equipo'
            },

            {
                data: 'resultado_estado',
                className: 'text-center align-middle',
            },

             {
                data: 'estado',
                className: 'text-center align-middle',
            },

           
            
            {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data,type,row){

                    const noEditar = permisos.editar;
                    const noDetalle = row.detalle;

                return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">

                            <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3  ${!noDetalle ? 'text-muted' : ''}"
                                ${!noDetalle ? '' : ` 
                                href="javascript:void(0)"
                                @click='window.bitacoraCalibracionEquipos.abrirDetalle(${row.id})'`}>
                                <i class="ti ti-eye"></i>Detalle
                                </a>    
                               </li>  
                              
                                <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3" ${!noEditar ? 'disabled' : ''} 
                                href="${row.location}">
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li> 
                               
                                <li>
                                    <a
                                        class="dropdown-item pointer d-flex align-items-center gap-3"
                                        href="javascript:void(0)"
                                        ${`@click='window.bitacoraCalibracionEquipos.abrirModalResultados(${JSON.stringify(row)})'`}>

                                        <i class="ti ti-folder-up"></i>
                                        Resultados

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