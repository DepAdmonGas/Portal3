document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-jarra-patron').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-jarra-patron/datatable',
            type: 'GET',
            dataSrc: function (json) {
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'marca'
            },

            {
                data: 'no_serie'
            },

            {
                data: 'capacidad'
            },

            {
                data: 'material'
            },
            
            {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data,type,row){

                    const noDelete = permisos.eliminar;
                    const noEditar = permisos.editar;
                    const noSerie = JSON.stringify(row.no_serie ?? '');

                        return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                              
                                <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noEditar ? 'disabled' : ''}" 
                                href="javascript:void(0)"
                                ${!noEditar ? '' : `@click='window.jarraPatron.openModalEditar(${JSON.stringify(row)})'`}>
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li>  
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}" 
                                    href="javascript:void(0)"
                                    ${!noDelete ? '' : `@click='window.jarraPatron.eliminar(${row.id},${noSerie})'`}>
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

    $("#table-jarra-patron tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});