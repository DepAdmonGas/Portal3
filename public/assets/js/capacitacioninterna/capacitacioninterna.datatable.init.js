document.addEventListener('DOMContentLoaded', () => {

const idtema = document.getElementById('container').dataset.idtema;
const idmodulo = document.getElementById('container').dataset.idmodulo;

    table1 = $('#table-capacitacion-interna').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/datatable-capacitacion-interna/' + idtema,
            type: 'GET',
            dataSrc: function (json) {
            
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
    { data: 'id' },

    { 
        data: 'nombre',
        defaultContent: 'S/I'
    },

    { 
        data: 'puesto',
        defaultContent: 'S/I'
    },

    { 
        data: 'telefono',
        defaultContent: 'S/I'
    },

    { 
        data: 'email',
        defaultContent: 'S/I'
    },

    {
        data: 'fecha_programada',
        render: function (data) {

            if (!data) return 'S/I';

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
    render: function (data, type, row) {
        return `<span class="${row.color} fw-bold">
                    ${row.texto_resultado}
                </span>`;
        }
    },

    {
        data: null,
        width: '1%',
        orderable: false,
        searchable: false,
        className: 'text-center align-middle',
        render: function (data, type, row) {

            const noCreate = permisos?.crear;

            return `
            <div class="d-flex gap-1 justify-content-center">
                <div class="dropdown dropstart">
                    <a href="javascript:void(0)" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical fs-6"></i>
                    </a>
                    <ul class="dropdown-menu">

                        <li>                            
                            <a class="dropdown-item pointer d-flex align-items-center gap-3 ${!noCreate ? 'disabled' : ''}" 
                            @click="openModalProgramar(${row.id}, ${idmodulo}, ${idtema})">
                               <i class="ti ti-chalkboard-teacher"></i>Programar Curso
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item pointer d-flex align-items-center gap-3"
                            onclick="window.dispatchEvent(new CustomEvent('ver-cursos', {
                            detail: { idUsuario: ${row.id}, idTema: ${idtema}, nombre: '${row.nombre.replace(/'/g, "\\'")}' }
                             }))">
                                <i class="ti ti-download"></i>Cursos Programados
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

    $("#table-capacitacion-interna tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});


