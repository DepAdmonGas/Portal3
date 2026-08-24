document.addEventListener('DOMContentLoaded', () => {

    table4 = $('#table-requisito-legal').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/gestoria/requisitos-legales/datatable-requisito-legal',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            
            { data: 'nivel_gobierno' },
            { data: 'mun_alc_est' },
            { data: 'dependencia' },
            { data: 'permiso' },
            { data: 'responsable' },
            
            {
              data: null,
              width: '1%',
              orderable: false,
              searchable: false,
              className: 'text-center align-middle td-small',

              render: function (data, type, row) {
                  
                  return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                     <a class="dropdown-item d-flex align-items-center pointer gap-3" 
                                     @click='requisitoLegal.editarRequisitoLegal(${JSON.stringify(row)})'>
                                          <i class="ti ti-edit"></i> Editar
                                      </a>
                                </li>
                                 <li>
                                    <a
                                      class="dropdown-item d-flex align-items-center pointer gap-3" 
                                      @click='requisitoLegal.eliminarRequisitoLegal(${row.id})'
                                      >
                                          <i class="ti ti-trash"></i> Eliminar
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

    $("#table-requisito-legal tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table4.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
