document.addEventListener('DOMContentLoaded', () => {

    $('#table-estaciones').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/estaciones/datatable',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [
            { data: 'id', width: '50px', className: 'text-center' },
            { data: 'nombre' },
            { data: 'permisocre' },
            { data: 'razonsocial' },
            { data: 'rfc' },
            {
                data: 'estatus',
                width: '80px',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="mb-1 badge text-bg-success">Activo</span>'
                        : '<span class="mb-1 badge text-bg-danger">Cancelado</span>';
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
                      <a
                          href="javascript:void(0)"
                          class="pointer btn-menu-estacion"
                          data-estacion-id="${row.id}"
                          data-estacion-nombre="${row.nombre}"
                          title="Opciones de estación"
                      >
                          <i class="ti ti-dots-vertical fs-6"></i>
                      </a>
                  `;
              }
          }
        ]
    });

});
