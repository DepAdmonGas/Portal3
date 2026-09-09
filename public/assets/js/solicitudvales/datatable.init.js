document.addEventListener(
    'DOMContentLoaded',
    () => {

        $('#table-vales').DataTable({

            processing: true,

            serverSide: false,

            autoWidth: false,

            stateSave: true,

            scrollX: true,

            order: [
                [
                    0,
                    'desc'
                ]
            ],

            pageLength: 25,

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

            ajax: {

                url:
                    '/solicitud-vales/datatable',

                type:
                    'GET',

                dataSrc:
                    function (json) {

                        if (!json.success) {
                            return [];
                        }

                        return json.data;
                    }

            },

            columnDefs: [

                {
                    targets: '_all',
                    className:
                        'align-middle'
                }

            ],

            columns: [

                /*
                 * FOLIO
                 */
                {
                    data:
                        'folio',

                    width:
                        '65px',

                    className:
                        'text-center fw-semibold',

                    render:
                        function (data) {

                            return `00${data}`;
                        }
                },


                /*
                 * FECHA
                 */
                {
                    data:
                        'fecha',

                    width:
                        '90px',

                    className:
                        'text-center',

                    render:
                        function (
                            data,
                            type
                        ) {

                            if (!data) {
                                return '';
                            }

                            /*
                             * Para ordenar correctamente,
                             * DataTables conserva el valor original.
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
                 * HORA
                 */
                {
                    data:
                        'hora',

                    width:
                        '80px',

                    className:
                        'text-center'
                },


                /*
                 * MONTO
                 */
                {
                    data:
                        'monto',

                    width:
                        '105px',

                    className:
                        'text-end',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            const monto =
                                Number(
                                    data || 0
                                );

                            if (
                                type === 'sort'
                                || type === 'type'
                            ) {

                                return monto;
                            }

                            const display =
                                monto.toLocaleString(
                                    'es-MX',
                                    {
                                        style:
                                            'currency',

                                        currency:
                                            'MXN'
                                    }
                                );

                            return `
                                <div
                                    class="editable-value rounded px-2 py-1 text-end"
                                    role="button"
                                    tabindex="0"

                                    data-editable="true"
                                    data-folio="${row.folio}"
                                    data-campo="monto"
                                    data-value="${monto}"

                                    title="Doble clic para editar"
                                >
                                    ${display}
                                </div>
                            `;
                        }
                },


                /*
                 * CONCEPTO
                 */
                {
                    data:
                        'concepto',

                    width:
                        '220px',

                    className:
                        'text-wrap',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            const concepto =
                                escapeDataTableHtml(
                                    data || ''
                                );

                            if (
                                type === 'sort'
                                || type === 'filter'
                            ) {

                                return data || '';
                            }

                            return `
                                <div
                                    class="editable-value rounded px-2 py-1 text-wrap"
                                    style="
                                        min-width: 180px;
                                        max-width: 260px;
                                        white-space: normal;
                                    "
                                    role="button"
                                    tabindex="0"

                                    data-editable="true"
                                    data-folio="${row.folio}"
                                    data-campo="concepto"
                                    data-value="${concepto}"

                                    title="Doble clic para editar"
                                >
                                    ${concepto}
                                </div>
                            `;
                        }
                },


                /*
                 * SOLICITANTE
                 */
                {
                    data:
                        'solicitante',

                    width:
                        '140px',

                    className:
                        'text-wrap',

                    render:
                        function (data) {

                            return `
                                <div
                                    style="
                                        min-width: 120px;
                                        max-width: 160px;
                                        white-space: normal;
                                    "
                                >
                                    ${escapeDataTableHtml(
                                        data || ''
                                    )}
                                </div>
                            `;
                        }
                },


                /*
                 * AUTORIZADO
                 */
                {
                    data:
                        'autorizado_por',

                    width:
                        '140px',

                    className:
                        'text-wrap',

                    render:
                        function (data) {

                            return `
                                <div
                                    style="
                                        min-width: 120px;
                                        max-width: 160px;
                                        white-space: normal;
                                    "
                                >
                                    ${escapeDataTableHtml(
                                        data || ''
                                    )}
                                </div>
                            `;
                        }
                },


                /*
                 * MÉTODO
                 */
                {
                    data:
                        'metodo_autorizacion',

                    width:
                        '130px',

                    className:
                        'text-wrap',

                    render:
                        function (data) {

                            return `
                                <div
                                    style="
                                        min-width: 110px;
                                        max-width: 150px;
                                        white-space: normal;
                                    "
                                >
                                    ${escapeDataTableHtml(
                                        data || ''
                                    )}
                                </div>
                            `;
                        }
                },


                /*
                 * RAZÓN SOCIAL
                 */
                {
                    data:
                        'razonsocial',

                    width:
                        '200px',

                    className:
                        'text-wrap',

                    render:
                        function (data) {

                            return `
                                <div
                                    style="
                                        min-width: 160px;
                                        max-width: 220px;
                                        white-space: normal;
                                    "
                                >
                                    ${escapeDataTableHtml(
                                        data || ''
                                    )}
                                </div>
                            `;
                        }
                },


                /*
                 * CUENTA
                 */
                {
                    data:
                        'cuenta',

                    width:
                        '130px',

                    className:
                        'text-wrap',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            const cuenta =
                                escapeDataTableHtml(
                                    data || ''
                                );

                            if (
                                type === 'sort'
                                || type === 'filter'
                            ) {

                                return data || '';
                            }

                            return `
                                <div
                                    class="editable-value rounded px-2 py-1 text-wrap"
                                    style="
                                        min-width: 110px;
                                        max-width: 150px;
                                        white-space: normal;
                                    "
                                    role="button"
                                    tabindex="0"

                                    data-editable="true"
                                    data-folio="${row.folio}"
                                    data-campo="cuenta"
                                    data-value="${cuenta}"

                                    title="Doble clic para editar"
                                >
                                    ${cuenta}
                                </div>
                            `;
                        }
                }

            ]

        });

    }
);


function formatearFecha(value) {

    if (!value) {
        return '';
    }

    const fecha =
        String(value)
            .substring(
                0,
                10
            );

    const partes =
        fecha.split('-');

    if (
        partes.length !== 3
    ) {

        return value;
    }

    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}


/*
 * Escapar contenido HTML.
 */
function escapeDataTableHtml(value) {

    const element =
        document.createElement(
            'div'
        );

    element.textContent =
        value ?? '';

    return element.innerHTML;
}