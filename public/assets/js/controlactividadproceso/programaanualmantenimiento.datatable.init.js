document.addEventListener('DOMContentLoaded', () => {

     const idprograma = document
    .getElementById('container')
    .dataset.idprograma;

     const columnMes = mes => ({

            data: mes,
            orderable: false,
            searchable: false,
            className: 'align-middle text-center',

            createdCell: function(td, cellData){

                $(td).css({
                    'background-color': cellData.background,
                    'padding': '0px',
                    'margin': '0px'
                });

                $(td).addClass(cellData.textColor);
            },

            render: function(data){

                return data.texto;
            }
        });

    table1 = $('#table-programa-anual').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/datatable-programa-anual-mantenimiento/' + idprograma,
            type: 'GET',
             dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'id_mantenimiento'
            },

            {
                data: 'equipo'
            },

            ...[
                    'enero',
                    'febrero',
                    'marzo',
                    'abril',
                    'mayo',
                    'junio',
                    'julio',
                    'agosto',
                    'septiembre',
                    'octubre',
                    'noviembre',
                    'diciembre'
                ].map(columnMes),
            

            {
                data: null,
                orderable: false,
                searchable: false,
                className:
                    'text-center align-middle',
                render: function(data,type,row){

                    const noDelete = permisos.eliminar;
                    const disableEdit = (row.periodicidad == 'Semanal' || !permisos.editar);

                        return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                              
                                <li>
                                <a class="dropdown-item d-flex align-items-center gap-3 ${disableEdit ? 'disabled' : ''}"
                                ${disableEdit ? '' : `@click='window.programaMantenimiento.editar(${row.id})'`}>
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li>  
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled' : ''}"
                                    ${!noDelete ? '' : `@click='window.programaMantenimiento.eliminar(${row.id})'`}>
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

         $("#table-programa-anual tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});