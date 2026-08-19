document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-nivel-gobierno').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/gestoria/requisitos-legales/datatable-nivel-gobierno',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            
            { data: 'gobierno' },
            
            {
              data: null,
              width: '1%',
              orderable: false,
              searchable: false,
              className: 'text-center align-middle td-small',

              render: function (data, type, row) {
                  return `
                      <a
                      class="pointer" @click='requisitoLegal.eliminarNivelGobierno(${row.id})'
                      >
                          <i class="ti ti-trash fs-6 text-danger"></i>
                      </a>
                  `;
              }
          }
        ]
    });

    $("#table-nivel-gobierno tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
