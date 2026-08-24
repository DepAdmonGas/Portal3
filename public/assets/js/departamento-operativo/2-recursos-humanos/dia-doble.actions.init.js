var diaDobleActions = {};

function diaDobleComponent() {
    var c = document.getElementById('container');

    return {
        idYear: c ? parseInt(c.dataset.idYear || '0') : 0,
        currentQuincena: c ? parseInt(c.dataset.currentQuincena || '0') : 0,
        puedeCrear: c ? c.dataset.puedeCrear === 'true' : false,
        puedeEditar: c ? c.dataset.puedeEditar === 'true' : false,
        puedeEliminar: c ? c.dataset.puedeEliminar === 'true' : false,
        idUsuario: c ? parseInt(c.dataset.idUsuario || '0') : 0,
        creando: false,
        comentarios: [],
        comentarioReporteId: null,
        nuevoComentario: '',
        guardandoComentario: false,
        cargandoComentarios: false,

        init() {
            var self = this;
            diaDobleActions.verDetalle = function (id) { self.verDetalle(id); };
            diaDobleActions.editar = function (id) { self.irFormulario(id); };
            diaDobleActions.verComentarios = function (id) { self.abrirComentarios(id); };
            diaDobleActions.eliminarReporte = function (id) { self.eliminarReporte(id); };
            diaDobleActions.descargarPdf = function (id) { self.descargarPdfDireccion(id); };
        },

        initSection() {
            var self = this;
            if (typeof sessionStorage === 'undefined') return;

            var tipo = sessionStorage.getItem('dd_tipo');
            if (!tipo) return;

            var attempts = 0;
            var maxAttempts = 20;
            var interval = setInterval(function () {
                attempts++;
                var ready = (tipo === '1' && typeof diaDobleLoadEstaciones === 'function') ||
                            (tipo === '2' && typeof diaDobleInitDireccion === 'function');

                if (ready || attempts >= maxAttempts) {
                    clearInterval(interval);
                    if (tipo === '1') self.showEstaciones();
                    else if (tipo === '2') self.showDireccion();
                }
            }, 50);
        },

        cambiarYearMes(year) {
            var template = this.$el ? this.$el.dataset.yearMesTemplate : '';
            if (template) {
                window.location.href = template.replace('{year}', year);
            } else {
                window.location.href = '/departamento-operativo/recursos-humanos/dia-doble/' + year;
            }
        },

        showCards() {
            if (typeof diaDobleCleanup === 'function') diaDobleCleanup();
            document.getElementById('dd-section-cards').style.display = '';
            document.getElementById('dd-section-estaciones').style.display = 'none';
            document.getElementById('dd-section-direccion').style.display = 'none';
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem('dd_tipo');
            }
        },

        showEstaciones() {
            if (typeof diaDobleCleanup === 'function') diaDobleCleanup();
            document.getElementById('dd-section-cards').style.display = 'none';
            document.getElementById('dd-section-estaciones').style.display = '';
            document.getElementById('dd-section-direccion').style.display = 'none';
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.setItem('dd_tipo', '1');
            }
            if (typeof diaDobleLoadEstaciones === 'function') diaDobleLoadEstaciones();
        },

        showDireccion() {
            if (typeof diaDobleCleanup === 'function') diaDobleCleanup();
            document.getElementById('dd-section-cards').style.display = 'none';
            document.getElementById('dd-section-estaciones').style.display = 'none';
            document.getElementById('dd-section-direccion').style.display = '';
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.setItem('dd_tipo', '2');
            }
            if (typeof diaDobleInitDireccion === 'function') diaDobleInitDireccion();
        },

        crearReporte() {
            var self = this;
            if (self.creando) return;
            self.creando = true;

            axios.post('/departamento-operativo/recursos-humanos/dia-doble/' + self.idYear + '/add', {
                quincena: self.currentQuincena || 1
            })
            .then(function (res) {
                var json = res.data;
                if (json.success && json.id) {
                    window.location.href = '/departamento-operativo/recursos-humanos/dia-doble-registro/' + json.id;
                } else {
                    self.creando = false;
                    Notify.error(json.message || 'Error al crear el reporte.');
                }
            })
            .catch(function (err) {
                self.creando = false;
                var resp = err.response;
                if (resp && resp.data && resp.data.pendiente_id) {
                    window.location.href = '/departamento-operativo/recursos-humanos/dia-doble-registro/' + resp.data.pendiente_id;
                    return;
                }
                var msg = (resp && resp.data && resp.data.message) || 'Error de conexión.';
                Notify.error(msg);
            });
        },

        irFormulario(id) {
            window.location.href = '/departamento-operativo/recursos-humanos/dia-doble-registro/' + id;
        },

        verDetalle(idReporte) {
            var bodyEl = document.getElementById('modalDetalleBody');
            if (bodyEl) bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

            var modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
            modal.show();

            axios.get('/departamento-operativo/recursos-humanos/dia-doble/detail', { params: { id: idReporte } })
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

        abrirComentarios(idReporte) {
            var self = this;
            self.comentarioReporteId = idReporte;
            self.nuevoComentario = '';
            self.comentarios = [];
            self.cargandoComentarios = true;

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('modalComentarios')).show();

            axios.get('/departamento-operativo/recursos-humanos/dia-doble/comentarios', { params: { id: idReporte } })
                .then(function (res) {
                    var json = res.data;
                    if (json.success && json.comentarios) {
                        self.comentarios = json.comentarios.map(function (c) {
                            return {
                                id: c.id,
                                id_usuario: c.id_usuario,
                                comentario: c.comentario,
                                esMio: c.id_usuario === self.idUsuario,
                                usuario_nombre: c.usuario_nombre || 'Usuario',
                                fecha_formateada: c.fecha_formateada || ''
                            };
                        });
                    }
                    self.cargandoComentarios = false;
                    self.scrollChatToBottom();
                })
                .catch(function () {
                    self.cargandoComentarios = false;
                    Notify.error('Error al cargar comentarios.');
                });
        },

        scrollChatToBottom() {
            var self = this;
            setTimeout(function () {
                var el = self.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            }, 150);
        },

        async agregarComentario() {
            var self = this;
            if (self.guardandoComentario) return;
            if (!self.nuevoComentario.trim()) return;
            if (!self.comentarioReporteId) return;

            self.guardandoComentario = true;

            try {
                var resp = await axios.post('/departamento-operativo/recursos-humanos/dia-doble/add-comentario', {
                    id_reporte: self.comentarioReporteId,
                    comentario: self.nuevoComentario
                });
                var json = resp.data;

                if (json.success) {
                    self.nuevoComentario = '';
                    var resp2 = await axios.get('/departamento-operativo/recursos-humanos/dia-doble/comentarios', { params: { id: self.comentarioReporteId } });
                    var json2 = resp2.data;
                    if (json2.success && json2.comentarios) {
                        self.comentarios = json2.comentarios.map(function (c) {
                            return {
                                id: c.id,
                                id_usuario: c.id_usuario,
                                comentario: c.comentario,
                                esMio: c.id_usuario === self.idUsuario,
                                usuario_nombre: c.usuario_nombre || 'Usuario',
                                fecha_formateada: c.fecha_formateada || ''
                            };
                        });
                    }
                    self.scrollChatToBottom();
                    if (typeof diaDobleReload === 'function') diaDobleReload();
                    Notify.success('Comentario agregado.');
                } else {
                    Notify.error(json.message || 'Error al agregar comentario.');
                }
            } catch (e) {
                Notify.error('Error al agregar comentario.');
            } finally {
                self.guardandoComentario = false;
            }
        },

        async eliminarReporte(id) {
            await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/dia-doble/delete',
                id: id,
                name: 'Reporte # 00' + id,
                table: '#tabla-dia-doble'
            });
        },

        descargarPdfDireccion(idReporte) {
            if (typeof diaDobleDownloadPdf === 'function') {
                diaDobleDownloadPdf('/departamento-operativo/recursos-humanos/dia-doble/' + this.idYear + '/pdf-direccion?id=' + idReporte);
            } else {
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = '/departamento-operativo/recursos-humanos/dia-doble/' + this.idYear + '/pdf-direccion?id=' + idReporte;
                document.body.appendChild(iframe);
                setTimeout(function () { if (iframe.parentNode) iframe.parentNode.removeChild(iframe); }, 30000);
            }
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

    html += '<div class="modal-header bg-primary">';
    html += '<h5 class="modal-title text-white"><i class="ti ti-eye"></i> Detalle Días Dobles</h5>';
    html += '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>';
    html += '</div>';

    html += '<div class="modal-body pb-0">';
    html += '<div class="row">';

    html += '<div class="col-12 mb-3 text-end"><strong>Estatus:</strong> ';
    var statusClass = data.status === 0 ? 'bg-danger' : (data.status === 1 || data.status === 2 ? 'bg-warning' : (data.status === 3 ? 'bg-success' : 'bg-success'));
    html += '<span class="badge ' + statusClass + '">' + escHtml(data.status_label) + '</span>';
    html += '</div>';

    html += '<div class="col-12 mb-3">';
    if (data.status === 3) {
        html += '<div class="alert alert-success border-0 text-center py-3 mb-0">';
        html += '<i class="ti ti-circle-check fs-6 me-1"></i> El reporte de Días Dobles ha sido <strong>Autorizado</strong>';
        html += '</div>';
    } else if (data.status === 0 || data.status === 1) {
        html += '<div class="alert alert-warning border-0 text-center py-3 mb-0">';
        html += '<i class="ti ti-signature me-1 fs-6 me-1"></i> Falta la firma del <strong>Visto Bueno</strong>';
        html += '</div>';
    } else if (data.status === 2) {
        html += '<div class="alert alert-warning border-0 text-center py-3 mb-0">';
        html += '<i class="ti ti-signature me-1 fs-6 me-1"></i> Falta la firma de <strong>Autorización</strong>';
        html += '</div>';
    }

    
    html += '</div>';

    html += '<div class="col-12 text-end mb-3">';
    html += '<b>No. de Folio:</b> 00' + escHtml(data.id);
    html += '<p>' + escHtml(data.fecha_formateada) + '</p>';
    html += '</div>';

    html += '<div class="col-12">';
    html += '<b>Lic. Alejandro Guzmán</b>';
    html += '<br>';
    html += '<p><b>Departamento de Recursos Humanos</b></p>';
    html += '<p>Buenos días, Por medio de la presente, les informo sobre los días dobles asignados al personal del Departamento de Dirección de Operaciones, correspondientes a la <b>Quincena No. ' + escHtml(data.quincena) + '</b>,';
    html += ' que abarca del <b>' + escHtml(data.inicio_quincena) + '</b>';
    html += ' al <b>' + escHtml(data.fin_quincena) + '</b>';
    html += ' <br><br> A continuación, detallo la información para cada uno de los colaboradores:';
    html += '</p>';
    html += '</div>';

    html += '<div class="col-12">';
    html += '<div class="table-responsive mb-4">';
    html += '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%">';
    html += '<thead>';
    html += '<tr>';
    html += '<th class="align-middle text-center">#</th>';
    html += '<th class="align-middle text-center">Empleado</th>';
    html += '<th class="align-middle text-center">Día Doble</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody class="bg-light">';

    if (data.empleados.length > 0) {
        data.empleados.forEach(function (emp, idx) {
            html += '<tr>';
            html += '<th class="align-middle text-center">' + (idx + 1) + '</th>';
            html += '<td class="align-middle text-center">' + escHtml(emp.nombre) + '</td>';
            html += '<td class="align-middle text-center">' + escHtml(emp.fecha_label || 'S/I') + '</td>';
            html += '</tr>';
        });
    } else {
        html += '<tr><th colspan="3" class="text-center text-secondary fw-normal"><small>No se encontró información para mostrar</small></th></tr>';
    }

    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    html += '</div>';

    html += '<div class="col-12 text-center"><p>Sin más por el momento quedo de usted.</p></div>';

    html += '<hr>';

    html += '<label class="form-label mb-3">Firmas:</label>';

    html += '<div class="row">';

    var firmaA = null, firmaB = null, firmaC = null;
    if (data.firmas) {
        data.firmas.forEach(function (f) {
            if (f.tipo_firma === 'A') firmaA = f;
            else if (f.tipo_firma === 'B') firmaB = f;
            else if (f.tipo_firma === 'C') firmaC = f;
        });
    }

    function renderCardA(f) {
        var h = '<div class="col-xl-4 col-lg-6 col-md-6 mb-3">';
        if (f) {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-user-check fs-5"></i></div>';
            h += '<div class="ms-3 overflow-hidden"><h6 class="mb-0 text-white">' + escHtml(f.tipo_label) + '</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
            if (f.tipo_firma === 'A' && f.es_imagen) {
                h += '<img src="' + escHtml(f.firma_img_url || '/download?tipo=dia-doble-firma&file=' + encodeURIComponent(f.firma)) + '" class="img-fluid" style="max-height:90px;object-fit:contain;">';
            } else {
                h += '<i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>';
                h += '<small class="text-dark">' + escHtml(f.fecha_label || '') + ', ' + escHtml(f.hora_label || '') + '</small>';
            }
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate">' + escHtml(f.usuario_nombre) + '</h6></div>';
            h += '</div>';
        } else {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-clock-hour-4 fs-5"></i></div>';
            h += '<div class="ms-3"><h6 class="mb-0 text-white">NOMBRE Y FIRMA DE QUIEN ELABORÓ</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
            h += '<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>';
            h += '<h6 class="text-muted mb-0">Sin firma registrada</h6>';
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de firma</small></div>';
            h += '</div>';
        }
        h += '</div>';
        return h;
    }

    function renderCardBC(f, tipo, titulo) {
        var h = '<div class="col-xl-4 col-lg-6 col-md-6 mb-3">';
        if (f) {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-user-check fs-5"></i></div>';
            h += '<div class="ms-3 overflow-hidden"><h6 class="mb-0 text-white">' + escHtml(f.tipo_label) + '</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
            h += '<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>';
            h += '<small class="text-dark">' + (f.firma_texto || '') + '</small>';
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate">' + escHtml(f.usuario_nombre) + '</h6></div>';
            h += '</div>';
        } else {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-clock-hour-4 fs-5"></i></div>';
            h += '<div class="ms-3"><h6 class="mb-0 text-white">' + titulo + '</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">';
            h += '<i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>';
            h += '<h6 class="text-muted mb-0">¡Falta la firma de ' + titulo.replace('VO.BO.', 'Vo.Bo.').replace('AUTORIZACIÓN', 'Autorización') + '!</h6>';
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de firma electrónica</small></div>';
            h += '</div>';
        }
        h += '</div>';
        return h;
    }

    html += renderCardA(firmaA);
    html += renderCardBC(firmaB, 'B', 'VO.BO.');
    html += renderCardBC(firmaC, 'C', 'AUTORIZACIÓN');

    html += '</div>';

    html += '</div>';

    html += '</div>';

    html += '<div class="modal-footer">';
    html += '<button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"> <i class="ti ti-x"></i> Cerrar </button>';
    html += '</div>';

    container.innerHTML = html;
}
