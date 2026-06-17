document.addEventListener('DOMContentLoaded', () => {

    const idSasisopa = document
    .getElementById('container')
    .dataset.idsasisopa;

    table1 = $('#table-programa-simulacro').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/preparacion-emergencias/simulacro/datatable',
            type: 'GET',
            dataSrc: function (json) {
                //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
            }
        },
        columns: [

            { data: 'nombre_simulacro' },

            { data: 'periodicidad' },
          
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
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="javascript:void(0)"
                     @click='window.preparacionEmergencias.abrirPersonal(${row.id})'>
                        <i class="ti ti-plus text-info fs-7"></i>
                    </a>
                `;
            }
        },

        { data: 'personal' },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a @click='window.preparacionEmergencias.abrirResumen(${row.id})'>
                        <i class="ti ti-edit text-primary fs-7"></i>
                    </a>
                `;
            }
        },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a>
                        <i class="ti ${
                            row.resumen
                                ? 'ti-check text-success'
                                : 'ti-x'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

        
         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.016a.doc">
                        <i class="ti ti-file-download text-info fs-7"></i>
                    </a>
                `;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a @click='window.preparacionEmergencias.abrirEvaluacion(${row.id})'>
                        <i class="ti ti-file-upload text-success fs-7"></i>
                    </a>
                `;
            }
        },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return row.evaluacion
                    ? `
                        <a
                            href="${row.evaluacion}"
                            target="_blank">

                            <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                        </a>
                    `
                    : `
                        <i class="ti ti-x fs-7"></i>
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
                    
                    const noEdit = permisos.editar;
                    const noDelete = permisos.eliminar;

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled' : ''}"
                                    @click='window.preparacionEmergencias.editarPrograma(${JSON.stringify(row)})'>
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled' : ''}"
                                     ${!noDelete ? '' : `
                                    @click='window.preparacionEmergencias.eliminarPrograma(${row.id}, ${JSON.stringify(row.nombre_simulacro)})'
                                    `}>
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

     $("#table-programa-simulacro tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
