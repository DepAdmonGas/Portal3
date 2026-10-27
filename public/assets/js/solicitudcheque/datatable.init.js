document.addEventListener(
    'DOMContentLoaded',
    () => {

        $('#table-cheques').DataTable({

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
                    '/solicitud-cheques/datatable',

                type:
                    'GET',

                dataSrc:
                    function (json) {

                        if (!json.success) {
                            window.solicitudesPendientesAutorizacion = [];
                            actualizarBotonFirmarTodas();
                            return [];
                        }

                        const rows = Array.isArray(json.data)
                            ? json.data
                            : [];

                        window.solicitudesPendientesAutorizacion = rows
                            .filter(row => {
                                const firmas = Number(row.firmas || 0);
                                return Number(row.status) === 1 && (firmas === 1 || firmas === 2);
                            })
                            .map(row => ({
                                id: Number(row.id),
                                opcion: Number(row.firmas) === 1 ? 1 : 2
                            }))
                            .filter(solicitud => Number.isInteger(solicitud.id) && solicitud.id > 0);

                        window.solicitudChequesDataReady = true;
                        actualizarBotonFirmarTodas();
                        document.dispatchEvent(
                            new CustomEvent('solicitud-cheques:datatable-loaded')
                        );

                        return rows;
                    }

            },


            columns: [

                /*
                 * ID
                 */
                {
                    data:
                        'id',

                    width:
                        '55px',

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
                        '155px',

                    className:
                        'align-middle text-center',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            if (!data) {
                                return '';
                            }


                            if (
                                type === 'sort'
                                || type === 'type'
                            ) {

                                return row.fecha_raw || '';
                            }

                            return data;
                        }
                },


                /*
                 * BENEFICIARIO
                 */
                {
                    data:
                        'beneficiario',

                    width:
                        '180px',

                    className:
                        'text-wrap',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            if (
                                type === 'sort'
                                || type === 'filter'
                            ) {

                                return data || '';
                            }


                            const valor =
                                escapeDataTableHtml(
                                    data || ''
                                );


                            return `
                                <div
                                    class="editable-value rounded px-2 py-1 text-wrap"

                                    style="
                                        min-width: 140px;
                                        max-width: 200px;
                                        white-space: normal;
                                    "

                                    data-editable="true"
                                    data-id="${row.id}"
                                    data-campo="beneficiario"
                                    data-value="${valor}"

                                    role="button"
                                    tabindex="0"

                                    title="Doble clic para editar"
                                >
                                    ${valor}
                                </div>
                            `;
                        }
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

                                    data-editable="true"
                                    data-id="${row.id}"
                                    data-campo="monto"
                                    data-value="${monto}"

                                    role="button"
                                    tabindex="0"

                                    title="Doble clic para editar"
                                >
                                    ${display}
                                </div>
                            `;
                        }
                },


                /*
                 * NO FACTURA
                 */
                {
                    data:
                        'no_factura',

                    width:
                        '110px',

                    className:
                        'text-center',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            if (
                                type === 'sort'
                                || type === 'filter'
                            ) {

                                return data || '';
                            }


                            const valor =
                                escapeDataTableHtml(
                                    data || ''
                                );


                            return `
                                <div
                                    class="editable-value rounded px-2 py-1"

                                    data-editable="true"
                                    data-id="${row.id}"
                                    data-campo="no_factura"
                                    data-value="${valor}"

                                    role="button"
                                    tabindex="0"

                                    title="Doble clic para editar"
                                >
                                    ${valor}
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

                            if (
                                type === 'sort'
                                || type === 'filter'
                            ) {

                                return data || '';
                            }


                            const valor =
                                escapeDataTableHtml(
                                    data || ''
                                );


                            return `
                                <div
                                    class="editable-value rounded px-2 py-1 text-wrap"

                                    style="
                                        min-width: 160px;
                                        max-width: 240px;
                                        white-space: normal;
                                    "

                                    data-editable="true"
                                    data-id="${row.id}"
                                    data-campo="concepto"
                                    data-value="${valor}"

                                    role="button"
                                    tabindex="0"

                                    title="Doble clic para editar"
                                >
                                    ${valor}
                                </div>
                            `;
                        }
                },


                /*
                 * FIRMA
                 */
                {
                    data:
                        null,

                    width:
                        '55px',

                    orderable:
                        false,

                    searchable:
                        false,

                    className:
                        'text-center',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            const firmas =
                                Number(
                                    row.firmas || 0
                                );


                            /*
                             * Comportamiento legacy:
                             *
                             * 1 firma -> opción 1
                             */
                            if (
                                firmas === 1
                            ) {

                                return `
                                    <button
                                        type="button"
                                        class="btn btn-sm p-0 border-0 bg-transparent text-primary btn-sign"

                                        data-id="${row.id}"
                                        data-opcion="1"

                                        title="Firmar solicitud"
                                    >
                                        <i
                                            class="ti ti-writing text-primary fs-8"
                                        ></i>
                                    </button>
                                `;
                            }


                            /*
                             * 2 firmas -> opción 2
                             */
                            if (
                                firmas === 2
                            ) {

                                return `
                                    <button
                                        type="button"
                                        class="btn btn-sm p-0 border-0 bg-transparent text-primary btn-sign"

                                        data-id="${row.id}"
                                        data-opcion="2"

                                        title="Firmar solicitud"
                                    >
                                        <i
                                            class="ti ti-writing text-primary fs-8"
                                        ></i>
                                    </button>
                                `;
                            }


                            /*
                             * 3 firmas / finalizada.
                             */
                            if (
                                firmas >= 3
                                || Number(
                                    row.status
                                ) === 2
                            ) {

                                return `
                                    <button
                                        type="button"
                                        class="btn btn-sm p-0 border-0 bg-transparent text-primary"
                                        disabled

                                        title="Firmas completadas"
                                    >
                                        <i
                                            class="ti ti-writing text-primary fs-8"
                                            style="opacity:.5;cursor:default;"
                                        ></i>
                                    </button>
                                `;
                            }


                            /*
                             * El legacy tampoco muestra
                             * acción cuando no hay firmas.
                             */
                            return '';
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
                                        min-width: 110px;
                                        max-width: 150px;
                                        white-space: normal;
                                    "
                                >
                                    ${
                                        escapeDataTableHtml(
                                            data || ''
                                        )
                                    }
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
                                        min-width: 150px;
                                        max-width: 220px;
                                        white-space: normal;
                                    "
                                >
                                    ${
                                        escapeDataTableHtml(
                                            data || ''
                                        )
                                    }
                                </div>
                            `;
                        }
                }

            ]

        });

    }
);


function actualizarBotonFirmarTodas() {

    const button = document.getElementById('btn-firmar-todas');
    const count = document.getElementById('firmar-todas-count');
    const total = (window.solicitudesPendientesAutorizacion || []).length;

    if (!button || !count) {
        return;
    }

    count.textContent = total > 0 ? String(total) : '';
    button.classList.toggle('d-none', total < 2);
    button.disabled = total < 2;
}


function escapeDataTableHtml(value) {

    const element =
        document.createElement(
            'div'
        );


    element.textContent =
        value ?? '';


    return element.innerHTML;
}
