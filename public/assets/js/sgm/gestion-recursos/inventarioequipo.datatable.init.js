let table1;
document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-inventario-equipo').DataTable({

        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,

        order: [[3, 'desc']],

        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },

        ajax: {
            url: '/sgm/gestion-recursos/inventario-equipo/datatable',
            type: 'GET',
            dataSrc: function (json) {
                return json.data;
            }
        },

        columns: [

            {
                data: null,
                className: 'text-center',
                searchable: false,
                render: (data, type, row, meta) => meta.row + 1
            },

            {
                data: 'nombre'
            },

            {
                data: 'identificacion',
                className: 'text-center'
            },

            {
                data: 'funcion',
                className: 'text-center'
            },

            {
            data: 'fecha_instalacion',
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
            data: null,
            width: '1%',
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a class="pointer" @click="inventario.openManuales(${row.id})">
                        <i class="ti ti-archive fs-7"></i>
                    </a>
                `;
            }
        },
    
        {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                    

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 pointer" @click='inventario.openEditar(${row.id})'>
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 pointer" @click="inventario.eliminar(${row.id})">
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    `;
                }
            }

        ]

    });

     $("#table-inventario-equipo tbody")
    .on("click","tr",function(){

        if($(this).hasClass("selected")){

            $(this).removeClass("selected");

        }else{

            table1
            .$("tr.selected")
            .removeClass("selected");

            $(this)
            .addClass("selected");

        }

    });

});