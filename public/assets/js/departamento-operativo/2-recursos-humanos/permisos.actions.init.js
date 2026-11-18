function escHtmlPermisos(str) {
    return String(str || '').replace(/[&<>"']/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function formatoCantidadPermisos(valor) {
    var numero = Number(String(valor == null ? '' : valor).replace(/,/g, ''));
    return Number.isFinite(numero)
        ? new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(numero)
        : '';
}

function renderDetallePermiso(data, container) {
    var html = '';

    // ESTATUS
    html += '<div class="row">';
    html += '  <div class="col-12 mb-3 text-end">';
    var statusClass = data.estado === 2 ? 'bg-success' : (data.estado === 1 ? 'bg-warning' : 'bg-danger');
    html += '    <span class="badge rounded-pill ' + statusClass + '">' + escHtmlPermisos(data.estado_label) + '</span>';
    html += '  </div>';
    html += '</div>';

    // DATOS GENERALES
    html += '<div class="row">';
    html += '  <div class="col-md-4 mb-3"><label class="form-label mb-1">Estación / Departamento:</label><div>' + escHtmlPermisos(data.estacion) + '</div></div>';
    html += '  <div class="col-md-8 mb-3"><label class="form-label mb-1">Colaborador:</label><div>' + escHtmlPermisos(data.nombre_colaborador) + '</div></div>';
    
    html += '  <div class="col-md-4 mb-3"><label class="form-label mb-1">Quien cubre:</label><div>' + escHtmlPermisos(data.cubre_nombre) + '</div></div>';
    html += '  <div class="col-md-8 mb-3"><label class="form-label mb-1">Estación de quien cubre:</label><div>' + (data.estacion_cubre_nombre ? ' ' + escHtmlPermisos(data.estacion_cubre_nombre) : '') + '</div></div>';

    html += '  <div class="col-md-4 mb-3"><label class="form-label mb-1">Del:</label><div>' + escHtmlPermisos(data.fecha_inicio_label) + '</div></div>';
    html += '  <div class="col-md-4 mb-3"><label class="form-label mb-1">Al:</label><div>' + escHtmlPermisos(data.fecha_termino_label) + '</div></div>';
    html += '  <div class="col-md-4 mb-3"><label class="form-label mb-1">Días:</label><div>' + escHtmlPermisos(formatoCantidadPermisos(data.dias_tomados)) + '</div></div>';
    
    html += '  <div class="col-12 mb-3"><label class="form-label mb-1">Motivo:</label><div>' + escHtmlPermisos(data.motivo) + '</div></div>';
    if (data.observaciones) {
        html += '  <div class="col-12 mb-3"><label class="form-label mb-1">Observaciones:</label><div>' + escHtmlPermisos(data.observaciones) + '</div></div>';
    }
    html += '</div>'; // CIERRE DE LA ROW DE DATOS

    // TÍTULO FIRMAS
    html += '<div class="row"><div class="col-12"><label class="form-label mb-1">Firmas:</label></div></div>';

    var firmaA = null, firmaB = null, firmaC = null;
    (data.firmas || []).forEach(function (f) {
        if (f.tipo_firma === 'A') firmaA = f;
        else if (f.tipo_firma === 'B') firmaB = f;
        else if (f.tipo_firma === 'C') firmaC = f;
    });

    function cardImagen(f, titulo, icono) {
        // Se añade col-12 col-md-4 para asegurar 3 columnas iguales en pantallas grandes/medianas que abarcan el 100% (4+4+4 = 12)
        var h = '<div class="col-12 col-md-4 mb-3">';
        if (f) {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-user-check fs-5"></i></div>';
            h += '<div class="ms-3 overflow-hidden"><h6 class="mb-0 text-white">' + escHtmlPermisos(f.tipo_label) + '</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:220px;">';
            if (f.es_imagen) {
                h += '<img src="' + escHtmlPermisos(f.firma_img_url) + '" class="img-fluid" style="max-height:90px;object-fit:contain;">';
            } else if (f.tipo_imagen) {
                h += '<i class="ti ti-signature-off text-muted mb-3" style="font-size:70px;"></i>';
                h += '<div class="text-muted">La firma está registrada, pero su imagen no está disponible.</div>';
            } else {
                h += '<i class="ti ti-signature text-primary mb-3" style="font-size:70px;"></i>';
                h += '<div class="text-dark" style="font-size:.9em;">' + (f.firma_texto || '') + '</div>';
            }
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><h6 class="mb-0 fw-semibold text-truncate">' + escHtmlPermisos(f.usuario_nombre) + '</h6>';
            h += '</div>';
            h += '</div>';
        } else {
            h += '<div class="card border h-100">';
            h += '<div class="card-header bg-primary text-white py-3 border-0">';
            h += '<div class="d-flex align-items-center">';
            h += '<div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;"><i class="ti ti-clock-hour-4 fs-5"></i></div>';
            h += '<div class="ms-3"><h6 class="mb-0 text-white">' + escHtmlPermisos(titulo) + '</h6></div>';
            h += '</div></div>';
            h += '<div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4" style="min-height:220px;">';
            h += '<i class="ti ti-signature-off text-muted mb-3" style="font-size:70px;"></i>';
            h += '<h6 class="text-muted mb-0">Sin firma registrada</h6>';
            h += '</div>';
            h += '<div class="card-footer bg-light text-center"><small class="text-muted">Pendiente de firma</small></div>';
            h += '</div>';
        }
        h += '</div>';
        return h;
    }

    // ROW EXCLUSIVA PARA LAS FIRMAS (Suma exactamente 12 columnas: 4 + 4 + 4)
    html += '<div class="row">';
    html += cardImagen(firmaA, 'NOMBRE Y FIRMA DEL SOLICITANTE');
    html += cardImagen(firmaB, 'NOMBRE Y FIRMA DE QUIEN CUBRE');
    html += cardImagen(firmaC, 'NOMBRE Y FIRMA DEL VISTO BUENO');
    html += '</div>';

    container.innerHTML = html;
}

document.addEventListener('alpine:init', () => {

    // ==================== INDEX / LISTA ====================
    Alpine.data('permisosComponent', () => ({
        puedeCrear: false,
        puedeEliminar: false,
        puedeDescargar: false,
        puedeVoBo: false,
        idUsuario: 0,
        esMultiestacion: false,
        contextoEspecifico: false,

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeDescargar = c.dataset.puedeDescargar === 'true';
this.puedeVoBo      = c.dataset.puedeVobo === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
                this.esMultiestacion = c.dataset.multiestacion === 'true';
            }
            this.actualizarContextoUI();

            document.addEventListener('ver-detalle-permiso', (e) => { this.verDetalle(e.detail.id); });
            document.addEventListener('eliminar-permiso', (e) => { this.eliminarPermiso(e.detail.id, e.detail.name); });
            document.addEventListener('tabla-recargada', () => {
                this.actualizarContextoUI();
                this.refrescarPendientes();
            });
            const stationSelector = document.getElementById('module-station-selector-permisos');
            if (stationSelector) {
                stationSelector.addEventListener('change', () => this.actualizarContextoUI());
            }
        },

        actualizarContextoUI() {
            const sel = document.getElementById('module-station-selector-permisos');
            if (sel) {
                this.contextoEspecifico = /^(estacion|depto)_\d+$/.test(String(sel.value || ''));
                return;
            }
            const c = document.getElementById('container');
            this.contextoEspecifico = !!(c && parseInt(c.dataset.idEstacion, 10) > 0);
        },

        irNuevo() { window.location.href = '/departamento-operativo/recursos-humanos/permisos-nuevo'; },
        irFormulario(id) { window.location.href = '/departamento-operativo/recursos-humanos/permisos-editar/' + id; },
        irFirmar(id) { window.location.href = '/departamento-operativo/recursos-humanos/permisos/firmar/' + id; },

async verDetalle(id) {
    const folioEl = document.getElementById('detalle-permiso-folio');
    const estatusEl = document.getElementById('detalle-permiso-estatus'); // Referencia al contenedor del estatus
    const bodyEl = document.getElementById('detalle-permiso-body');

    if (!bodyEl) return;
    
    // Limpiar folio y estatus previos al abrir
    if (folioEl) folioEl.textContent = '';
    if (estatusEl) estatusEl.innerHTML = '';

    bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    const modal = new bootstrap.Modal(document.getElementById('modalDetallePermiso'));
    modal.show();

    try {
        const resp = await axios.get('/departamento-operativo/recursos-humanos/permisos/detalle', { params: { id: id } });
        const json = resp.data;

        if (!json.success || !json.detalle) {
            bodyEl.innerHTML = '<div class="alert alert-danger">' + escHtmlPermisos(json.message || 'Error al cargar los datos.') + '</div>';
            return;
        }

        if (folioEl) folioEl.textContent = '(#' + id + ')';

        // MOLDURA/RENDER DEL ESTATUS A LA DERECHA (Ajusta los nombres según la propiedad de tu JSON)
        if (estatusEl && json.detalle.estatus) {
            estatusEl.innerHTML = `<span class="badge bg-light text-dark fs-6">${escHtmlPermisos(json.detalle.estatus)}</span>`;
        }

        renderDetallePermiso(json.detalle, bodyEl);

    } catch (e) {
        bodyEl.innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
    }
},

        async eliminarPermiso(id, name) {
            await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/permisos/delete',
                id: id,
                name: name,
                table: '#tabla-permisos'
            });
        },

        actualizarBadges(json) {
            const sel = document.getElementById('module-station-selector-permisos');
            if (!sel) return;
            const map = {};
            Object.keys(json).forEach(function (k) {
                if (k.indexOf('estacion_') === 0) map[k] = json[k];
                if (k.indexOf('depto_') === 0) map[k] = json[k];
            });
            Array.from(sel.options).forEach(function (opt) {
                const v = opt.value;
                if (!v) return;
                const count = map[v] || 0;
                const label = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                opt.textContent = count > 0 ? label + ' (' + count + ')' : label;
            });
            const first = sel.options[0];
            if (first && !first.value && typeof json.total !== 'undefined') {
                const base = first.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                first.textContent = json.total > 0 ? base + ' (' + json.total + ')' : base;
            }
        },

        async refrescarPendientes() {
            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/permisos/pendientes');
                const json = await resp.json();
                if (json.success) {
                    this.actualizarBadges(json);
                    const badge = document.getElementById('permisos-pending-count');
                    if (badge) badge.textContent = json.contexto || 0;
                }
            } catch (e) {}
        }
    }));

    // ==================== FORMULARIO (NUEVO / EDITAR) ====================
    Alpine.data('permisosFormComponent', () => ({
        modo: 'nuevo',
        esNuevo: true,
        idEstacion: 0,
        idUsuario: 0,
        estaciones: [],
        estacionesCubre: [],
        personal: [],
        detalle: null,

        colaborador: '',
        estacionCubre: 0,
        cubre: '',
        fechaInicio: '',
        fechaTermino: '',
        motivo: '',
        observaciones: '',
        cargandoCubre: false,
        guardando: false,
        firmaPad: null,
        errores: {
            colaborador: false,
            estacionCubre: false,
            cubre: false,
            fechaInicio: false,
            fechaTermino: false,
            motivo: false,
            firma: false
        },

        init() {
            const c = this.$el;
            this.modo = c.dataset.modo || 'nuevo';
            this.esNuevo = this.modo !== 'editar';
            this.idEstacion = parseInt(c.dataset.idEstacion) || 0;
            this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
            this.estaciones = JSON.parse(c.dataset.estaciones || '[]');
            this.estacionesCubre = JSON.parse(c.dataset.estacionesCubre || '[]');
            this.personal = JSON.parse(c.dataset.personal || '[]');
            if (c.dataset.detalle) {
                try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) { this.detalle = null; }
            }

            if (!this.esNuevo && this.detalle) {
                this.colaborador = this.detalle.id_personal || '';
                this.estacionCubre = this.detalle.estacion_cubre || this.detalle.id_estacion || 0;
                this.cubre = this.detalle.cubre_turno || '';
                this.fechaInicio = this.detalle.fecha_inicio || '';
                this.fechaTermino = this.detalle.fecha_termino || '';
                this.motivo = this.detalle.motivo || '';
                this.observaciones = this.detalle.observaciones || '';
            }

            const self = this;
            this.$el.addEventListener('input', function (event) { self.limpiarErrorCampo(event.target); });
            this.$el.addEventListener('change', function (event) { self.limpiarErrorCampo(event.target); });
            this.$nextTick(function () {
                self.initSelect2();
                self.initFirma();
                if (!self.esNuevo && self.estacionCubre) {
                    self.cargarPersonalCubre(self.estacionCubre, self.cubre, self.detalle.cubre_nombre || '');
                }
            });

            if (window.ModuleStationSelector) {
                ModuleStationSelector.init('permisos', {
                    customReload: function () {
                        document.dispatchEvent(new CustomEvent('permisos-estacion-cambio'));
                    }
                });
            }
            document.addEventListener('permisos-estacion-cambio', function () {
                self.recargarPorSelector();
            });
        },

        onEstacionCubreChange(event) {
            this.cubre = '';
            this.estacionCubre = parseInt(event.target.value) || 0;
            const $sel = $('#select-cubre');
            if (!this.estacionCubre) {
                $sel.empty().append('<option value="">Selecciona...</option>');
                $sel.prop('disabled', true).val('').trigger('change');
                return;
            }
            this.cargarPersonalCubre(this.estacionCubre);
        },

        cargarPersonalCubre(estacion, preseleccion, nombrePreseleccion) {
            const self = this;
            this.cargandoCubre = true;
            this.cubre = '';

            axios.get('/departamento-operativo/recursos-humanos/permisos/personal', { params: { estacion: estacion } })
                .then(function (res) {
                    const json = res.data;
                    if (!json.success) {
                        Notify.error(json.message || 'Error al cargar el personal.');
                        return;
                    }
                    const $sel = $('#select-cubre');
                    $sel.empty().append('<option value="">Selecciona...</option>');
                    var personalCubre = Array.isArray(json.personal) ? json.personal.slice() : [];
                    var preseleccionExiste = personalCubre.some(function (p) {
                        return String(p.id) === String(preseleccion || '');
                    });
                    if (preseleccion && !preseleccionExiste && nombrePreseleccion) {
                        personalCubre.push({ id: preseleccion, nombre: nombrePreseleccion });
                    }
                    personalCubre.forEach(function (p) {
                        $('<option>').val(p.id).text(p.nombre).appendTo($sel);
                    });
                    $sel.prop('disabled', false);
                    self.refrescarSelect2();

                    if (preseleccion) {
                        $sel.val(preseleccion).trigger('change');
                        self.cubre = preseleccion;
                    }
                })
                .catch(function () {
                    Notify.error('Error al cargar el personal.');
                })
                .finally(function () {
                    self.cargandoCubre = false;
                });
        },

        initSelect2() {
            const self = this;
            const $sel = $('#select-cubre');
            if ($sel.length && $.fn && $.fn.select2) {
                $sel.select2({
                    placeholder: 'Selecciona...',
                    allowClear: false,
                    width: '100%'
                });
                $sel.on('select2:select', function () {
                    self.cubre = $sel.val() || '';
                    self.limpiarErrorCampo($sel[0]);
                });
                $sel.on('select2:clear', function () {
                    self.cubre = '';
                    self.limpiarErrorCampo($sel[0]);
                });
            }
            const $col = $('#select-colaborador');
            if ($col.length && $.fn && $.fn.select2) {
                $col.select2({
                    placeholder: 'Selecciona...',
                    allowClear: false,
                    width: '100%'
                });
                $col.on('select2:select', function () {
                    self.colaborador = $col.val() || '';
                    self.limpiarErrorCampo($col[0]);
                });
                $col.on('select2:clear', function () {
                    self.colaborador = '';
                    self.limpiarErrorCampo($col[0]);
                });
                $col.val(self.colaborador || '').trigger('change');
            }
        },

        actualizarSelectColaborador() {
            const $col = $('#select-colaborador');
            if (!$col.length) return;
            $col.empty().append('<option value="">Selecciona...</option>');
            (this.personal || []).forEach(function (p) {
                $('<option>').val(p.id).text(p.nombre).appendTo($col);
            });
            $col.val(this.colaborador || '').trigger('change');
        },

        refrescarSelect2() {
            const $sel = $('#select-cubre');
            if ($sel.length && $.fn && $.fn.select2 && $sel.data('select2')) {
                $sel.trigger('change');
            } else {
                this.initSelect2();
            }
        },

        recargarPorSelector() {
            const self = this;
            let idEst = this.idEstacion;
            if (window.ModuleStationSelector && ModuleStationSelector._instances && ModuleStationSelector._instances['permisos']) {
                const v = ModuleStationSelector._instances['permisos'].getValue();
                idEst = v.id_estacion || v.id_depto || 0;
            }
            idEst = parseInt(idEst) || 0;
            if (idEst === this.idEstacion) return;

            this.idEstacion = idEst;
            this.colaborador = '';
            this.personal = [];

            axios.get('/departamento-operativo/recursos-humanos/permisos/personal', { params: { estacion: idEst } })
                .then(function (res) {
                    const json = res.data;
                    if (json.success) {
                        self.personal = json.personal || [];
                        self.actualizarSelectColaborador();
                    } else {
                        Notify.error(json.message || 'Error al cargar el personal.');
                    }
                })
                .catch(function () {
                    Notify.error('Error al cargar el personal de la estación.');
                });
        },

        get diasCalculados() {
            if (!this.fechaInicio || !this.fechaTermino) return '';
            const ini = new Date(this.fechaInicio + 'T00:00:00');
            const fin = new Date(this.fechaTermino + 'T00:00:00');
            if (isNaN(ini.getTime()) || isNaN(fin.getTime())) return '';
            if (fin < ini) return 'Fechas no válidas';
            return formatoCantidadPermisos(Math.round((fin - ini) / 86400000) + 1);
        },

        get firmaExistente() {
            if (!this.detalle || !this.detalle.firmas) return '';
            const a = this.detalle.firmas.find(function (f) { return f.tipo_firma === 'A'; });
            return a && a.firma_img_url ? a.firma_img_url : '';
        },

        initFirma() {
            const self = this;
            if (typeof SignaturePad === 'undefined') return;
            const canvas = document.getElementById('firma-canvas-form');
            if (canvas) canvas.addEventListener('pointerup', function () { self.limpiarErrorFirma(); });
            setTimeout(function () {
                self._resizeFirma();
                window.addEventListener('resize', function () { self._resizeFirma(); });
            }, 300);
        },

        _resizeFirma() {
            const w = document.getElementById('signature-pad');
            const cv = w ? w.querySelector('canvas') : null;
            if (!cv) return;
            const r = Math.max(window.devicePixelRatio || 1, 1);
            cv.width = cv.offsetWidth * r;
            cv.height = cv.offsetHeight * r;
            cv.getContext('2d').scale(r, r);
            if (this.firmaPad) {
                const data = this.firmaPad.toData();
                this.firmaPad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
                if (data.length > 0) this.firmaPad.fromData(data);
            } else {
                this.firmaPad = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
            }
        },

        limpiarFirma() {
            if (this.firmaPad) this.firmaPad.clear();
            this.errores.firma = false;
            const wrapper = document.getElementById('signature-pad');
            if (wrapper) {
                wrapper.classList.remove('border-danger');
                wrapper.style.borderColor = '';
            }
        },

        limpiarErrorFirma() {
            const wrapper = document.getElementById('signature-pad');
            if (wrapper && this.firmaPad && !this.firmaPad.isEmpty()) {
                this.errores.firma = false;
                wrapper.classList.remove('border-danger');
                wrapper.style.borderColor = '';
            }
        },

        limpiarErrorCampo(campo) {
            if (!campo || !campo.matches || !campo.matches('[data-permisos-obligatorio]')) return;
            const claves = {
                'select-colaborador': 'colaborador',
                'estacion-cubre-permiso': 'estacionCubre',
                'select-cubre': 'cubre',
                'fecha-inicio-permiso': 'fechaInicio',
                'fecha-termino-permiso': 'fechaTermino',
                'motivo-permiso': 'motivo'
            };
            if (claves[campo.id]) this.errores[claves[campo.id]] = false;
            campo.classList.remove('is-invalid');
            campo.closest('.mb-3')?.classList.remove('is-invalid-field');
            const select2 = campo.parentElement && campo.parentElement.querySelector('.select2-container');
            if (select2) {
                const selection = select2.querySelector('.select2-selection');
                if (selection) {
                    selection.classList.remove('is-invalid');
                    selection.style.borderColor = '';
                    selection.style.boxShadow = '';
                }
            }
            if (campo.id === 'fecha-inicio-permiso' || campo.id === 'fecha-termino-permiso') {
                document.getElementById('fecha-inicio-permiso')?.classList.remove('is-invalid');
                document.getElementById('fecha-termino-permiso')?.classList.remove('is-invalid');
                this.errores.fechaInicio = false;
                this.errores.fechaTermino = false;
            }
        },

        marcarCampoInvalido(selector) {
            const campo = this.$el.querySelector(selector);
            if (!campo) return;
            campo.classList.add('is-invalid');
            campo.closest('.mb-3')?.classList.add('is-invalid-field');
            const select2 = campo.parentElement && campo.parentElement.querySelector('.select2-container');
            if (select2) {
                const selection = select2.querySelector('.select2-selection');
                if (selection) {
                    selection.classList.add('is-invalid');
                    selection.style.borderColor = 'var(--bs-form-invalid-border-color, #fa896b)';
                    selection.style.boxShadow = '0 0 0 .25rem rgba(250, 137, 107, .15)';
                }
            }
        },

        validarCamposObligatorios() {
            this.errores = {
                colaborador: false,
                estacionCubre: false,
                cubre: false,
                fechaInicio: false,
                fechaTermino: false,
                motivo: false,
                firma: false
            };
            this.$el.querySelectorAll('[data-permisos-obligatorio].is-invalid').forEach((campo) => {
                campo.classList.remove('is-invalid');
                campo.closest('.mb-3')?.classList.remove('is-invalid-field');
                const select2 = campo.parentElement && campo.parentElement.querySelector('.select2-container');
                const selection = select2 && select2.querySelector('.select2-selection');
                if (selection) {
                    selection.classList.remove('is-invalid');
                    selection.style.borderColor = '';
                    selection.style.boxShadow = '';
                }
            });
            const valorColaborador = document.getElementById('select-colaborador')?.value || '';
            const valorCubre = document.getElementById('select-cubre')?.value || '';
            const campos = [
                ['#select-colaborador', Number(valorColaborador) > 0, 'colaborador'],
                ['#estacion-cubre-permiso', parseInt(this.estacionCubre, 10) > 0, 'estacionCubre'],
                ['#select-cubre', Number(valorCubre) > 0, 'cubre'],
                ['#fecha-inicio-permiso', !!this.fechaInicio, 'fechaInicio'],
                ['#fecha-termino-permiso', !!this.fechaTermino, 'fechaTermino'],
                ['#motivo-permiso', !!String(this.motivo || '').trim(), 'motivo']
            ];
            let valido = true;
            campos.forEach(([selector, tieneValor, clave]) => {
                if (!tieneValor) {
                    this.errores[clave] = true;
                    this.marcarCampoInvalido(selector);
                    valido = false;
                }
            });

            if (this.fechaInicio && this.fechaTermino && new Date(this.fechaTermino + 'T00:00:00') < new Date(this.fechaInicio + 'T00:00:00')) {
                this.errores.fechaInicio = true;
                this.errores.fechaTermino = true;
                this.marcarCampoInvalido('#fecha-inicio-permiso');
                this.marcarCampoInvalido('#fecha-termino-permiso');
                valido = false;
            }

            if (this.esNuevo && (!this.firmaPad || this.firmaPad.isEmpty())) {
                this.errores.firma = true;
                const wrapper = document.getElementById('signature-pad');
                if (wrapper) {
                    wrapper.classList.add('border-danger');
                    wrapper.style.borderColor = 'var(--bs-form-invalid-border-color, #fa896b)';
                }
                valido = false;
            }

            if (!this.idEstacion || !valido) {
                Notify.error('Completa los campos obligatorios.');
                return false;
            }
            return true;
        },

        cancelar() {
            window.location.href = '/departamento-operativo/recursos-humanos/permisos';
        },

        async guardar() {
            const self = this;
            const $col = $('#select-colaborador');
            if ($col.length) this.colaborador = $col.val() || '';
            const $selCubre = $('#select-cubre');
            if ($selCubre.length) this.cubre = $selCubre.val() || '';
            if (!this.validarCamposObligatorios()) return;

            let firma = '';
            if (this.firmaPad && !this.firmaPad.isEmpty()) {
                const cv = document.getElementById('firma-canvas-form');
                firma = cv.toDataURL();
            }
            if (this.guardando) return;
            this.guardando = true;

            const payload = {
                id_estacion: this.idEstacion,
                colaborador: this.colaborador,
                cubre: this.cubre,
                estacion_cubre: this.estacionCubre,
                fecha_inicio: this.fechaInicio,
                fecha_termino: this.fechaTermino,
                motivo: this.motivo,
                observaciones: this.observaciones
            };

            let url = '/departamento-operativo/recursos-humanos/permisos/guardar';
            if (!this.esNuevo) {
                url = '/departamento-operativo/recursos-humanos/permisos/update';
                payload.id = this.detalle.id;
            }
            if (firma) {
                payload.firma = firma;
            }

            try {
                const resp = await axios.post(url, payload);
                const res = resp.data;
                if (res.success) {
                    Notify.success(res.message || 'Permiso registrado.');
                    window.location.href = '/departamento-operativo/recursos-humanos/permisos';
                } else {
                    this.guardando = false;
                    Notify.error(res.message || 'Error al guardar el permiso.');
                }
            } catch (e) {
                this.guardando = false;
                Notify.error((e.response && e.response.data && e.response.data.message) || 'Error al guardar el permiso.');
            }
        }
    }));

    // ==================== FIRMAR ====================
    Alpine.data('permisosFirmarComponent', () => ({
        id: 0,
        estado: 0,
        idUsuario: 0,
        detalle: null,
        token: '',
        botonesDeshabilitados: false,
        segundosRestantes: 0,
        solicitandoToken: false,
        temporizadorToken: null,
        firmando: false,
        firmandoCubre: false,
        firmaPadB: null,
        firmaBInvalida: false,
        tokenInvalido: false,
        puedeFirmarCubre: false,
        puedeFirmarVoBo: false,
        firmaBResizeObserver: null,
        firmaBCanvasSize: null,

        init() {
            const c = this.$el;
            this.id = parseInt(c.dataset.id) || 0;
            this.estado = parseInt(c.dataset.estado) || 0;
            this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
            this.puedeFirmarCubre = c.dataset.puedeFirmarCubre === 'true';
            // El atributo data-puede-firmar-vobo no se convierte en
            // dataset.puedeFirmarVoBo (la capitalización de "VoBo" no coincide).
            // Leerlo por nombre evita que Alpine oculte el flujo del usuario 19.
            this.puedeFirmarVoBo = c.getAttribute('data-puede-firmar-vobo') === 'true';
            if (c.dataset.detalle) {
                try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) { this.detalle = null; }
            }

            const self = this;
            if (typeof SignaturePad !== 'undefined' && this.estado === 0 && this.puedeFirmarCubre) {
                const iniciarCanvas = function () {
                    const cv = document.getElementById('firma-canvas-b');
                    if (!cv) return;

                    const rect = cv.getBoundingClientRect();
                    if (rect.width <= 0 || rect.height <= 0) {
                        window.requestAnimationFrame(iniciarCanvas);
                        return;
                    }

                    const width = rect.width;
                    const height = rect.height;
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const previous = self.firmaBCanvasSize;
                    if (previous && Math.abs(previous.width - width) < 0.5 && Math.abs(previous.height - height) < 0.5 && previous.ratio === ratio) return;

                    const data = self.firmaPadB && !self.firmaPadB.isEmpty()
                        ? self.firmaPadB.toData()
                        : null;

                    if (self.firmaPadB) self.firmaPadB.off();
                    cv.width = Math.round(width * ratio);
                    cv.height = Math.round(height * ratio);
                    cv.getContext('2d').setTransform(cv.width / width, 0, 0, cv.height / height, 0, 0);

                    self.firmaPadB = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)' });
                    if (data && data.length) self.firmaPadB.fromData(data);
                    self.firmaBCanvasSize = { width: width, height: height, ratio: ratio };
                };

                const observarCanvas = function () {
                    const cv = document.getElementById('firma-canvas-b');
                    if (!cv) {
                        window.requestAnimationFrame(observarCanvas);
                        return;
                    }

                    iniciarCanvas();
                    cv.addEventListener('pointerup', function () {
                        if (self.firmaPadB && !self.firmaPadB.isEmpty()) self.firmaBInvalida = false;
                    });

                    if (typeof ResizeObserver !== 'undefined') {
                        self.firmaBResizeObserver = new ResizeObserver(iniciarCanvas);
                        self.firmaBResizeObserver.observe(cv);
                    } else {
                        window.addEventListener('resize', iniciarCanvas);
                    }
                };

                window.requestAnimationFrame(observarCanvas);
            }

            const disableTime = localStorage.getItem('permisos_disableTime');
            if (disableTime) {
                const elapsed = new Date().getTime() - parseInt(disableTime);
                if (elapsed < 30000) {
                    this.iniciarTemporizadorToken(30000 - elapsed);
                } else {
                    localStorage.removeItem('permisos_disableTime');
                }
            }
        },

        iniciarTemporizadorToken(duracion) {
            window.clearInterval(this.temporizadorToken);
            const venceEn = Date.now() + Math.max(0, duracion);
            const actualizar = () => {
                this.segundosRestantes = Math.ceil(Math.max(0, venceEn - Date.now()) / 1000);
                this.botonesDeshabilitados = this.segundosRestantes > 0 || this.solicitandoToken;

                if (this.segundosRestantes <= 0) {
                    window.clearInterval(this.temporizadorToken);
                    this.temporizadorToken = null;
                    this.botonesDeshabilitados = this.solicitandoToken;
                    localStorage.removeItem('permisos_disableTime');
                }
            };

            actualizar();
            if (this.segundosRestantes > 0) {
                this.temporizadorToken = window.setInterval(actualizar, 250);
            }
        },

        firmaDeTipo(tipo) {
            const fs = (this.detalle && this.detalle.firmas) || [];
            return fs.find(function (f) { return f.tipo_firma === tipo; }) || null;
        },

        get firmaA() { return this.firmaDeTipo('A'); },
        get firmaB() { return this.firmaDeTipo('B'); },
        get firmaC() { return this.firmaDeTipo('C'); },

        get alertMensaje() {
            if (this.estado === 2) {
                return { clase: 'alert-success', titulo: '¡Permiso Autorizado!', texto: 'El permiso fue <strong>autorizado</strong> correctamente. Todas las firmas fueron completadas.' };
            }
            if (this.estado === 0 && this.puedeFirmarCubre) {
                return { clase: 'alert-warning', titulo: '¡Firma Pendiente!', texto: 'Para completar el proceso, debe registrar la <strong>firma de quien cubre</strong>.' };
            }
            if (this.estado === 0) {
                return { clase: 'alert-warning', titulo: '¡Firma Pendiente!', texto: 'Es necesario que la persona que <strong>cubre el turno</strong> registre su firma.' };
            }
            if (this.estado === 1 && this.puedeFirmarVoBo) {
                return { clase: 'alert-warning', titulo: '¡Visto Bueno Pendiente!', texto: 'La firma de quien cubre ya fue registrada. <br> Para finalizar, es necesario que firme el <strong>Visto Bueno</strong> mediante su token de seguridad.' };
            }
            return { clase: 'alert-warning', titulo: '¡Visto Bueno Pendiente!', texto: 'La firma de quien cubre ya fue registrada. <br> Es necesario que el responsable de <strong>firmar el Visto Bueno</strong> finalice el proceso.' };
        },

        limpiarFirmaB() {
            if (this.firmaPadB) this.firmaPadB.clear();
            this.firmaBInvalida = false;
        },

        async firmarQuienCubre() {
            const self = this;
            if (!this.firmaPadB || this.firmaPadB.isEmpty()) {
                this.firmaBInvalida = true;
                Notify.error('Completa los campos obligatorios.');
                return;
            }
            this.firmaBInvalida = false;
            if (this.firmandoCubre) return;
            this.firmandoCubre = true;

            const cv = document.getElementById('firma-canvas-b');
            try {
                const resp = await axios.post('/departamento-operativo/recursos-humanos/permisos/firma-quien-cubre', {
                    id: this.id,
                    firma: cv.toDataURL()
                });
                const json = resp.data;
                if (json.success) {
                    Notify.success(json.message || 'Firma registrada.');
                    window.location.reload();
                } else {
                    this.firmandoCubre = false;
                    Notify.error(json.message || 'Error al registrar la firma.');
                }
            } catch (e) {
                this.firmandoCubre = false;
                Notify.error((e.response && e.response.data && e.response.data.message) || 'Error al registrar la firma.');
            }
        },

        async crearToken(via) {
            if (this.botonesDeshabilitados) return;
            this.botonesDeshabilitados = true;
            this.solicitandoToken = true;

            try {
                const resp = await axios.post('/departamento-operativo/recursos-humanos/permisos/crear-token', {
                    id: this.id,
                    via: via
                });
                const json = resp.data;
                if (json.success) {
                    Notify.success(json.message || 'Token generado.');
                    localStorage.setItem('permisos_disableTime', String(Date.now()));
                    this.iniciarTemporizadorToken(30000);
                } else {
                    Notify.error(json.message || 'Error al crear el token.');
                }
            } catch (e) {
                Notify.error('Error al crear el token.');
            } finally {
                this.solicitandoToken = false;
                this.botonesDeshabilitados = this.segundosRestantes > 0;
            }
        },

        async firmarVoBo() {
            const self = this;
            this.token = String(this.token || '').replace(/\D/g, '').slice(0, 6);
            if (this.token.length !== 6) {
                this.tokenInvalido = true;
                Notify.error('Completa los campos obligatorios.');
                return;
            }
            this.tokenInvalido = false;
            if (this.firmando) return;
            this.firmando = true;

            try {
                const resp = await axios.post('/departamento-operativo/recursos-humanos/permisos/firmar', {
                    id: this.id,
                    token: parseInt(this.token) || 0
                });
                const json = resp.data;
                if (json.success) {
                    localStorage.removeItem('permisos_disableTime');
                    Notify.success(json.message || 'Visto bueno firmado.');
                    window.location.reload();
                } else {
                    this.firmando = false;
                    Notify.error(json.message || 'Error al firmar el visto bueno.');
                }
            } catch (e) {
                this.firmando = false;
                const status = e.response && e.response.status;
                const mensajeServidor = e.response && e.response.data && e.response.data.message;
                Notify.error(mensajeServidor || (status === 419
                    ? 'La sesión de seguridad expiró. Actualiza la página e intenta de nuevo.'
                    : status === 401
                        ? 'Tu sesión expiró. Inicia sesión nuevamente.'
                        : 'No fue posible registrar el visto bueno. Intenta generar un token nuevo.'));
            }
        }
    }));
});
