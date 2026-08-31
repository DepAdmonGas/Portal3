let table1;
document.addEventListener('DOMContentLoaded', () => {

    if (!document.getElementById('sgm-content')) {
        return;
    }

    let permisos = {};
    let year = new Date().getFullYear();

    table1 = $('#table-capacitacion-externa').DataTable({

        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,

        order: [[3, 'desc']],

        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },

        ajax: {
            url: '/sgm/gestion-recursos/programa-capacitacion-externa/datatable/' + year,
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos;
                return json.data;
            }
        },

        columns: [

            {
                data: null,
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: (data, type, row, meta) => meta.row + 1
            },

            {
                data: 'curso'
            },

            {
                data: 'tipo',
                className: 'text-center'
            },

            {
            data: 'fecha_programada',
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
                data: 'duracion',
                className: 'text-center'
            },

            {
                data: 'instructor',
                className: 'text-center'
            },

            {
            data: 'fecha_real',
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
              data: 'personal',

              render(data){

                  if (!Array.isArray(data) || data.length === 0) {
                      return '';
                  }

                  return data
                      .map(nombre => `<small>${nombre}</small>`)
                      .join(', ');

              }
          },

           {
              data: 'evidencias',
              className: 'text-center',

              render(data){

                  if (!Array.isArray(data) || data.length === 0) {
                      return '';
                  }

                  return data.map(e => `
                      <small>
                          <a href="/${e.url}" target="_blank">
                              ${e.nombre}
                          </a>
                      </small>
                  `).join(', ');

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
                    const noDownload = permisos.descargar;
                    

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3" @click='capacitacion.editar(${row.id})'>
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}" 
                                    ${!noDelete ? '' : `@click='capacitacion.eliminar(${row.id})'`}>
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

     $("#table-capacitacion-externa tbody")
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