document.addEventListener('DOMContentLoaded', function () {

    var c = document.getElementById('container');
    if (!c) return;

    var permisos = {};
    var idYear = parseInt(c.dataset.idYear || '0');

    var dirTableInstance = null;

    function cls(enabled) { return enabled ? '' : ' disabled'; }

    function firmaIcon(d) {
        var st = d.status;
        if (st === 0 || st === 3) {
            return '<i class="ti ti-signature text-success fs-10" style="width:20px;height:20px;filter:grayscale(1) opacity(0.5);cursor:default;"></i>';
        }
        var nf = d.num_firmas || 0;
        var src;
        if (nf >= 3) src = '';
        else if (nf >= 2) src = '<i class="ti ti-writing text-primary fs-8"></i>';
        else src = '<i class="ti ti-writing text-dark fs-8"></i>';
        return '<a href="/departamento-operativo/recursos-humanos/dia-doble-firma/' + d.id + '" class="firma-link">' + src + '</a>';
    }

    function statusBadge(v, t, d) {
        if (t === 'display') {
            var st = d.status;
            var cls = 'bg-danger text-white';
            if (st === 0) cls = 'bg-danger text-white';
            else if (st === 1 || st === 2) cls = 'bg-warning text-white';
            else if (st === 3) cls = 'bg-success';
            return '<span class="badge rounded-pill ' + cls + '">' + d.status_label + '</span>';
        }
        return d.status_label;
    }

function comentariosIcon(row) {
    var count = row.comentarios || 0;
    var badge = count > 0
        ? '<span class="badge-historico position-absolute top-0 start-100 translate-middle">' + count + '</span>'
        : '';

    return '<a href="javascript:void(0);" class="btn-comentarios btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center" data-action="comentarios" data-id="' + row.id + '" title="Comentarios">' +
           '<i class="ti ti-message fs-7"></i>' + badge +
           '</a>';
}

    function renderAcciones(row) {  
        var s = row.status;

        var puedeDetalle  = s >= 1;
        var puedePdf      = s === 3;
        var puedeEditar   = s === 0;
        var puedeEliminar = s === 0;

        var html = '<div class="d-flex gap-1 justify-content-center">';
        html += '<div class="dropdown dropstart">';
        html += '<a href="javascript:void(0)" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a>';
        html += '<ul class="dropdown-menu">';

        html += '<li><a class="dropdown-item d-flex align-items-center gap-3' + cls(puedeDetalle) + '" href="javascript:void(0)" data-action="detalle" data-id="' + row.id + '">';
        html += '<i class="ti ti-eye me-1"></i>Detalle</a></li>';

        html += '<li><a class="dropdown-item d-flex align-items-center gap-3' + cls(puedePdf) + '" href="javascript:void(0)" data-action="pdf-direccion" data-id="' + row.id + '">';
        html += '<i class="ti ti-file-text me-1"></i>Descargar PDF</a></li>';

        html += '<li><a class="dropdown-item d-flex align-items-center gap-3' + cls(puedeEditar) + '" href="javascript:void(0)" data-action="editar" data-id="' + row.id + '">';
        html += '<i class="ti ti-pencil me-1"></i>Editar</a></li>';

        html += '<li><a class="dropdown-item d-flex align-items-center gap-3 ' + cls(puedeEliminar) + '" href="javascript:void(0)" data-action="eliminar" data-id="' + row.id + '" data-name="Reporte #' + row.id + '">';
        html += '<i class="ti ti-trash me-1"></i>Eliminar</a></li>';

        html += '</ul></div></div>';
        return html;
    }

    function destroyDirTable() {
        if (dirTableInstance) {
            dirTableInstance.destroy();
            dirTableInstance = null;
        }
        var tableEl = document.getElementById('tabla-dia-doble');
        if (tableEl) {
            tableEl.querySelector('tbody').innerHTML = '';
        }
    }

    function initDirDataTable() {
        destroyDirTable();

        var tableEl = document.getElementById('tabla-dia-doble');
        if (!tableEl) return;

        dirTableInstance = $(tableEl).DataTable({
            processing: false,
            serverSide: false,
            deferRender: true,
            autoWidth: false,
            stateSave: false,
            order: [[0, 'asc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json',
            },
            ajax: {
                url: '/departamento-operativo/recursos-humanos/dia-doble/' + idYear + '/data',
                type: 'GET',
                dataSrc: function (json) {
                    permisos = json.permisos || {};
                    return json.data;
                }
            },
            columnDefs: [
                { orderable: false, targets: [5, 6, 7, 8] },
                { searchable: false, targets: [5, 6, 7, 8] }
            ],
            columns: [
{ 
    data: null, 
    title: '#', 
    width: '48px', 
    className: 'text-center align-middle fw-bold',
    render: function (data, type, row, meta) { 
        return '00' + (meta.row + 1); 
    } 
},
                { data: 'fecha_creacion', title: 'Fecha de creación', className: 'align-middle' },
                { data: 'quincena', title: 'No. Quincena', className: 'text-center align-middle', width: '96px' },
                { data: 'inicio_quincena', title: 'Del', className: 'align-middle' },
                { data: 'fin_quincena', title: 'Al', className: 'align-middle' },
                { data: null, title: '<i class="ti ti-message fs-7"></i>', className: 'text-center align-middle position-relative', width: '20px', orderable: false, searchable: false,
                  render: function (data, type, row) { return comentariosIcon(row); } },
                { data: null, title: 'Firmar', className: 'text-center align-middle', width: '20px', orderable: false, searchable: false,
                  render: function (d) { return firmaIcon(d); } },
                { data: 'status_label', title: 'Estatus', className: 'text-center align-middle', searchable: true,
                  render: function (v, t, d) { return statusBadge(v, t, d); } },
                { data: null, title: '<i class="ti ti-dots-vertical fs-5"></i>', width: '20px', orderable: false, searchable: false, className: 'text-center align-middle',
                  render: function (data, type, row) { return renderAcciones(row); } }
            ],
            rowCallback: function (row, data) {
                var colors = { 0: '#ffb6af', 1: '#fcfcda', 2: '#fcfcda', 3: '#b0f2c2' };
                $(row).css('background-color', colors[data.status] || '#ffffff');
            },
            drawCallback: function () {
                $('#tabla-dia-doble [data-bs-toggle="dropdown"]').each(function () {
                    new bootstrap.Dropdown(this, { popperConfig: { strategy: 'fixed' } });
                });
            }
        });
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function formatearFechaCorta(fecha) {
        if (!fecha) return '';
        var parts = fecha.split('-');
        if (parts.length !== 3) return fecha;
        var day = parseInt(parts[2], 10);
        var monthNames = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        var month = parseInt(parts[1], 10);
        return day + ' ' + (monthNames[month] || '');
    }

    function buildEstacionesTables(estaciones) {
        var container = document.getElementById('dd-estaciones-tables');
        if (!container) return;
        container.innerHTML = '';

        if (!estaciones || estaciones.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4">Sin información</div>';
            return;
        }

        estaciones.forEach(function (est) {
            var wrapper = document.createElement('div');
            wrapper.className = 'mb-4';

            var tableHtml = '<div class="table-responsive">';
            tableHtml += '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">';
            tableHtml += '<thead>';
            tableHtml += '<tr class="title-table-bg"><th class="text-start align-middle" colspan="4">' + escHtml(est.nombre) + '</th></tr>';
            tableHtml += '<tr>';
            tableHtml += '<th class="text-center align-middle" width="48px">No.</th>';
            tableHtml += '<th class="text-start align-middle">Nombre</th>';
            tableHtml += '<th class="text-center align-middle">Puesto</th>';
            tableHtml += '<th class="text-center align-middle">Día doble</th>';
            tableHtml += '</tr>';
            tableHtml += '</thead>';
            tableHtml += '<tbody class="bg-white">';

            if (est.empleados && est.empleados.length > 0) {
                var num = 1;
                est.empleados.forEach(function (emp) {
                    var diasCount = emp.dias_dobles ? emp.dias_dobles.length : 0;
                    if (diasCount > 0) {
                        emp.dias_dobles.forEach(function (dia, idx) {
                            tableHtml += '<tr>';
                            if (idx === 0) {
                                tableHtml += '<th rowspan="' + diasCount + '" class="text-center align-middle">' + num + '</th>';
                                tableHtml += '<td rowspan="' + diasCount + '" class="text-start align-middle">' + escHtml(emp.nombre) + '</td>';
                                tableHtml += '<td rowspan="' + diasCount + '" class="text-center align-middle">' + escHtml(emp.puesto_nombre) + '</td>';
                                num++;
                            }
                            tableHtml += '<th class="text-center align-middle fw-normal">' + escHtml(formatearFechaCorta(dia)) + '</th>';
                            tableHtml += '</tr>';
                        });
                    } else {
                        tableHtml += '<tr>';
                        tableHtml += '<th class="text-center align-middle">' + num + '</th>';
                        tableHtml += '<td class="text-start align-middle">' + escHtml(emp.nombre) + '</td>';
                        tableHtml += '<td class="text-center align-middle">' + escHtml(emp.puesto_nombre) + '</td>';
                        tableHtml += '<th class="text-center align-middle text-secondary">No tiene día doble</th>';
                        tableHtml += '</tr>';
                        num++;
                    }
                });
            } else {
                tableHtml += '<tr><th colspan="4" class="text-secondary text-center">No se encontró información para mostrar</th></tr>';
            }

            tableHtml += '</tbody></table></div>';

            wrapper.innerHTML = tableHtml;
            container.appendChild(wrapper);
        });
    }

    function loadEstacionesData() {
        var semanaSel = document.getElementById('dd-semana-selector');
        var semana = semanaSel ? parseInt(semanaSel.value) || 1 : 1;

        var loadingEl = document.getElementById('dd-estaciones-loading');
        var contentEl = document.getElementById('dd-estaciones-content');

        if (loadingEl) loadingEl.style.display = '';
        if (contentEl) contentEl.style.display = 'none';

        axios.get('/departamento-operativo/recursos-humanos/dia-doble/' + idYear + '/data-estaciones', {
            params: { semana: semana }
        })
        .then(function (res) {
            var json = res.data;
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = '';

            if (!json.success) {
                Notify.error(json.message || 'Error al cargar datos.');
                return;
            }

            var weekTitleEl = document.getElementById('dd-week-title');
            if (weekTitleEl && json.weekTitle) {
                weekTitleEl.textContent = json.weekTitle;
            }

            buildEstacionesTables(json.estaciones);
        })
        .catch(function () {
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = '';
            Notify.error('Error de conexión.');
        });
    }

    function downloadPdfIframe(url) {
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        setTimeout(function () {
            if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 30000);
    }

    window.diaDobleLoadEstaciones = function () { loadEstacionesData(); };
    window.diaDobleInitDireccion = function () { initDirDataTable(); };
    window.diaDobleReload = function () {
        if (dirTableInstance) dirTableInstance.ajax.reload(null, false);
    };
    window.diaDobleCleanup = function () {
        destroyDirTable();
        var container = document.getElementById('dd-estaciones-tables');
        if (container) container.innerHTML = '';
        var loadingEl = document.getElementById('dd-estaciones-loading');
        var contentEl = document.getElementById('dd-estaciones-content');
        if (loadingEl) loadingEl.style.display = 'none';
        if (contentEl) contentEl.style.display = '';
    };
    window.diaDobleDownloadPdf = function (url) { downloadPdfIframe(url); };

    var semanaSelector = document.getElementById('dd-semana-selector');
    if (semanaSelector) {
        semanaSelector.addEventListener('change', function () {
            var semana = parseInt(this.value) || 1;
            axios.post('/departamento-operativo/recursos-humanos/dia-doble/guardar-contexto', {
                semana: semana,
                id_year: idYear
            });
            loadEstacionesData();
        });
    }

    var btnPdf = document.getElementById('btn-dd-pdf-estaciones');
    if (btnPdf) {
        btnPdf.addEventListener('click', function () {
            var semanaSel = document.getElementById('dd-semana-selector');
            var semana = semanaSel ? parseInt(semanaSel.value) || 1 : 1;
            var url = '/departamento-operativo/recursos-humanos/dia-doble/' + idYear + '/pdf-estaciones?semana=' + semana;
            downloadPdfIframe(url);
        });
    }

    $('#tabla-dia-doble tbody').on('click', '[data-action]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $el = $(this);
        if ($el.hasClass('disabled')) return;

        var action = $el.data('action');
        var id = parseInt($el.data('id'));

        if (action === 'detalle' && typeof diaDobleActions.verDetalle === 'function') {
            diaDobleActions.verDetalle(id);
        } else if (action === 'editar' && typeof diaDobleActions.editar === 'function') {
            diaDobleActions.editar(id);
        } else if (action === 'comentarios' && typeof diaDobleActions.verComentarios === 'function') {
            diaDobleActions.verComentarios(id);
        } else if (action === 'pdf-direccion' && typeof diaDobleActions.descargarPdf === 'function') {
            diaDobleActions.descargarPdf(id);
        } else if (action === 'eliminar' && typeof diaDobleActions.eliminarReporte === 'function') {
            diaDobleActions.eliminarReporte(id);
        }
    });

});
