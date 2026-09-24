document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-extintores').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/extintores/datatable',
            type: 'GET',
            dataSrc: function (json) {
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'no_extintor', className: 'text-center align-middle',
            },

            {
                data: 'ubicacion', className: 'text-start align-middle',
            },

             {
            data: 'ultima_recarga', className: 'text-center align-middle',
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
                data: 'tipo_extintor', className: 'text-center align-middle',
            },

            {
                data: 'peso_kg', className: 'text-center align-middle',
            },
            
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function(data,type,row){

                    const noDelete = permisos.eliminar;
                    const noEditar = permisos.editar;
                    const nomEquipo = JSON.stringify(row.equipo ?? '');

                        return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a class="pointer" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                              
                                <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noEditar ? 'disabled' : ''}"
                                ${!noEditar ? '' : `@click='window.extintores.openModalEditar(${JSON.stringify(row)})'`}>
                                <i class="ti ti-edit"></i>Editar
                                </a>    
                               </li>  
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `@click='window.extintores.eliminar(${row.id},${nomEquipo})'`}>
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

    $("#table-extintores tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});