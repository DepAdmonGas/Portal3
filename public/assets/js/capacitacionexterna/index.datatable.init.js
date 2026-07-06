document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-capacitacion-externa').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[2, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/datatable-capacitacion-externa',
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
        render: function (data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            }
        },

    { 
        data: 'curso',
        defaultContent: 'S/I'
    },

    {
        data: 'fecha_programada',
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

   {
        data: null,
        defaultContent: 'S/I',
        render: function (data, type, row) {
            let duracion = row.duracion ?? '';
            let detalle = row.duraciondetalle ?? '';

            if (!duracion && !detalle) {
                return 'S/I';
            }

            return duracion + ' ' + detalle;
        }
    },

     { 
        data: 'instructor',
        defaultContent: 'S/I'
    },

    {
        data: 'fecha_real',
        render: function (data) {

            if (
                !data ||
                data.startsWith('0000-00-00') ||
                data.startsWith('-000001') ||
                data.includes('-000001-11-30')
            ) {
                return '<small class="text-danger">Falta editar la fecha real del curso</small>';
            }

            const fecha = new Date(data);

            if (isNaN(fecha.getTime())) return 'S/I';

            return fecha.toLocaleDateString('es-MX', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
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
            const cursoTitulo = JSON.stringify(row.curso ?? '');

            return `
            <div class="d-flex gap-1 justify-content-center">
                <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">

                    <li>                            
                            <a class="dropdown-item d-flex align-items-center gap-3 ${!noEdit ? 'disabled text-muted' : ''}"
                            ${!noEdit ? '' : `
                                onclick='window.capacitacionExterna.openModalEditar(${JSON.stringify(row)})'
                            `}>
                                <i class="ti ti-edit"></i>Editar
                            </a>
                        </li>

                       <li>                            
                            <a class="dropdown-item d-flex align-items-center gap-3"
                                onclick='window.capacitacionExterna.openModalPersonal(${row.id})'>
                                <i class="ti ti-users"></i>Trabajadores
                            </a>
                        </li>

                        <li>
                            <a href="/sasisopa/competencia-personal-capacitacion-entrenamiento/pdf-capacitacion-externa/${row.id}" class="dropdown-item d-flex align-items-center gap-3">
                                <i class="ti ti-download"></i>Descargar
                            </a>
                        </li>

                         <li>
                            <a class="dropdown-item d-flex align-items-center gap-3 ${!noDelete ? 'disabled text-muted' : ''}"
                            ${!noDelete ? '' : `
                                    @click='window.capacitacionExterna.delete(${row.id}, ${cursoTitulo})'
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

    $("#table-capacitacion-externa tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


