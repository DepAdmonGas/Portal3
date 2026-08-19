document.addEventListener('DOMContentLoaded', () => {

     $('#table-equipo-critico').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/integridad-mecanica-aseguramiento/datatable-equipo-critico',
            type: 'GET',
            dataSrc: function (json) {
                permisos = json.permisos;
                return json.data;
            }
        },
        columns: [
            { data: 'id_equipo', width: '60px', className: 'text-center' },
            { data: 'nombre_equipo' },
            { data: 'marca_modelo', className: 'text-center' },
            { data: 'funciones' },
            {
            data: 'fecha_instalacion', className: 'text-center',
            render: function (data, type) {

                if (!data) return '';

                const fecha = new Date(data);

                const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                // Para búsqueda y display usar el texto formateado
                if (type === 'display' || type === 'filter') {
                    return fechaFormateada;
                }

                // Para ordenamiento usar la fecha real
                return data;
            },
            orderable: true,
            searchable: true
        },

        { data: 'tiempo_vida', className: 'text-center' },
             
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function (data, type, row) {

                    const archivo = row.manual.split('/').pop();
                    const noDelete = permisos.eliminar;
                    const nomEquipo = JSON.stringify(row.nombre_equipo ?? '');
                    

                    return `
                        <div class="dropdown dropstart">
                            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                                <i class="ti ti-dots-vertical fs-6"></i>
                            </a>
                            <ul class="dropdown-menu">
                                 <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3" href="javascript:void(0)" 
                                    @click="download('manual','${archivo}')">
                                        <i class="fs-4 ti ti-download"></i>Descargar
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='window.equipoCritico.baja(${row.id}, ${nomEquipo})'
                                    `}>
                                        <i class="fs-4 ti ti-archive-off"></i>Baja
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                                    ${!noDelete ? '' : `
                                    @click='window.equipoCritico.eliminar(${row.id}, ${nomEquipo})'
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

});
