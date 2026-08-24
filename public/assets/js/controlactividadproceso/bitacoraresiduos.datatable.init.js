document.addEventListener('DOMContentLoaded', () => {

    table1 = $('#table-residuos-peligrosos').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },

        ajax: {
            url: '/sasisopa/control-actividades-procesos/bitacora-residuos-peligrosos/datatable',
            type: 'GET',
            dataSrc: function(json){

                permisos = json.permisos;

                return json.data;
            }
        },

        columns: [

            {
                data: 'folio',
                className: 'text-center align-middle',
                defaultContent: 'S/I'
            },

            {
                data: 'nombreresiduo',
                className: 'text-center align-middle',
                defaultContent: 'S/I'
            },

            {
                data: 'cantidadgenerada',
                className: 'text-center align-middle',
                defaultContent: 'S/I'
            },

            {
                data: 'caracteristicas',
                className: 'text-center align-middle',
                defaultContent: 'S/I'
            },

            {
                data: 'areaproceso',
                className: 'text-center align-middle',
                defaultContent: 'S/I'
            },

            {
            data: 'fechaingreso',
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
            data: 'fechasalida',
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
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',

                render: function(data, type, row){

               
                    return `
                        <a href="javascript:void(0)" @click='window.bitacoraResiduos.detalle(${JSON.stringify(row)})'>
                            <i class="ti ti-eye fs-6"></i>
                        </a>
                    `;
                }
            }
        ]
    });

    $("#table-residuos-peligrosos tbody").on(
        "click",
        "tr",
        function () {

            if ($(this).hasClass("selected")) {

                return;
            }

            table1.$("tr.selected")
                .removeClass("selected");

            $(this)
                .addClass("selected");
        }
    );

});