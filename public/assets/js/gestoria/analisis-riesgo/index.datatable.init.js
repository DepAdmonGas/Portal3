document.addEventListener('DOMContentLoaded', () => {

  const container = document.getElementById('container');
    const idEstacion = Number(container.dataset.idestacion);

    table1 = $('#table-analisis-riesgo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/gestoria/analisis-riesgo/' + idEstacion + '/data',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            
            { data: 'numero', class: 'text-center'},
            { data: 'fecha_formateada', class: 'text-center'},
            { data: 'descripcion', class: 'text-center'},
            
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                    const disabled = row.estatus === 0
                        ? 'disabled opacity-50 pointer-events-none'
                        : '';

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                            <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    onclick="window.analisisRiesgo.openAnexos(${row.id})">
                                        <i class="ti ti-file-description"></i> Agregar Anexos
                                    </a>
                                </li>

                            <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3" 
                                    @click.prevent="download('analisis-riesgo', '${row.documento}')">
                                        <i class="ti ti-file-type-pdf"></i> Descargar
                                    </a>
                                </li>

                                <li>
                                  <a
                                      class="dropdown-item pointer d-flex align-items-center gap-3"
                                      onclick="window.analisisRiesgo.openEditar(${row.id})">

                                      <i class="ti ti-pencil"></i>
                                      Editar

                                  </a>
                              </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                    @click='window.analisisRiesgo.eliminar(${row.id})'>
                                        <i class="ti ti-trash"></i> Eliminar
                                    </a>
                                </li>
                                                             
                            </ul>
                        </div>
                    `;
                }
            }
        ]
    });

    $("#table-analisis-riesgo tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
