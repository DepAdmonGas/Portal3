document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-personal-estacion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/datatable-perfiles-personal',
            type: 'GET',
            dataSrc: function (json) {
            
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
        { data: 'id', name: 'id' },
        { data: 'nombre', name: 'nombre' },
        { data: 'puesto', name: 'puesto' },
        { data: 'telefono', name: 'telefono' },
        { data: 'email', name: 'email' },
        {
            data: null,
            name: 'porcentaje',
            render: function (data, type, row) {
                return `<span class="${row.color} fw-bold">${row.porcentaje}%</span>`;
            }
        },

        {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                               
                    const noDownload = permisos.descargar;
                  
                    return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">

                                <li>                            
                                    <a class="dropdown-item d-flex align-items-center gap-3"
                                     href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal/${row.id}">
                                        <i class="ti ti-eye"></i>Ficha personal
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    href="/sasisopa/competencia-personal-capacitacion-entrenamiento/ficha-personal-pdf/${row.id}">
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

    $("#table-personal-estacion tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


