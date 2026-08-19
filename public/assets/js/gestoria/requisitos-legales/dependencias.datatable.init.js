document.addEventListener('DOMContentLoaded', () => {

    table3 = $('#table-dependencias').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/gestoria/requisitos-legales/datatable-dependencias',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            
            { data: 'dependencia' },
            
            {
              data: null,
              width: '1%',
              orderable: false,
              searchable: false,
              className: 'text-center align-middle td-small',

              render: function (data, type, row) {
                  return `
                      <a
                      class="pointer" @click='requisitoLegal.eliminarDependencia(${row.id})'
                      >
                          <i class="ti ti-trash fs-6 text-danger"></i>
                      </a>
                  `;
              }
          }
        ]
    });

    $("#table-dependencias tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table3.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
