var rolComodinesActions = {};

function rolComodinesComponent() {
    var c = document.getElementById('container');

    return {
        puedeCrear: c ? c.dataset.puedeCrear === 'true' : false,

        init() {
            var self = this;
            rolComodinesActions.verDetalle = function (id) { self.verDetalle(id); };
            rolComodinesActions.descargarPDF = function (id) { self.descargarPDF(id); };
            rolComodinesActions.eliminarRol = function (id) { self.eliminarRol(id); };
        },

        agregarRol() {
            axios.post('/departamento-operativo/recursos-humanos/rol-comodines/add')
                .then(function (res) {
                    var json = res.data;
                    if (json.success && json.id) {
                        window.location.href = '/departamento-operativo/recursos-humanos/rol-comodines/' + json.id;
                    } else {
                        Notify.error(json.message || 'Error al crear el rol.');
                    }
                })
                .catch(function () { Notify.error('Error de conexión.'); });
        },

        verDetalle(id) {
            var bodyEl = document.getElementById('modalDetalleBody');
            if (bodyEl) bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

            var modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
            modal.show();

            axios.get('/departamento-operativo/recursos-humanos/rol-comodines/detail', { params: { id: id } })
                .then(function (res) {
                    var json = res.data;
                    if (!json.success || !json.empleados) {
                        if (bodyEl) bodyEl.innerHTML = '<div class="alert alert-danger">' + escHtml(json.message || 'Error al cargar los datos.') + '</div>';
                        return;
                    }
                    renderDetalleModal(json, bodyEl);
                })
                .catch(function (err) {
                    if (bodyEl) bodyEl.innerHTML = '<div class="alert alert-danger">Error de conexión: ' + escHtml(err.message) + '</div>';
                });
        },

        descargarPDF(id) {
            window.open('/departamento-operativo/recursos-humanos/rol-comodines/pdf/' + id, '_blank');
        },

        eliminarRol(id) {
            var self = this;

            self.deleteAction({
                url: '/departamento-operativo/recursos-humanos/rol-comodines/delete',
                id: id,
                name: 'Rol #' + id,
                table: null
            }).then(function (res) {
                if (res && res.success) {
                    if (typeof rolComodinesReload === 'function') rolComodinesReload();
                }
            });
        }
    };
}

function escHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function renderDetalleModal(data, container) {
    var html = '';
    var statusCls = data.status == 1 ? 'bg-success' : 'bg-danger';
    var statusLabel = data.status == 1 ? 'Finalizado' : 'Pendiente';
    var fechaInicio = data.fecha_inicio_label || 'S/I';
    var fechaFin = data.fecha_fin_label || 'S/I';

    html += '<div class="row mb-3">';
    html += '<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-3">';
    html += '<div class="card border-0 shadow-sm h-100">';
    html += '<div class="card-body text-center">';
    html += '<small class="text-secondary fw-bold d-block mb-1">ROL #</small>';
    html += '<span class="fs-5 fw-bold text-primary">' + escHtml(data.id) + '</span>';
    html += '</div></div></div>';

    html += '<div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-3">';
    html += '<div class="card border-0 shadow-sm h-100">';
    html += '<div class="card-body text-center">';
    html += '<small class="text-secondary fw-bold d-block mb-1">ESTADO</small>';
    html += '<span class="badge ' + statusCls + ' fs-6">' + escHtml(statusLabel) + '</span>';
    html += '</div></div></div>';

    html += '<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">';
    html += '<div class="card border-0 shadow-sm h-100">';
    html += '<div class="card-body text-center">';
    html += '<small class="text-secondary fw-bold d-block mb-1">PERÍODO</small>';
    html += '<span class="fw-semibold">' + escHtml(fechaInicio) + ' <span class="text-muted">al</span> ' + escHtml(fechaFin) + '</span>';
    html += '</div></div></div>';

    html += '</div>';

    html += '<div class="card border-0 shadow-sm">';
    html += '<div class="card-header bg-light">';
    html += '<h6 class="mb-0 fw-bold text-secondary"><i class="ti ti-table me-2"></i>Asignación por empleado</h6>';
    html += '</div>';
    html += '<div class="card-body p-0">';
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered mb-0" style="font-size:.8em;">';
    html += '<thead class="table-dark"><tr>';
    html += '<th class="text-center align-middle" width="50">#</th>';
    html += '<th class="align-middle">Nombre completo</th>';
    html += '<th class="text-center align-middle">Lunes</th>';
    html += '<th class="text-center align-middle">Martes</th>';
    html += '<th class="text-center align-middle">Miércoles</th>';
    html += '<th class="text-center align-middle">Jueves</th>';
    html += '<th class="text-center align-middle">Viernes</th>';
    html += '<th class="text-center align-middle">Sábado</th>';
    html += '<th class="text-center align-middle">Domingo</th>';
    html += '</tr></thead>';
    html += '<tbody>';

    var dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    data.empleados.forEach(function (emp, idx) {
        html += '<tr>';
        html += '<td class="text-center align-middle fw-bold">' + (idx + 1) + '</td>';
        html += '<td class="align-middle fw-semibold">' + escHtml(emp.nombre) + '</td>';

        dias.forEach(function (dia) {
            var val = (data.asignaciones[emp.id] && data.asignaciones[emp.id][dia]) || 0;
            var label = 'S/I';
            var cellCls = 'text-center align-middle';

            if (val === 400) {
                label = 'Descanso';
                cellCls += ' fw-semibold text-success';
            } else if (val > 0) {
                var est = data.estaciones.find(function (e) { return e.id === val; });
                label = est ? est.nombre : 'S/I';
                cellCls += ' fw-semibold';
            } else {
                cellCls += ' text-muted';
            }

            html += '<td class="' + cellCls + '">' + label + '</td>';
        });

        html += '</tr>';
    });

    html += '</tbody></table></div></div></div>';

    container.innerHTML = html;
}
