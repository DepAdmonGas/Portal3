document.addEventListener('DOMContentLoaded', () => {

    $('#table-lista-analisis-riesgo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[1, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/datatable-lista-analisis-riesgo',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
            }
        },
        columns: [
            { data: 'id', width: '60px', className: 'text-center' },
            {
                data: 'fecha',
                render: function (data, type) {

                    if (!data) return '';

                    const fecha = new Date(data);

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
            { data: 'descripcion' },

           {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {
                    const archivoDisponible = Boolean(row.documento_disponible && row.documento_url);
                    const puedeDescargar = Boolean(permisos.descargar && archivoDisponible);
                    const descarga = puedeDescargar
                        ? `href="${row.documento_url}"`
                        : 'href="#" aria-disabled="true" tabindex="-1"';
                    const etiquetaDescarga = archivoDisponible ? 'Descargar PDF' : 'Archivo no disponible';
                    const iconoDescarga = archivoDisponible ? 'ti-download' : 'ti-file-off';
                    
                    return `
                    <div x-data="actions()" class="d-flex gap-1 justify-content-center">
                        <div class="dropdown dropstart">
                            <a class="pointer" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                              
                                 <li>
                                    <a
                                    class="dropdown-item pointer d-flex align-items-center gap-3 ${puedeDescargar ? '' : 'disabled'}"
                                    ${descarga}
                                    aria-label="${etiquetaDescarga}"
                                    @click.prevent="true">
                                        <i class="ti ${iconoDescarga} fs-6"></i>Descargar
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!permisos.descargar ? 'disabled' : ''}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#anexos"
                                    @click="$dispatch('open-anexos', { id: ${row.id} })">
                                        <i class="ti ti-file fs-6"></i>Anexos
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

     $("#table-lista-analisis-riesgo tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table2.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
