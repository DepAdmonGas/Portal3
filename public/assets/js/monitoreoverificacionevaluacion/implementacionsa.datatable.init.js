document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-implementacionsa').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/monitoreo-verificacion-evaluacion/implementacion-sa/datatable',
            type: 'GET',
            dataSrc: function (json) {
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'numero'
            },

            {
                data: 'responsable'
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
                data: 'preguntas'
            },

            {
                data: 'si',
                className: 'text-center align-middle',
            },

            {
                data: 'no',
                className: 'text-center align-middle',
            },

            {
                data: 'resultado',
                className: 'text-center align-middle',
            },

           
            
            {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data,type,row){

                    const noEditar = permisos.editar

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
                                @click='window.implementacionsa.abrirDetalle(${row.id})'>
                                <i class="ti ti-eye"></i>Detalle
                                </a>    
                               </li>  
                              
                                <li>
                                <a class="dropdown-item d-flex align-items-center gap-3" ${!noEditar ? 'disabled' : ''} 
                                href="javascript:void(0)"
                                @click='window.implementacionsa.abrirEditar(${row.id})'>
                                <i class="ti ti-edit"></i>Editar
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

    $("#table-implementacionsa tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});