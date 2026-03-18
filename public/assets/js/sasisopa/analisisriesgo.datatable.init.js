document.addEventListener('DOMContentLoaded', () => {

    $('#table-lista-analisis-riesgo').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: true, 
        order: [[0, 'desc']],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        ajax: {
            url: '/sasisopa/datatable-lista-analisis-riesgo',
            type: 'GET',
            dataSrc: function (json) {
                return json.data;
            },
            error: function (xhr) {
                
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
                render: function () {
                    return `<i class="fs-4 ti ti-file-text fs-6"></i>`;
                }
            },
            {
                data: null,
                width: '1%',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle td-small',
                render: function () {
                    return `<i class="fs-4 ti ti-file-text fs-6"></i>`;
                }
            }
        ]
    });

});
