document.addEventListener(
    'DOMContentLoaded',
    () => {

        $('#table-cursos').DataTable({

            processing: true,

            stateSave: true,

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
                    '/cursos/datatable',

                dataSrc:
                    'data'
            },

            columns: [

                {
                    data:
                        'num_tema',

                    className:
                        'text-center fw-semibold'
                },


                {
                    data:
                        'modulo',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            if (
                                type !== 'display'
                            ) {

                                return data;
                            }


                            return `
                                <div
                                    class="editable-curso"
                                    data-tipo="modulo"
                                    data-id="${row.modulo_id}"
                                >${escapeHtml(data)}</div>
                            `;
                        }
                },


                {
                    data:
                        'titulo',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            if (
                                type !== 'display'
                            ) {

                                return data;
                            }


                            return `
                                <div
                                    class="editable-curso"
                                    data-tipo="tema"
                                    data-id="${row.id}"
                                    data-campo="titulo"
                                >${escapeHtml(data)}</div>
                            `;
                        }
                },


                {
                    data:
                        'categoria',

                    render:
                        function (
                            data,
                            type,
                            row
                        ) {

                            const value =
                                data || '';


                            if (
                                type !== 'display'
                            ) {

                                return value;
                            }


                            return `
                                <div
                                    class="editable-curso"
                                    data-tipo="tema"
                                    data-id="${row.id}"
                                    data-campo="categoria"
                                >${escapeHtml(value)}</div>
                            `;
                        }
                },


                {
                    data:
                        'archivo',

                    width:
                        '70px',

                    orderable:
                        false,

                    searchable:
                        false,

                    className:
                        'text-center',

                    render:
                        function () {

                            return `
                                <i
                                    class="ti ti-file-type-pdf fs-5"
                                ></i>
                            `;
                        }
                },


                {
                    data:
                        null,

                    width:
                        '50px',

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

                            return `
                                <div class="dropdown dropstart">

                                    <a
                                        href="javascript:void(0)"
                                        data-bs-toggle="dropdown"
                                    >
                                        <i
                                            class="ti ti-dots-vertical fs-6"
                                        ></i>
                                    </a>

                                    <ul class="dropdown-menu">

                                        <li>

                                            <a
                                                href="/cursos/cuestionario/${row.id}"
                                                class="dropdown-item d-flex align-items-center gap-2"
                                            >
                                                <i
                                                    class="ti ti-adjustments"
                                                ></i>

                                                Cuestionario
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


function escapeHtml(value) {

    return String(value ?? '')
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );
}