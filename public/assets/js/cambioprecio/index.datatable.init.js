document.addEventListener('DOMContentLoaded', () => {

    if (!document.getElementById('table-cambio-precio')) {
        return;
    }

    table1 = $('#table-cambio-precio').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/cambio-precio/datetable',
            type: 'GET',
            dataSrc: function (json) {
            //guardas permisos globalmente
            permisos = json.permisos;
            return json.data;
        }
        },
        columns: [
        { data: 'id', className: 'text-center' },
        { data: 'fecha', className: 'text-center' },
        
        { data: 'hora', className: 'text-center' },
        {
        data: 'gsuper',
        className: 'text-center',
        render: function(data){
            return `<span class="fw-bold text-success">$${parseFloat(data).toFixed(2)}</span>`;
        }
    },
    {
        data: 'gpremium',
        className: 'text-center',
        render: function(data){
            return `<span class="fw-bold text-danger">$${parseFloat(data).toFixed(2)}</span>`;
        }
    },
    {
        data: 'gdiesel',
        className: 'text-center',
        render: function(data){
            return `<span class="fw-bold text-dark">$${parseFloat(data).toFixed(2)}</span>`;
        }
    },

        {
            className: 'text-center align-middle',
            orderable: false,
            searchable: false,
            render: function (data, type, row) {
                return `
                    <a href="javascript:void(0)"
                    onclick="cambioprecio.eliminar(${row.id})">
                    <i class="ti ti-trash fs-7 text-danger"></i>
                    </a>
                    `;
            }
        },
           
        {
            data: 'estado',
            className: 'text-center align-middle',
            orderable: false,
            searchable: false,
            render: function (estado) {
                return `<i class="ti ${estado.icon} ${estado.color_css} fs-7"></i>`;
            }
        },
    
        ]
    });

     $("#table-cambio-precio tbody").on("click", "tr", function () {
    if ($(this).hasClass("selected")) {
    } else {
        table1.$("tr.selected").removeClass("selected");
        $(this).addClass("selected");
    }
    });

});
