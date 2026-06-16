document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-seguridad-contratista').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/seguridad-contratistas/datatable',
            type: 'GET',
            dataSrc: function (json) {
         
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [

        {
            data: 'folio'
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
            data: 'solicitante'
        },

        {
            data: 'proveedor'
        },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="javascript:void(0)"
                    @click='window.seguridadContratistas.openModalFormato12(${JSON.stringify(row)})'>
                        <i class="ti ${
                            row.formato12
                                ? 'ti-edit text-success'
                                : 'ti-plus text-success'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="/sasisopa/seguridad-contratistas/formato12/pdf/${row.id}">
                        <i class="ti ${
                            row.formato12
                                ? 'ti-file-type-pdf text-danger fs-7'
                                : 'ti-x text-danger fs-7'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="/sasisopa/seguridad-contratistas/formato13/${row.id}">
                        <i class="ti ti-file-type-pdf text-danger fs-7"></i>
                    </a>
                `;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="javascript:void(0)"
                    @click='window.seguridadContratistas.modalFormato14(${row.id})'>
                        <i class="ti ${
                            row.formato14
                                ? 'ti-edit text-success'
                                : 'ti-plus text-success'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="${row.formato14_url}" target="_BLANK">
                        <i class="ti ${
                            row.formato14
                                ? 'ti-file-type-pdf text-danger'
                                : 'ti-x text-danger'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

        
        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',

            render: function(data, type, row) {

                return `
                <a href="javascript:void(0)"
                @click="window.seguridadContratistas.modalFormato15(${row.id})">

                    <i class="ti
                    ${row.formato15
                        ? 'ti-edit text-success'
                        : 'ti-plus text-success'}
                    fs-6">
                    </i>

                </a>`;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="/sasisopa/seguridad-contratistas/formato15/pdf/${row.id}">
                        <i class="ti ${
                            row.formato15
                                ? 'ti-file-type-pdf text-danger fs-7'
                                : 'ti-x text-danger fs-7'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

         {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="javascript:void(0)"
                    @click="window.seguridadContratistas.openCartaResponsiva(${row.id})">
                        <i class="ti ${
                            row.carta_responsiva
                                ? 'ti-edit text-success'
                                : 'ti-plus text-success'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                return `
                    <a href="/sasisopa/seguridad-contratistas/carta-responsiva/pdf/${row.id}">
                        <i class="ti ${
                            row.carta_responsiva
                                ? 'ti-file-type-pdf text-danger'
                                : 'ti-x text-danger'
                        } fs-7"></i>
                    </a>
                `;
            }
        },

           

    {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center align-middle',
        render: function (data, type, row) {

           const noEdit = permisos.editar;
           const noDelete = permisos.eliminar;
           
            return `
            <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">

                        <li>
                    
                            <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled' : ''}"
                            @click='window.seguridadContratistas.openModalEditar(${JSON.stringify(row)})'>
                                <i class="ti ti-edit"></i>Editar
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled' : ''}"
                            ${!noDelete ? '' : `
                            @click='window.seguridadContratistas.eliminar(${row.id}, ${row.folio})'
                            `}>
                                <i class="ti ti-trash"></i>Eliminar
                            </a>
                        </li>
                    </ul>
                </div>
                </div>
            `;
       
        }
    }
]
    });

    $("#table-seguridad-contratista tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


