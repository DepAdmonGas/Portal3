document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-entregas').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/gestoria/entregas/table',
            type: 'GET',      
             dataSrc: function (json) {
                return json.data;
            }
        },
        columns: [

          { data: 'numero' },
          {
            data: 'fecha',
            className: 'lign-middle',
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
            
            { data: 'estacion' },
            { data: 'destinatario' },

            {
        data: 'estatus',
        className: 'text-center',
        render: function (data, type, row) {

            let estado = '';

            switch (data) {
                case 0:
                    estado = 'Pendiente';
                    break;

                case 1:
                    estado = 'En proceso';
                    break;

                case 2:
                    estado = 'Finalizada';
                    break;

                default:
                    estado = 'Desconocido';
            }

            return `
                <span class="badge ${row.color}">
                    ${estado}
                </span>
            `;
        }
    },
            
{
    data: null,
    width: '1%',
    orderable: false,
    searchable: false,
    className: 'text-center align-middle td-small',

    render: function (data, type, row) {

        // DESCARGAR
        const descargar = row.estatus == 2
            ? `
                <a
                    class="dropdown-item d-flex align-items-center pointer gap-3"
                    href="/gestoria/entregas/pdf/${row.id}">
                    <i class="ti ti-file-type-pdf"></i>
                    Descargar
                </a>
              `
            : `
                <a
                    class="dropdown-item d-flex align-items-center gap-3 text-muted"
                    style="pointer-events: none; opacity: .5;">
                    <i class="ti ti-file-type-pdf"></i>
                    Descargar
                </a>
              `;


        // EDITAR
        const editar = row.estatus != 2
            ? `
                <a
                    class="dropdown-item d-flex align-items-center pointer gap-3"
                    href="/gestoria/entregas/formulario/${row.id}">
                    <i class="ti ti-edit"></i>
                    Editar
                </a>
              `
            : `
                <a
                    class="dropdown-item d-flex align-items-center gap-3 text-muted"
                    style="pointer-events: none; opacity: .5;">
                    <i class="ti ti-edit"></i>
                    Editar
                </a>
              `;


        // ELIMINAR
        const eliminar = row.estatus == 0
            ? `
                <a
                    class="dropdown-item d-flex align-items-center pointer gap-3"
                    @click='window.entregas.eliminar(${row.id})'>
                    <i class="ti ti-trash"></i>
                    Eliminar
                </a>
              `
            : `
                <a
                    class="dropdown-item d-flex align-items-center gap-3 text-muted"
                    style="pointer-events: none; opacity: .5;">
                    <i class="ti ti-trash"></i>
                    Eliminar
                </a>
              `;


        return `
            <div x-data="actions()" class="d-flex gap-1 justify-content-center">

                <div class="dropdown dropstart">

                    <a
                        href="javascript:void(0)"
                        data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical fs-6"></i>
                    </a>

                    <ul class="dropdown-menu">

                        <li>
                            ${descargar}
                        </li>

                        <li>
                            ${editar}
                        </li>

                        <li>
                            ${eliminar}
                        </li>

                    </ul>

                </div>

            </div>
        `;
    }
}
        ]
    });

    $("#table-entregas tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
