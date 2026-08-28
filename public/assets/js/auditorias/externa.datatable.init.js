document.addEventListener('DOMContentLoaded', () => {

    window.table1 = $('#tablaAuditoriasExternas').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/auditorias/externa/datatable',
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos;
                return json.data;
            }
        },
        columns: [

            {
                data: 'id',
                className: 'text-center align-middle'
            },

            {
                data: 'fecha_larga',
                className: 'text-center align-middle'
            },

            { data: 'prestador_servicio' },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function () {
                    return `
                        <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc" download>
                            <i class="ti ti-file-download text-info fs-7"></i>
                        </a>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return `
                        <a href="javascript:void(0)"
                           @click="window.auditoriasExterna.subir024(${row.id})">
                            <i class="ti ti-file-upload text-success fs-7"></i>
                        </a>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return row.formato024.existe
                        ? `
                            <a href="/uploads/${row.formato024.archivo}" download>
                                <i class="ti ti-download text-danger fs-7"></i>
                            </a>
                        `
                        : `
                            <i class="ti ti-x fs-7"></i>
                        `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function () {
                    return `
                        <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.025.docx" download>
                            <i class="ti ti-file-download text-info fs-7"></i>
                        </a>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return `
                        <a href="javascript:void(0)"
                           @click="window.auditoriasExterna.subir025(${row.id})">
                            <i class="ti ti-file-upload text-success fs-7"></i>
                        </a>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return row.formato025.existe
                        ? `
                            <a href="/uploads/${row.formato025.archivo}" download>
                                <i class="ti ti-download text-danger fs-7"></i>
                            </a>
                        `
                        : `
                            <i class="ti ti-x fs-7"></i>
                        `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return `
                        <a @click="window.auditoriasExterna.abrirAsea(${row.id}, 25)">
                            <i class="pointer ti ti-paperclip text-primary fs-7"></i>
                        </a>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                width: '1%',
                render: function (data, type, row) {
                    return `
                        <a @click="window.auditoriasExterna.eliminar(${row.id})">
                            <i class="pointer ti ti-trash text-danger fs-7"></i>
                        </a>
                    `;
                }
            }
        ],
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.querySelector('#tablaAuditoriasExternas'));
            }
        }
    });

});