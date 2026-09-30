document.addEventListener('DOMContentLoaded', function () {

    var permisos = {};

    function getContainer() {
        return document.getElementById('container');
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function renderEstatus(estatus, type) {
        if (type === 'filter') {
            return ['Pendiente', 'En proceso', 'Finalizado'][estatus + 1] || '';
        }
        if (type === 'sort') {
            return estatus;
        }
        if (estatus === 1) {
            return '<span class="badge bg-success text-success text-white">Finalizado</span>';
        }
        if (estatus === 0) {
            return '<span class="badge bg-warning text-warning text-white">En proceso</span>';
        }
        return '<span class="badge bg-danger text-danger text-white">Pendiente</span>';
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    window.agregarPrecio = function (year, mes, fecha) {
        axios.post('/departamento-operativo/importacion/precios-diarios-combustible/agregar', {
            year: year,
            mes: mes,
            fecha: fecha
        }, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        })
        .then(function (r) {
            if (!r.data.success || !r.data.id) {
                Notify.error(r.data.message || 'Error al crear el registro');
                return;
            }
            Notify.success(r.data.message || 'Registro creado');
            window.location.href = '/departamento-operativo/importacion/precios-diarios-combustible/formulario/' + r.data.id;
        })
        .catch(function (err) {
            var msg = (err.response && err.response.data && err.response.data.message)
                ? err.response.data.message
                : 'Error de conexión al crear';
            Notify.error(msg);
        });
    };

    var year = getContainer().dataset.idYear;
    var mes = getContainer().dataset.idMes;

    var table = $('#tabla-precios').DataTable({
        processing: true,
        serverSide: false,
        autoWidth: false,
        stateSave: false,
        order: [[0, 'asc']],
        pageLength: 31,
        lengthMenu: [15, 31, 50, 100],
        language: {
            url: '/assets/libs/datatables.net/js/es-ES.json'
        },
        initComplete: function () {
            aplicarVisibilidad();
        },
        drawCallback: function () {
            aplicarVisibilidad();
        },
        createdRow: function (row, data) {
            if (data.rowClass) {
                $(row).addClass(data.rowClass);
            }
        },
        ajax: {
            url: '/departamento-operativo/importacion/precios-diarios-combustible/data',
            type: 'GET',
            data: { year: year, mes: mes },
            dataSrc: function (json) {
                permisos = json.permisos || {};
                return json.data;
            }
        },
        columns: [
            {
                data: 'day',
                title: '#',
                width: '60px',
                className: 'text-center align-middle fw-normal',
                render: function (data) { return String(data).padStart(2, '0'); }
            },
            {
                data: 'fecha',
                title: 'Fecha',
                className: 'text-start align-middle fw-bold',
                render: function (data) { return escHtml(data); }
            },

            {
                data: 'id',
                title: '<i class="ti ti-eye text-info fs-6"></i>',
                width: '48px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle col-accion-ver',
                render: function (data, type, row) {
                    if (row.estatus === -1) {
                        return '<i class="ti ti-eye fs-6 text-secondary" style="opacity:.35" title="Sin registro"></i>';
                    }
                    return '<a href="/departamento-operativo/importacion/precios-diarios-combustible-detalle/' + data + '" class="pointer">' +
                        '<i class="ti ti-eye text-primary fs-6"></i></a>';
                }
            },
            {
                data: 'id',
                title: '<i class="ti ti-edit fs-6 text-warning"></i>',
                width: '48px',
                orderable: false,
                searchable: false,
                className: 'text-center align-middle col-accion-editar',
                render: function (data, type, row) {
                    if (row.estatus === -1) {
                        return '<a class="pointer" title="Agregar" onclick="agregarPrecio(' + year + ', ' + mes + ', \'' + escHtml(row.fechaRaw) + '\')">' +
                            '<i class="ti ti-plus text-success fs-6"></i></a>';
                    }
                    return '<a href="/departamento-operativo/importacion/precios-diarios-combustible/formulario/' + data + '" class="pointer" title="Editar">' +
                        '<i class="ti ti-edit text-warning fs-6"></i></a>';
                }
            },
                        {
                data: 'estatus',
                title: 'Estatus',
                width: '140px',
                className: 'text-center align-middle',
                render: renderEstatus
            },
        ]
    });

    window.tablaPrecios = table;

    function aplicarVisibilidad() {
        try {
            table.column(3).visible(permisos.esPuesto13 === true);
            table.column(4).visible(permisos.puedeCrear === true || permisos.puedeEditar === true);
        } catch (e) {}
    }
});
