document.addEventListener('DOMContentLoaded', () => {

const year = new Date().getFullYear();

    table1 = $('#table-recepcion-descarga-producto').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: true,
        stateSave: true,
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url:
            '/sasisopa/control-actividades-procesos/recepcion-descarga-producto/datatable?year=' + year,
            type: 'GET',
             dataSrc: function (json) {
            permisos = json.permisos;
            return json.data;
            }
        },

        columns: [

            {
                data: 'folio'
            }, 
            
            {
                data: 'fecha',
                render: function(data, type) {

                    if (type === 'sort' || type === 'type') {
                        return data.sort;
                    }

                    return data.display;
                }
            },

           {
                data: 'hora_llegada',
                className: 'text-center',
                render: function(data, type) {

                    if (type === 'sort' || type === 'type') {
                        return data.sort;
                    }

                    return data.display;
                }
            },

           {
                data: 'hora_salida',
                className: 'text-center',
                render: function(data, type) {

                    if (type === 'sort' || type === 'type') {
                        return data.sort;
                    }

                    return data.display;
                }
            },
                        
            {
                data: 'placa'
            },

            {
                data: 'operador'
            },

            {
                data: 'no_factura'
            },

           {
                data: 'litros_compra',
                className: 'text-end',
                render: function(data, type) {

                    if (type === 'sort' || type === 'type') {
                        return data.sort;
                    }

                    return data.display;
                }
            },

            {
                data: 'producto',
                className: 'text-center',
                render: function(data) {

                    return `
                        <strong
                            style="color:${data.color}">
                            ${data.nombre}
                        </strong>
                    `;
                }
            },

            {
                data: 'firma_recibe',
                className: 'text-center',
                orderable: false,
                render: function(data) {

                    if (!data.firma) {
                           return `
                        <div>
                            <small>${data.nombre}</small>
                        </div>`;
                    }

                    return `
                        <img
                            width="50"
                            src="${data.firma}">

                        <div>
                            <small>${data.nombre}</small>
                        </div>
                    `;
                }
            },

            {
                data: 'firma_supervisa',
                className: 'text-center',
                orderable: false,
                render: function(data) {

                    if (!data.firma) {
                        return `
                        <div>
                            <small>${data.nombre}</small>
                        </div>`;
                    }

                    return `
                        <img
                            width="50"
                            src="${data.firma}">

                        <div>
                            <small>${data.nombre}</small>
                        </div>
                    `;
                }
            },

    
          
        ]
    });

    $("#table-recepcion-descarga-producto tbody").on("click", "tr", function () {

    const data = table1.row(this).data();

    if (!data) {
        return;
    }
    
    window.recepcionDescargaProducto.openModal(data);

    if ($(this).hasClass("selected")) {
    } else {
    table1.$("tr.selected").removeClass("selected");
    $(this).addClass("selected");
    }
    });

});