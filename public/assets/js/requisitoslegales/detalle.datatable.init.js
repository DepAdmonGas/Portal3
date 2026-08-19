document.addEventListener('DOMContentLoaded', () => {

     const ngobierno = document
    .getElementById('container')
    .dataset.ngobierno;

     const modulo = document
    .getElementById('container')
    .dataset.modulo;

    table1 = $('#table-lista-requisitos-legales-detalle').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/requisitos-legales/datatable-detalle/' + ngobierno + '/' + modulo,
            type: 'GET',
            dataSrc: function (json) {
            
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
            { data: 'dependencia',
                render: function (data) {
                return `<div style="max-width:500px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },
             {
            data: 'permiso',
            render: function (data) {
                return `<div >${data}</div>`;
                }
            },
            { data: 'vigencia',
                className: 'text-center align-middle',
             },
            {
            data: 'fecha_emision',
            className: 'text-center align-middle',
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
            
        },{
            data: 'fecha_vencimiento',
            className: 'text-center align-middle',
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
            data: null,
            width: '1%',
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                const noDescargar = permisos.descargar;
                const tieneArchivo = row.acuse_file && row.acuse_file.trim() !== '';

                const archivo = row.acuse_file.split('/').pop();

               
                if (!tieneArchivo) {
                    return `<i class="ti ti-x text-danger fs-6"></i>`;
                }

                return `
                <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                    <a class="${!noDescargar ? 'disabled' : ''}" href="javascript:void(0)"
                    ${!noDescargar ? '' : `
                        @click="download('requisitos-legales','${archivo}')"
                        `}>
                        <i class="ti ti-download fs-6 text-success"></i>
                    </a>
                </div>
                `;
            }
        },

            {
            data: null,
            width: '1%',
            orderable: false,
            searchable: false,
            className: 'text-center align-middle',
            render: function (data, type, row) {

                const noDescargar = permisos.descargar;
                const tieneArchivo = row.requisito_file && row.requisito_file.trim() !== '';
                const archivo = row.requisito_file.split('/').pop();

               
                if (!tieneArchivo) {
                    return `<i class="ti ti-x text-danger fs-6"></i>`;
                }
                
                return `
                    <div x-data="actions()">
                    <a class="${!noDescargar ? 'disabled' : ''}" href="javascript:void(0)"
                     ${!noDescargar ? '' : `
                        @click="download('requisitos-legales','${archivo}')"
                        `}>
                        <i class="ti ti-download fs-6 text-success"></i>
                    </a>
                    </div>
                `;
            }
        },

        {
            data: 'cumplimiento',
            className: 'text-center align-middle',
            render: function (data) {
                return (data ?? 0) + ' %';
            }
        },
        { data: 'renovacion', className: 'text-center align-middle',
            render: function (data) {
                return `<div style="max-width:500px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
         },

         {
            data: 'estatus',
            className: 'text-center align-middle',
            render: function (data, type) {

                if (!data || !data.titulo) return '';

                return `
                    <span class="badge rounded-pill ${data.color_css}">
                        ${data.titulo}
                    </span>
                `;
            }
        },

        {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function (data, type, row) {
                                      
                    const noEdit = permisos.editar;
                    const noDelete = permisos.eliminar;
                    const noDownload = permisos.descargar;
                    const permisoNombre = JSON.stringify(row.permiso ?? '');
                    const vigencia = JSON.stringify(row.vigencia ?? '');

                    return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">

                                <li>                            
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                     @click="window.requisitosInstance.openDetalle(${row.id})">
                                        <i class="ti ti-eye"></i>Detalle
                                    </a>
                                </li>

                                <li>
                            
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                                    @click="window.requisitosInstance.openEditar(${row.id})">
                                        <i class="ti ti-edit"></i>Editar
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDownload ? 'disabled' : ''}"
                                    @click='window.requisitosInstance.openHistorial(${row.id}, ${permisoNombre}, ${vigencia})'>
                                        <i class="ti ti-history"></i>Historial
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='window.requisitosInstance.handleDelete(${row.id}, ${permisoNombre})'
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

    $("#table-lista-requisitos-legales-detalle tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


