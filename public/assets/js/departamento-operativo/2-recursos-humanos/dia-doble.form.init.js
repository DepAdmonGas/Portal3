function diaDobleForm() {
    var c = document.getElementById('container');

    return {
        idReporte: c ? parseInt(c.dataset.idReporte || '0') : 0,
        idYear: c ? parseInt(c.dataset.year || '0') : 0,
        status: c ? parseInt(c.dataset.status || '0') : 0,
        puedeEditar: c ? c.dataset.puedeEditar === 'true' : false,
        guardando: false,
        guardandoQuincena: false,
        guardandoPersonal: false,
        signaturePad: null,
        tipoFirmaToken: '',

        initForm() {
            var self = this;
            if (self.status === 0 && self.puedeEditar && typeof SignaturePad !== 'undefined') {
                setTimeout(function () {
                    var cv = document.getElementById('firma-canvas-form');
                    if (cv) {
                        var r = Math.max(window.devicePixelRatio || 1, 1);
                        cv.width = cv.offsetWidth * r;
                        cv.height = cv.offsetHeight * r;
                        cv.getContext('2d').scale(r, r);
                        self.signaturePad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
                    }

                    var btnLimpiar = document.getElementById('btn-limpiar-firma-form');
                    if (btnLimpiar) {
                        btnLimpiar.addEventListener('click', function () {
                            if (self.signaturePad) self.signaturePad.clear();
                        });
                    }
                }, 300);
            }

        },

        abrirModalAgregarPersonal() {
            var self = this;
            var select = document.getElementById('personalSelect');
            var fechaInput = document.getElementById('fechaDiaDoble');
            if (select) {
                select.innerHTML = '<option value="">Cargando...</option>';
                select.disabled = true;
            }
            if (fechaInput) fechaInput.value = '';

            axios.get('/departamento-operativo/recursos-humanos/dia-doble/personal-direccion')
                .then(function (res) {
                    var json = res.data;
                    if (json.success && json.personal) {
                        var options = '<option value="">Selecciona una opcion...</option>';
                        json.personal.forEach(function (p) {
                            options += '<option value="' + p.id + '">' + escHtmlF(p.nombre_completo) + '</option>';
                        });
                        select.innerHTML = options;
                        select.disabled = false;
                    }
                })
                .catch(function () {
                    select.innerHTML = '<option value="">Error al cargar</option>';
                    select.disabled = false;
                });

            var modal = new bootstrap.Modal(document.getElementById('modalAgregarPersonal'));
            modal.show();
        },

        guardarPersonal() {
            var self = this;
            var idUsuario = parseInt(document.getElementById('personalSelect').value || '0');
            var fechaDoble = document.getElementById('fechaDiaDoble').value;

            if (!idUsuario) {
                Notify.error('Selecciona un empleado.');
                return;
            }
            if (!fechaDoble) {
                Notify.error('Selecciona la fecha del dia doble.');
                return;
            }

            self.guardandoPersonal = true;

            axios.post('/departamento-operativo/recursos-humanos/dia-doble/add-personal', {
                id_reporte: self.idReporte,
                id_usuario: idUsuario,
                fecha_doble: fechaDoble
            })
            .then(function (res) {
                self.guardandoPersonal = false;
                var json = res.data;
                if (json.success) {
                    Notify.success('Personal agregado correctamente.');
                    bootstrap.Modal.getInstance(document.getElementById('modalAgregarPersonal')).hide();
                    window.location.reload();
                } else {
                    Notify.error(json.message || 'Error al agregar personal.');
                }
            })
            .catch(function () {
                self.guardandoPersonal = false;
                Notify.error('Error de conexion.');
            });
        },

        abrirModalEditarQuincena() {
            var select = document.getElementById('quincenaEditSelect');
            if (select) select.selectedIndex = 0;
            var modal = new bootstrap.Modal(document.getElementById('modalEditarQuincena'));
            modal.show();
        },

        guardarQuincena() {
            var self = this;
            var quincena = parseInt(document.getElementById('quincenaEditSelect').value || '0');

            if (!quincena) {
                Notify.error('Selecciona una quincena.');
                return;
            }

            self.guardandoQuincena = true;

            axios.post('/departamento-operativo/recursos-humanos/dia-doble/edit-quincena', {
                id_reporte: self.idReporte,
                quincena: quincena
            })
            .then(function (res) {
                self.guardandoQuincena = false;
                var json = res.data;
                if (json.success) {
                    Notify.success('Quincena actualizada correctamente.');
                    window.location.reload();
                } else {
                    Notify.error(json.message || 'Error al editar la quincena.');
                }
            })
            .catch(function () {
                self.guardandoQuincena = false;
                Notify.error('Error de conexion.');
            });
        },

        finalizarFormato() {
            var self = this;

            if (!self.signaturePad || self.signaturePad.isEmpty()) {
                Notify.error('Falta agregar la firma.');
                return;
            }

            alerts.confirm('Finalizar', '¿Estas seguro de finalizar el formato?', function () {
                var cv = document.getElementById('firma-canvas-form');
                var base64 = cv.toDataURL();

                self.guardando = true;

                axios.post('/departamento-operativo/recursos-humanos/dia-doble/firmar-firma', {
                    id_reporte: self.idReporte,
                    firma_base64: base64
                })
                .then(function (res) {
                    self.guardando = false;
                    var json = res.data;
                    if (json.success) {
                        Notify.success('Formato finalizado correctamente.');
                        window.location.href = '/departamento-operativo/recursos-humanos/dia-doble/' + self.idYear;
                    } else {
                        Notify.error(json.message || 'Error al finalizar.');
                    }
                })
                .catch(function () {
                    self.guardando = false;
                    Notify.error('Error de conexion.');
                });
            });
        },

        abrirModalFirmaToken(tipoFirma) {
            var self = this;
            self.tipoFirmaToken = tipoFirma;
            var title = document.getElementById('firmaTokenTitle');
            if (title) {
                title.textContent = tipoFirma === 'B' ? 'Firma de VoBO' : 'Firma de Autorizacion';
            }
            var tokenInput = document.getElementById('tokenInput');
            if (tokenInput) tokenInput.value = '';
            var modal = new bootstrap.Modal(document.getElementById('modalFirmaToken'));
            modal.show();
        },

        crearToken(via) {
            var self = this;
            axios.post('/departamento-operativo/recursos-humanos/dia-doble/crear-token', {
                id_reporte: self.idReporte,
                via: via
            })
            .then(function (res) {
                var json = res.data;
                if (json.success) {
                    Notify.success('Token enviado correctamente.');
                } else {
                    Notify.error(json.message || 'Error al crear el token.');
                }
            })
            .catch(function () { Notify.error('Error de conexion.'); });
        },

        firmarToken() {
            var self = this;
            var token = parseInt(document.getElementById('tokenInput').value || '0');

            if (!token) {
                Notify.error('Ingresa el token.');
                return;
            }

            self.guardando = true;

            axios.post('/departamento-operativo/recursos-humanos/dia-doble/firmar', {
                id_reporte: self.idReporte,
                tipo_firma: self.tipoFirmaToken,
                token: token
            })
            .then(function (res) {
                self.guardando = false;
                var json = res.data;
                if (json.success) {
                    Notify.success('Formato firmado correctamente.');
                    window.location.reload();
                } else {
                    Notify.error(json.message || 'Error al firmar.');
                }
            })
            .catch(function () {
                self.guardando = false;
                Notify.error('Error de conexion.');
            });
        },

        firmarTokenDirecto(tipoFirma, inputId) {
            var self = this;
            var el = document.getElementById(inputId);
            var token = el ? parseInt(el.value || '0') : 0;

            if (!token) {
                Notify.error('Ingresa el token.');
                return;
            }

            self.guardando = true;

            axios.post('/departamento-operativo/recursos-humanos/dia-doble/firmar', {
                id_reporte: self.idReporte,
                tipo_firma: tipoFirma,
                token: token
            })
            .then(function (res) {
                self.guardando = false;
                var json = res.data;
                if (json.success) {
                    Notify.success('Formato firmado correctamente.');
                    window.location.reload();
                } else {
                    Notify.error(json.message || 'Error al firmar.');
                }
            })
            .catch(function () {
                self.guardando = false;
                Notify.error('Error de conexion.');
            });
        }
    };
}

function escHtmlF(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}
