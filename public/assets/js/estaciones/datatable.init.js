document.addEventListener(
    'DOMContentLoaded',
    () => {

        $('#table-estaciones')
            .DataTable({

                processing: true,

                serverSide: false,

                autoWidth: false,

                stateSave: true,

                responsive: false,

                order: [
                    [0, 'desc']
                ],

                language: {
                    url:
                        '/assets/libs/datatables.net/js/es-ES.json'
                },

                ajax: {

                    url:
                        '/estaciones/datatable',

                    type:
                        'GET',

                    dataSrc:
                        function (json) {

                            return json.data || [];

                        }

                },

                columns: [

                    {
                        data: 'id',

                        width: '50px',

                        className:
                            'text-center'
                    },


                    {
                        data: 'nombre'
                    },


                    {
                        data: 'permisocre'
                    },


                    {
                        data: 'razonsocial'
                    },


                    {
                        data: 'rfc'
                    },


                    {
                        data: 'estatus',

                        width: '90px',

                        className:
                            'text-center',

                        render:
                            function (data) {

                                return Number(data) === 1

                                    ? `
                                        <span class="badge bg-success-subtle text-success">
                                            Activo
                                        </span>
                                      `

                                    : `
                                        <span class="badge bg-danger-subtle text-danger">
                                            Cancelado
                                        </span>
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

                      const nombre = $('<div>')
                          .text(row.nombre || '')
                          .html();

                      return `
                          <div class="dropdown dropstart">

                              <a
                                  href="javascript:void(0)"
                                  data-bs-toggle="dropdown"
                                  aria-expanded="false"
                              >
                                  <i class="ti ti-dots-vertical fs-6"></i>
                              </a>

                              <ul class="dropdown-menu">

                                  <li>
                                      <a
                                          href="/usuarios?idEstacion=${row.id}"
                                          class="dropdown-item pointer d-flex align-items-center gap-3"
                                      >
                                          <i class="fs-4 ti ti-users"></i>
                                          Usuarios
                                      </a>
                                  </li>

                                  <li>
                                      <a
                                          href="javascript:void(0)"
                                          class="dropdown-item pointer d-flex align-items-center gap-3 btn-edit"
                                          data-id="${row.id}"
                                      >
                                          <i class="fs-4 ti ti-edit"></i>
                                          Editar
                                      </a>
                                  </li>

                                  ${
                                      Number(row.estatus) === 1
                                          ? `
                                              <li>
                                                  <a
                                                      href="javascript:void(0)"
                                                      class="dropdown-item pointer d-flex align-items-center gap-3 btn-delete"
                                                      data-id="${row.id}"
                                                      data-nombre="${nombre}"
                                                  >
                                                      <i class="fs-4 ti ti-trash"></i>
                                                      Eliminar
                                                  </a>
                                              </li>
                                          `
                                          : ''
                                  }

                              </ul>

                          </div>
                      `;
                  }
              }

                ]

            });

    }
);