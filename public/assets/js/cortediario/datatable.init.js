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
                            '96px',

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
                                type,
                                row
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


                                return row.fecha_formateada || '';
                            }
                    },


                    /*
                     * ESTATUS
                     */
                    {
                        data:
                            'finalizado',

                        width:
                            '100px',

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
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                                class="text-muted pointer"
                                            >
                                                <i
                                                    class="ti ti-dots-vertical fs-6"
                                                ></i>
                                            </a>


                                            <ul class="dropdown-menu">

                                                <li>

                                                    <a
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
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            class="text-muted pointer"
                                        >
                                            <i
                                                class="ti ti-dots-vertical fs-6"
                                            ></i>
                                        </a>


                                        <ul class="dropdown-menu">

                                            <li>

                                                <a
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
