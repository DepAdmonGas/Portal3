document.addEventListener('DOMContentLoaded', () => {

    const container =
        document.getElementById(
            'container'
        );

    if (!container) {
        return;
    }

    const idEstacion =
        Number(
            container.dataset.idestacion
        );


    if (!idEstacion) {

        console.error(
            'No se encontró el id de la estación.'
        );

        return;

    }
    $('#table-calibracion-tanques').DataTable({

        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [
            [0, 'desc']
        ],
        language: {

            url:
                '/assets/libs/datatables.net/js/es-ES.json'

        },
        ajax: {

            url:
                '/gestoria/calibracion-tanques/' +
                idEstacion +
                '/data',

            type:
                'GET',

            dataSrc: function (json) {

                return json.data ?? [];

            }

        },
        columns: [
            {
                data: 'id',
                width: '50px',
                className: 'text-center align-middle fw-semibold'
            },
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
                data:
                    null,

                width:
                    '1%',

                orderable:
                    false,

                searchable:
                    false,

                className:
                    'text-center align-middle td-small',

                render: function (
                    data,
                    type,
                    row
                ) {

                    return `
                        <div class="dropdown dropstart">

                            <a
                                href="javascript:void(0)"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i
                                    class="ti ti-dots-vertical fs-6">
                                </i>
                            </a>


                            <ul class="dropdown-menu">

                                <li>

                                    <a
                                        href="javascript:void(0)"
                                        class="dropdown-item pointer d-flex align-items-center gap-2"
                                        onclick="window.calibracionTanques.editar(${row.id})"
                                    >

                                        <i
                                            class="ti ti-pencil fs-5">
                                        </i>

                                        Editar

                                    </a>

                                </li>


                                <li>

                                    <a
                                        href="javascript:void(0)"
                                        class="dropdown-item pointer d-flex align-items-center gap-2 text-danger"
                                        onclick="window.calibracionTanques.eliminar(${row.id})"
                                    >

                                        <i
                                            class="ti ti-trash fs-5">
                                        </i>

                                        Eliminar

                                    </a>

                                </li>

                            </ul>

                        </div>
                    `;

                }
            }

        ]

    });

});