document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-mantenimiento-correctivo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/mantenimiento-correctivo/datatable',
            type: 'GET',
            dataSrc: function (json) {
                console.log(json.data);
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'folio'
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
                data: 'nombre_equipo'
            },

            {
                data: 'descripcion_hallazgo'
            },
            
            {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data,type,row){

                    const noEditar = permisos.editar;

                        return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                              
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-3" 
                                href="javascript:void(0)"
                                @click='window.mantenimientoCorrectivo.openModalDetalle(${JSON.stringify(row)})'>
                                <i class="ti ti-eye"></i>Detalle
                                </a>    
                               </li>  
                                <li>
                                <a class="dropdown-item d-flex align-items-center gap-3 ${!noEditar ? 'disabled' : ''}" 
                                href="javascript:void(0)"
                                ${!noEditar ? '' : `@click='window.mantenimientoCorrectivo.openModalEditar(${JSON.stringify(row)})'`}>
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li>  
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3" 
                                    href="javascript:void(0)"
                                    @click='window.mantenimientoCorrectivo.evidencia(${JSON.stringify(row)})'>
                                        <i class="ti ti-camera"></i>Evidencia
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

    $("#table-mantenimiento-correctivo tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});