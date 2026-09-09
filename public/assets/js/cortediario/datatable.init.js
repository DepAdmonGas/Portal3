document.addEventListener(
    'DOMContentLoaded',
    () => {

        const selectorTabla =
            '#table-corte-diario';


        /*
         * Evita inicialización doble.
         */
        if (
            $.fn.DataTable.isDataTable(
                selectorTabla
            )
        ) {

            $(selectorTabla)
                .DataTable()
                .destroy();
        }


        $(selectorTabla)
            .DataTable({

                processing:
                    true,

                serverSide:
                    false,

                autoWidth:
                    false,

                stateSave:
                    true,

                order: [
                    [
                        0,
                        'desc'
                    ]
                ],

                pageLength:
                    25,

                lengthMenu: [
                    25,
                    50,
                    75,
                    100
                ],

                language: {
                    url:
                        '/assets/libs/datatables.net/js/es-ES.json'
                },


                /**
                 * =================================================
                 * AJAX
                 * =================================================
                 */
                ajax: {

                    url:
                        '/corte-diario/datatable',

                    type:
                        'GET',

                    cache:
                        false,


                    data:
                        function (
                            data
                        ) {

                            /*
                             * Primero intentamos obtener el valor
                             * directamente del select.
                             */
                            const select =
                                document.getElementById(
                                    'filtroEstacion'
                                );


                            let idEstacion =
                                '';


                            if (
                                select
                                && select.value
                            ) {

                                idEstacion =
                                    select.value;

                            } else {

                                /*
                                 * Respaldo:
                                 * sessionStorage.
                                 */
                                idEstacion =
                                    sessionStorage.getItem(
                                        'idEstacion'
                                    ) || '';
                            }


                            data.idEstacion =
                                idEstacion;


                            /*
                             * Evita cache del navegador.
                             */
                            data._ =
                                Date.now();
                        },


                    dataSrc:
                        function (
                            json
                        ) {

                            if (
                                !json
                                || !json.success
                            ) {

                                return [];
                            }


                            return json.data
                                || [];
                        }

                },


                /**
                 * =================================================
                 * COLUMNAS
                 * =================================================
                 */
                columns: [

                    /*
                     * ID
                     */
                    {
                        data:
                            'id',

                        width:
                            '70px',

                        className:
                            'text-center fw-semibold'
                    },


                    /*
                     * FECHA
                     */
                    {
                        data:
                            'fecha',

                        width:
                            '140px',

                        render:
                            function (
                                data,
                                type
                            ) {

                                if (!data) {
                                    return '';
                                }


                                /*
                                 * Mantener ISO para ordenar.
                                 */
                                if (
                                    type === 'sort'
                                    || type === 'type'
                                ) {

                                    return data;
                                }


                                return formatearFecha(
                                    data
                                );
                            }
                    },


                    /*
                     * ESTATUS
                     */
                    {
                        data:
                            'finalizado',

                        width:
                            '130px',

                        className:
                            'text-center align-middle',

                        render:
                            function (
                                data,
                                type,
                                row
                            ) {

                                const finalizado =
                                    row.finalizado === true
                                    || row.finalizado === 1
                                    || row.finalizado === '1';


                                /*
                                 * Texto limpio para búsquedas
                                 * y ordenamiento.
                                 */
                                if (
                                    type === 'sort'
                                    || type === 'filter'
                                ) {

                                    return finalizado
                                        ? 'Finalizado'
                                        : 'Activo';
                                }


                                /*
                                 * FINALIZADO
                                 */
                                if (
                                    finalizado
                                ) {

                                    return `
                                        <span
                                            class="badge text-bg-success"
                                        >
                                            <i
                                                class="ti ti-circle-check me-1"
                                            ></i>

                                            Finalizado
                                        </span>
                                    `;
                                }


                                /*
                                 * ACTIVO
                                 */
                                return `
                                    <span
                                        class="badge text-bg-warning"
                                    >
                                        <i
                                            class="ti ti-clock me-1"
                                        ></i>

                                        Activo
                                    </span>
                                `;
                            }
                    },


                    /*
                     * ACCIONES
                     */
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

                        render:
                            function (
                                data,
                                type,
                                row
                            ) {

                                const finalizado =
                                    row.finalizado === true
                                    || row.finalizado === 1
                                    || row.finalizado === '1';


                                /*
                                 * =================================
                                 * FINALIZADO
                                 *
                                 * Acción disponible:
                                 * ACTIVAR
                                 * =================================
                                 */
                                if (
                                    finalizado
                                ) {

                                    return `
                                        <div class="dropdown dropstart">

                                            <a
                                                href="javascript:void(0)"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                                class="text-muted"
                                            >
                                                <i
                                                    class="ti ti-dots-vertical fs-6"
                                                ></i>
                                            </a>


                                            <ul class="dropdown-menu">

                                                <li>

                                                    <a
                                                        href="javascript:void(0)"
                                                        class="dropdown-item pointer d-flex align-items-center gap-3 btn-activate"
                                                        data-id="${row.id}"
                                                    >

                                                        <i
                                                            class="fs-4 ti ti-player-play"
                                                        ></i>

                                                        Activar

                                                    </a>

                                                </li>

                                            </ul>

                                        </div>
                                    `;
                                }


                                /*
                                 * =================================
                                 * ACTIVO
                                 *
                                 * Acción disponible:
                                 * FINALIZAR
                                 * =================================
                                 */
                                return `
                                    <div class="dropdown dropstart">

                                        <a
                                            href="javascript:void(0)"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            class="text-muted"
                                        >
                                            <i
                                                class="ti ti-dots-vertical fs-6"
                                            ></i>
                                        </a>


                                        <ul class="dropdown-menu">

                                            <li>

                                                <a
                                                    href="javascript:void(0)"
                                                    class="dropdown-item pointer d-flex align-items-center gap-3 btn-finalize"
                                                    data-id="${row.id}"
                                                >

                                                    <i
                                                        class="fs-4 ti ti-circle-check"
                                                    ></i>

                                                    Finalizar

                                                </a>

                                            </li>

                                        </ul>

                                    </div>
                                `;
                            }
                    }

                ]

            });

    }
);


/**
 * =========================================================
 * FORMATEAR FECHA
 * =========================================================
 */
function formatearFecha(
    value
) {

    if (!value) {
        return '';
    }


    const fecha =
        String(
            value
        )
            .substring(
                0,
                10
            );


    const partes =
        fecha.split(
            '-'
        );


    if (
        partes.length !== 3
    ) {

        return value;
    }


    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}