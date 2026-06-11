document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-mantenimiento-quincenal').DataTable({

        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,

        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },

        ajax: {

            url:
            '/sasisopa/control-actividades-procesos/bitacora-mantenimiento-quincenal/datatable',

            type: 'GET',

            dataSrc(json){

                permisos = json.permisos;

                return json.data;
            }
        },

        columns: [

            {
            data: 'fecha',
             className:
                    'text-center align-middle',
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
                data: 'folio',
                className: 'text-center align-middle'
            },

            ...[1,2,3,4,5,6,7].map(i => ({

                data: `formato${i}`,

                className:
                    'text-center align-middle',

                render(data){

                    if(!data){
                        return `
                        <i class="ti ti-x text-danger fs-6"></i>
                        `;
                    }

                    return `
                    <a href="/uploads/${data}"
                       target="_blank">

                        <i class="ti ti-file-type-pdf text-success fs-6"></i>

                    </a>`;
                }
            })),

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
                                <a class="dropdown-item d-flex align-items-center gap-3 ${!noEditar ? 'disabled' : ''}" 
                                href="javascript:void(0)"
                                ${!noEditar ? '' : `@click='window.mantenimiento.openModalEditar(${JSON.stringify(row)})'`}>
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li>  
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3" 
                                    href="javascript:void(0)"
                                    @click='window.mantenimiento.eliminar(${row.id},${JSON.stringify(row.folio)})'>
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

});