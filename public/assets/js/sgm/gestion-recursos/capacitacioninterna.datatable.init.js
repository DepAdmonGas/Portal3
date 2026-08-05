let table1;
document.addEventListener('DOMContentLoaded', () => {

    let permisos = {};
    let year = new Date().getFullYear();

    table1 = $('#table-capacitacion-interna').DataTable({

        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true,

        order: [[3, 'asc']],

        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },

        ajax: {

            url: '/sgm/gestion-recursos/programa-capacitacion-interna/datatable/' + year,
            type: 'GET',

            dataSrc: function(json){

                permisos = json.permisos ?? {};

                return json.data;

            }

        },


        columns: [

          {
          data: null,
          className: 'text-center',
          searchable: false,
          orderable: false,
          render: function (data, type, row, meta) {

              return meta.row + 1;

          }
      },

            {
                data:'curso',
                render:function(data){

                    return `
                    <div style="
                    max-width:100%;
                    white-space:normal;
                    word-break:break-word;">
                        ${data ?? ''}
                    </div>`;

                }
            },


            {
                data:'tipo',
                className:'text-center'
            },


            {
            data: 'fecha_programada',
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
                data:'duracion',
                className:'text-center'
            },


            {
                data:'instructor',
                className:'text-center'
            },


            {
            data: 'fecha_real',
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
                data:'usuario',

                render:function(data){

                    return `
                    <div style="
                    max-width:100%;
                    white-space:normal;
                    word-break:break-word;">
                    ${data ?? ''}
                    </div>`;

                }
            },


            {
                data:'resultado',

                className:'text-center',

                orderable:false,

                render:function(data, type, row){

                      if(!data)
                        return '';

                    if(data.pdf){

                        return `
                        <a target="_blank"
                        href="/sgm/gestion-recursos/programa-capacitacion-interna/reconocimiento/${row.id}">
                            <i class="ti ti-file-type-pdf fs-7 text-danger"></i>
                        </a>
                        `;

                    }


                    return `
                    <i class="ti ti-x fs-7"></i>
                    `;

                }

            },


        ]

    });



    $("#table-capacitacion-interna tbody")
    .on("click","tr",function(){

        if($(this).hasClass("selected")){

            $(this).removeClass("selected");

        }else{

            table1
            .$("tr.selected")
            .removeClass("selected");

            $(this)
            .addClass("selected");

        }

    });


});