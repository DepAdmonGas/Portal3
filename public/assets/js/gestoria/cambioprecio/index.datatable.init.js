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


    $('#table-cambio-precio').DataTable({

        processing: true,

        serverSide: false,

        autoWidth: false,

        stateSave: true,

        order: [
            [1, 'desc']
        ],

        language: {

            url:
                '/assets/libs/datatables.net/js/es-ES.json'

        },

        ajax: {

            url:
                '/gestoria/cambio-precio/' +
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
                data:
                    'id',

                width:
                    '50px',

                className:
                    'text-center align-middle fw-semibold'
            },
            {
                data:
                    'fechacreacion',

                className:
                    'text-center align-middle',

                defaultContent:
                    '',

                render: function (
                    data,
                    type,
                    row
                ) {

                    if (
                        type === 'sort' ||
                        type === 'type'
                    ) {

                        return data ?? '';

                    }
                    return row
                        .fechacreacion_formateada
                        ?? '';

                }
            },

            {
                data:
                    'fecha',

                className:
                    'text-center align-middle',

                defaultContent:
                    '',

                render: function (
                    data,
                    type,
                    row
                ) {

                    if (
                        type === 'sort' ||
                        type === 'type'
                    ) {

                        return data ?? '';

                    }


                    return row
                        .fecha_formateada
                        ?? '';

                }
            },

            {
                data:
                    'hora',

                className:
                    'text-center align-middle',

                defaultContent:
                    ''
            },

            {
                data:
                    'gsuper',

                className:
                    'text-center align-middle text-success fw-semibold',

                render: function (
                    data,
                    type
                ) {

                    if (
                        data === null ||
                        data === ''
                    ) {

                        return '';

                    }


                    if (
                        type === 'sort' ||
                        type === 'type'
                    ) {

                        return Number(data);

                    }


                    return Number(data)
                        .toLocaleString(
                            'es-MX',
                            {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }
                        );

                }
            },

            {
                data:
                    'gpremium',

                className:
                    'text-center align-middle text-danger fw-semibold',

                render: function (
                    data,
                    type
                ) {

                    if (
                        data === null ||
                        data === ''
                    ) {

                        return '';

                    }


                    if (
                        type === 'sort' ||
                        type === 'type'
                    ) {

                        return Number(data);

                    }


                    return Number(data)
                        .toLocaleString(
                            'es-MX',
                            {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }
                        );

                }
            },

            {
                data:
                    'gdiesel',

                className:
                    'text-center align-middle fw-semibold',

                render: function (
                    data,
                    type
                ) {

                    if (
                        data === null ||
                        data === ''
                    ) {

                        return '';

                    }


                    if (
                        type === 'sort' ||
                        type === 'type'
                    ) {

                        return Number(data);

                    }


                    return Number(data)
                        .toLocaleString(
                            'es-MX',
                            {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }
                        );

                }
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

                    if (
                        Number(
                            row.estado
                        ) === 0
                    ) {

                        return `
                            <a
                                href="javascript:void(0)"
                                class="pointer"
                                onclick="window.cambioPrecio.actualiza(${row.id})"
                                title="Pendiente en actualizar"
                            >
                                <i
                                    class="ti ti-refresh fs-7 text-danger">
                                </i>
                            </a>
                        `;

                    }

                    return `
                        <span
                            class="text-success"
                            title="Actualizado"
                        >
                            <i
                                class="ti ti-circle-check fs-7">
                            </i>
                        </span>
                    `;

                }
            }

        ]

    });

});