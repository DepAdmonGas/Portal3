document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-registro-comunicacion').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/comunicacion-participacion-consulta/datatable-registro-comunicacion',
            type: 'GET',
            dataSrc: function (json) {
                //guardas permisos globalmente
                permisos = json.permisos;
                return json.data;
            }
        },
        columns: [
            {
            data: null,
            width: '60px', className: 'text-center',
            render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            {
            data: 'fecha',
            render: function (data, type, row) {

                if (!data) return 'S/I';

                if (type === 'sort' || type === 'type') {
                    return data;
                }

                const fecha = new Date(data);

                return fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            }
        },
            { data: 'tema',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:250px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },
            { data: 'encargado_comunicacion' },
            { data: 'tipo_comunicacion' },
             { data: 'material',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:200px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },
            { data: 'seguimiento',
                defaultContent: 'S/I',
                render: function (data) {
                return `<div style="max-width:160px; white-space:normal; word-break:break-word;">${data}</div>`;
                }
             },

            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {

                    const noEdit = permisos.editar;
                    const noDelete = permisos.eliminar;
                    const noDownload = permisos.descargar;
                    

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                           <li>
                                <a class="dropdown-item pointer d-flex align-items-center gap-3"
                                @click='window.comunicacionParticipacionConsulta.openModalDetalle(${row.id})'>
                                    <i class="fs-4 ti ti-eye"></i>Detalle
                                </a>
                            </li>
                           <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            @click='window.comunicacionParticipacionConsulta.openModalEvidencia(
                                    ${row.id},
                                    ${JSON.stringify(row.tema)}
                            )'>
                                <i class="fs-4 ti ti-files"></i>Evidencia
                            </a>
                        </li>
                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                                    ${!noEdit ? '' : `
                                    @click='window.comunicacionParticipacionConsulta.editarComunicacion(${JSON.stringify(row)})'
                                    `}>
                                        <i class="fs-4 ti ti-edit"></i>Editar
                                    </a>
                                </li>
                                 <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3" href="/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion?id=${row.id}">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>
                               <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                   @click='window.comunicacionParticipacionConsulta.eliminarComunicacion(${row.id}, ${JSON.stringify(row.tema)})'
                                    `}>
                                        <i class="fs-4 ti ti-trash"></i>Eliminar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    `;
                }
            }

            
        ]
    });

     $("#table-registro-comunicacion tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
