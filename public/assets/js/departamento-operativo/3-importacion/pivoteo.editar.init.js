document.addEventListener('alpine:init', () => {

    Alpine.data('pivoteoEditarComponent', () => ({

        detalle: null,
        id: 0,
        estatus: 0,
        editable: false,
        multiestacion: false,
        inlineEditable: false,
        puedeAgregar: false,
        puedeEditarFecha: false,
        puedeFirmar: false,
        puedePdf: false,
        puedeGmail: false,
        puedeEditarCabecera: false,
        puedeEliminarDetalle: false,
        detalleId: 0,
        correoDefault: 'cambiosdedestinovdm@g500network.com',
        nocontrol: '',
        guardando: false,
        puedeFinalizar: false,
        puedeFinalizarBase: false,
        validacionFinalizacionActiva: false,
        camposFaltantes: [],
        puedeFirmarElaborador: false,
        firmaPad: null,
        firmaInvalida: false,
        reintentosSignaturePad: 0,
        resizeFirmaElaborador: null,
        erroresAgregar: {},
        erroresEstacion: {},
        erroresCorreo: {},

        productos: [],
        tanques: [],
        tads: [],
        unidades: [],
        choferes: [],
        estaciones: [],
        historialCorreos: [],
        filas: [],

        formAgregar: { producto: '', tanque: '', litros: '', tad: '', unidad: '', chofer: '' },
        estacionForm: { id_detalle: 0, categoria: 1, estacion: '', estacionOtro: '', destinoOtro: '' },
        gmailForm: { correo: '', asunto: '', contenido: '' },

        init() {
            const c = this.$el;
            if (c && c.dataset.detalle) {
                try { this.detalle = JSON.parse(c.dataset.detalle); } catch (e) { this.detalle = null; }
            }
            const d = this.detalle || {};

            this.id = parseInt(d.id) || 0;
            this.estatus = parseInt(d.estatus || '0');
            this.inlineEditable = !!d.inlineEditable;
            this.editable = !!d.puedeEditar && this.estatus !== 2;
            this.multiestacion = !!d.multiestacion;
            this.puedeAgregar = !!d.puedeAgregar;
            this.puedeEditarFecha = d.puedeEditarFecha !== undefined
                ? !!d.puedeEditarFecha
                : (!!d.puedeEditar && this.estatus !== 2);
            this.puedeFirmar = !!d.puedeFirmar;
            this.puedePdf = !!d.puedePDF;
            this.puedeGmail = !!d.puedeGmail;
            this.puedeEditarCabecera = !!d.puedeEditarCabecera;
            this.puedeEliminarDetalle = !!d.puedeEliminarDetalle;
            this.puedeFirmarElaborador = !!d.puedeFirmarElaborador;
            this.correoDefault = d.correo_default || 'cambiosdedestinovdm@g500network.com';
            this.nocontrol = d.nocontrol_txt || '';
            this.productos = d.productos || [];
            this.tanques = d.tanques || [];
            this.tads = d.tads || [];
            this.unidades = d.unidades || [];
            this.choferes = d.choferes || [];
            this.estaciones = d.estaciones || [];
            this.historialCorreos = d.historial_correos || [];

            this.filas = Array.isArray(d.filas) ? d.filas : [];
            this.puedeFinalizarBase = !!d.puedeFinalizarBase;
            this.puedeFinalizar = !!d.puedeFinalizar && this.filas.length > 0;
            this.detalleId = this.filas.length > 0 ? (parseInt(this.filas[0].id_detalle) || 0) : 0;

            this.gmailForm.correo = this.correoDefault;
            this.gmailForm.asunto = 'Formato de Pivoteo';
            this.gmailForm.contenido = 'Envió formato de Pivoteo con número de folio: ' + this.nocontrol;

            this.$nextTick(() => {
                this.bindModalSelect2({
                    modalRef: 'modalAgregar',
                    selectRef: 'selUnidadAgregar',
                    wrapperRef: 'wrapUnidadAgregar',
                    model: 'formAgregar.unidad',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'unidadAgregar'
                });
                this.bindModalSelect2({
                    modalRef: 'modalAgregar',
                    selectRef: 'selChoferAgregar',
                    wrapperRef: 'wrapChoferAgregar',
                    model: 'formAgregar.chofer',
                    options: {
                        tags: true,
                        placeholder: 'Selecciona o escribe...',
                        allowClear: true,
                        createTag: (params) => ({
                            id: String(params.term || '').toUpperCase(),
                            text: String(params.term || '').toUpperCase()
                        })
                    },
                    namespace: 'choferAgregar'
                });
                this.bindModalSelect2({
                    modalRef: 'modalEstacion',
                    selectRef: 'selEstacion',
                    wrapperRef: 'wrapEstacion',
                    model: 'estacionForm.estacion',
                    options: {
                        placeholder: 'Selecciona...',
                        allowClear: true
                    },
                    namespace: 'estacionModal'
                });

                this.refreshTableSelect2s();
                this.iniciarSignaturePadElaborador();

                const params = new URLSearchParams(window.location.search);
                if (params.get('gmail') === '1' && this.puedeGmail) {
                    this.abrirGmail();
                }
            });
        },

        opciones(lista, actual) {
            const arr = (lista || []).slice();
            const valor = (actual === null || actual === undefined) ? '' : String(actual);
            if (valor !== '' && arr.indexOf(valor) === -1) {
                arr.unshift(valor);
            }
            return arr;
        },

        formatoLitros(valor) {
            return Number(valor || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        iniciarSignaturePadElaborador() {
            if (!this.puedeFirmarElaborador || this.firmaPad) return;
            const wrapper = document.getElementById('pivoteo-signature-pad');
            const canvas = document.getElementById('pivoteo-signature-canvas');
            if (!wrapper || !canvas) return;
            if (typeof window.SignaturePad === 'undefined') {
                if (this.reintentosSignaturePad++ < 10) {
                    window.setTimeout(() => this.iniciarSignaturePadElaborador(), 100);
                } else {
                    console.error('SignaturePad no disponible para Pivoteo');
                    this.notify('error', 'No se pudo cargar el espacio para la firma. Recarga la página e intenta nuevamente.');
                }
                return;
            }

            this.firmaPad = new window.SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: '#000000',
                minWidth: 1,
                maxWidth: 2.5,
                onEnd: () => { this.firmaInvalida = this.firmaPad.isEmpty(); }
            });
            this.redimensionarFirmaElaborador();
            this.resizeFirmaElaborador = () => this.redimensionarFirmaElaborador();
            window.addEventListener('resize', this.resizeFirmaElaborador);
        },

        redimensionarFirmaElaborador() {
            const canvas = document.getElementById('pivoteo-signature-canvas');
            if (!canvas || !canvas.offsetWidth) return;
            const trazo = this.firmaPad && !this.firmaPad.isEmpty() ? this.firmaPad.toData() : null;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
            if (this.firmaPad && trazo) this.firmaPad.fromData(trazo);
        },

        limpiarFirmaElaborador() {
            if (this.firmaPad) this.firmaPad.clear();
            this.firmaInvalida = false;
        },

        normalizarNumero(valor) {
            const numero = Number(String(valor ?? '').replace(/,/g, '').trim());
            return Number.isFinite(numero) ? numero : 0;
        },

        formatearNumeroInput(evento, modelo, campo) {
            const input = evento.target;
            const original = input.value;
            const cursor = input.selectionStart ?? original.length;
            const antes = original.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
            let normalizado = original.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const punto = normalizado.indexOf('.');
            if (punto !== -1) normalizado = normalizado.slice(0, punto + 1) + normalizado.slice(punto + 1).replace(/\./g, '');
            const partes = normalizado.split('.');
            const entero = partes[0] || '';
            const fraccion = partes.length > 1 ? partes[1] : null;
            const agrupado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const formateado = fraccion === null ? agrupado : agrupado + '.' + fraccion;

            if (modelo === 'formAgregar') this.formAgregar[campo] = normalizado;
            input.value = formateado;

            let siguiente = 0;
            let caracteres = 0;
            while (siguiente < formateado.length && caracteres < antes) {
                if (/[0-9.]/.test(formateado[siguiente])) caracteres++;
                siguiente++;
            }
            input.setSelectionRange(siguiente, siguiente);
        },

        marcarErroresAgregar() {
            const f = this.formAgregar;
            const invalidos = {
                producto: !String(f.producto || '').trim(),
                tanque: !String(f.tanque || '').trim(),
                litros: !(this.normalizarNumero(f.litros) > 0),
                tad: !String(f.tad || '').trim(),
                unidad: !String(f.unidad || '').trim(),
                chofer: !String(f.chofer || '').trim()
            };
            this.erroresAgregar = invalidos;
            return Object.values(invalidos).some(Boolean);
        },

        validarFilasObligatorias() {
            const campos = ['producto_fc', 'tanque_fc', 'litros', 'tad', 'unidad', 'chofer'];
            if (this.multiestacion) campos.push('estacion_fc', 'destino_fc', 'factura_fc', 'estacion_fn', 'factura_fn');
            let invalido = false;
            const faltantes = [];
            const etiquetas = {
                estacion_fc: 'Documentación Facturada (CANCELAR) — Estación',
                destino_fc: 'Documentación Facturada (CANCELAR) — Destino',
                producto_fc: 'Documentación Facturada (CANCELAR) — Producto',
                tanque_fc: 'Documentación Facturada (CANCELAR) — Tanque',
                factura_fc: 'Documentación Facturada (CANCELAR) — Factura',
                litros: 'Documentación Facturada (CANCELAR) — Litros',
                tad: 'Documentación Facturada (CANCELAR) — TAD',
                unidad: 'Documentación Facturada (CANCELAR) — Unidad',
                chofer: 'Documentación Facturada (CANCELAR) — Chofer',
                estacion_fn: 'Documentación a refacturar — Estación',
                factura_fn: 'Documentación a refacturar — Factura'
            };

            this.filas.forEach((fila, indice) => {
                campos.forEach(campo => {
                    const valor = fila[campo];
                    const texto = valor === null || valor === undefined ? '' : String(valor).trim();
                    const falta = campo === 'litros'
                        ? !(this.normalizarNumero(valor) > 0)
                        : campo === 'destino_fc'
                            ? !/^\d+$/.test(texto)
                            : texto === '';
                    const selector = '[data-required-detail="' + Number(fila.id_detalle) + '"][data-required-field="' + campo + '"]';
                    this.$el.querySelectorAll(selector).forEach(elemento => {
                        elemento.classList.toggle('is-invalid', falta);
                        elemento.closest('td')?.classList.toggle('border-danger', falta);
                        if (elemento.matches('select') && window.jQuery) {
                            const contenedor = window.jQuery(elemento).next('.select2-container').find('.select2-selection');
                            contenedor.toggleClass('is-invalid', falta);
                        }
                    });
                    if (falta) {
                        invalido = true;
                        faltantes.push({
                            key: Number(fila.id_detalle) + '-' + campo,
                            etiqueta: 'Línea ' + (indice + 1) + ': ' + (etiquetas[campo] || campo)
                        });
                    }
                });
            });
            const faltaFecha = !String(this.detalle?.fecha || '').trim();
            this.$el.querySelectorAll('[data-required-field="fecha"]').forEach(elemento => {
                elemento.classList.toggle('is-invalid', faltaFecha);
                elemento.closest('td')?.classList.toggle('border-danger', faltaFecha);
            });
            if (faltaFecha) {
                invalido = true;
                faltantes.unshift({ key: 'fecha', etiqueta: 'Fecha' });
            }
            this.camposFaltantes = faltantes;
            return invalido;
        },

        marcarCamposFaltantesServidor(campos) {
            this.$el.querySelectorAll('[data-required-field].is-invalid').forEach(elemento => elemento.classList.remove('is-invalid'));
            this.$el.querySelectorAll('td.border-danger').forEach(elemento => elemento.classList.remove('border-danger'));
            this.$el.querySelectorAll('.select2-selection.is-invalid').forEach(elemento => elemento.classList.remove('is-invalid'));
            const etiquetas = {
                fecha: 'Fecha',
                estacion_fc: 'Documentación Facturada (CANCELAR) — Estación',
                destino_fc: 'Documentación Facturada (CANCELAR) — Destino',
                producto_fc: 'Documentación Facturada (CANCELAR) — Producto',
                tanque_fc: 'Documentación Facturada (CANCELAR) — Tanque',
                factura_fc: 'Documentación Facturada (CANCELAR) — Factura',
                litros: 'Documentación Facturada (CANCELAR) — Litros',
                tad: 'Documentación Facturada (CANCELAR) — TAD',
                unidad: 'Documentación Facturada (CANCELAR) — Unidad',
                chofer: 'Documentación Facturada (CANCELAR) — Chofer',
                estacion_fn: 'Documentación a refacturar — Estación',
                factura_fn: 'Documentación a refacturar — Factura'
            };
            const filas = new Map(this.filas.map((fila, indice) => [Number(fila.id_detalle), indice + 1]));
            this.camposFaltantes = [];
            (Array.isArray(campos) ? campos : []).forEach(error => {
                const id = Number(error.id_detalle) || 0;
                const campo = String(error.campo || '');
                if (!campo || (!id && campo !== 'fecha')) return;
                const selector = campo === 'fecha'
                    ? '[data-required-field="fecha"]'
                    : '[data-required-detail="' + id + '"][data-required-field="' + campo + '"]';
                this.camposFaltantes.push({
                    key: campo === 'fecha' ? 'fecha' : id + '-' + campo,
                    etiqueta: campo === 'fecha'
                        ? 'Fecha'
                        : 'Línea ' + (filas.get(id) || '?') + ': ' + (etiquetas[campo] || campo)
                });
                this.$el.querySelectorAll(selector).forEach(elemento => {
                    elemento.classList.add('is-invalid');
                    elemento.closest('td')?.classList.add('border-danger');
                    if (elemento.matches('select') && window.jQuery) {
                        window.jQuery(elemento).next('.select2-container').find('.select2-selection').addClass('is-invalid');
                    }
                });
            });
            const primerCampo = this.$el.querySelector('[data-required-field].is-invalid');
            if (primerCampo) primerCampo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        refreshTableSelect2s() {
            if (!window.jQuery) return;
            const $ = window.jQuery;
            const guard = (this._sel2Guard = this._sel2Guard || { key: '', t: 0 });
            this.$el.querySelectorAll('select[data-select2-inline]').forEach(sel => {
                const $sel = $(sel);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2('destroy');
                }
                $sel.select2({
                    width: '100%',
                    tags: true,
                    placeholder: 'Selecciona o escribe...',
                    allowClear: true,
                    createTag: (params) => ({
                        id: String(params.term || '').toUpperCase(),
                        text: String(params.term || '').toUpperCase()
                    })
                });
                $sel.next('.select2-container').css('width', '100%');
                $sel.off('change.sel2save').on('change.sel2save', () => {
                    const idDetalle = parseInt(sel.dataset.detalle) || 0;
                    const opcion = parseInt(sel.dataset.opcion) || 0;
                    const valor = String($sel.val() || '');
                    const key = idDetalle + '|' + opcion + '|' + valor;
                    const ahora = Date.now();
                    if (key === guard.key && ahora - guard.t < 300) return;
                    guard.key = key;
                    guard.t = ahora;
                    this.editarCampo(idDetalle, opcion, valor, sel);
                });
            });
        },

        reemplazarFila(fila) {
            if (!fila) return;
            const id = parseInt(fila.id_detalle) || 0;
            const actual = this.filas.find(f => parseInt(f.id_detalle) === id);
            if (actual) {
                Object.assign(actual, fila);
            } else {
                this.filas.push(fila);
            }
            this.puedeFinalizar = this.puedeFinalizarBase && this.filas.length > 0;
            this.$nextTick(() => {
                this.refreshTableSelect2s();
                if (this.validacionFinalizacionActiva) this.validarFilasObligatorias();
            });
        },

        quitarFila(idDetalle) {
            const id = parseInt(idDetalle) || 0;
            const idx = this.filas.findIndex(f => parseInt(f.id_detalle) === id);
            if (idx !== -1) this.filas.splice(idx, 1);
            this.puedeFinalizar = this.puedeFinalizarBase && this.filas.length > 0;
            this.$nextTick(() => this.refreshTableSelect2s());
        },

        async ejecutar(url, data, opciones = {}) {
            const usarLoader = opciones.loader !== false;
            if (usarLoader && this.guardando) return null;
            if (usarLoader) {
                this.guardando = true;
                if (window.loader) window.loader.show();
            }
            try {
                const response = await axios.post(url, data);
                return response.data;
            } catch (err) {
                return {
                    success: false,
                    message: err.response?.data?.message || err.message || 'Error en la solicitud',
                    errors: err.response?.data?.errors || {}
                };
            } finally {
                if (usarLoader) {
                    if (window.loader) window.loader.hide();
                    this.guardando = false;
                }
            }
        },

        mostrarIndicadorGuardado(elemento) {
            const celda = elemento?.closest('td') || elemento?.parentElement;
            if (!celda) return;
            celda.classList.add('pivoteo-save-cell');
            let indicador = celda.querySelector(':scope > .pivoteo-save-indicator');
            if (!indicador) {
                indicador = document.createElement('span');
                indicador.className = 'pivoteo-save-indicator';
                celda.appendChild(indicador);
            }
            indicador.innerHTML = '<i class="ti ti-check"></i> Guardado';
            indicador.classList.remove('d-none');
            window.clearTimeout(indicador._hideTimer);
            indicador._hideTimer = window.setTimeout(() => indicador.classList.add('d-none'), 1800);
        },

        manejar(res, opciones = {}) {
            if (!res) return false;
            if (res.success) {
                if (res.message) {
                    if (opciones.alerta !== false) {
                        this.showAlert('success', 'Correcto', res.message);
                    }
                    if (opciones.notificar !== false) this.notify('success', res.message);
                }
                return true;
            }
            const mensaje = res.message || 'Error en la solicitud';
            this.showAlert('error', 'Error', mensaje);
            this.notify('error', mensaje);
            return false;
        },

        resetAgregar() {
            this.formAgregar = { producto: '', tanque: '', litros: '', tad: '', unidad: '', chofer: '' };
            this.erroresAgregar = {};
        },

        async abrirAgregar() {
            if (!this.puedeAgregar) return;

            if (this.multiestacion) {
                const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/agregar-detalle-vacio', { id: this.id });
                if (!this.manejar(res)) return;
                this.reemplazarFila(res.fila);
                return;
            }

            this.resetAgregar();
            this.$nextTick(() => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregar')).show();
            });
        },

        async guardarAgregar() {
            if (!this.puedeAgregar) return;
            const f = this.formAgregar;
            if (this.marcarErroresAgregar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/agregar-detalle', {
                id: this.id,
                producto: f.producto,
                tanque: f.tanque,
                litros: this.normalizarNumero(f.litros),
                tad: f.tad,
                unidad: f.unidad,
                chofer: f.chofer
            });
            if (!this.manejar(res)) return;

            this.reemplazarFila(res.fila);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregar')).hide();
        },

        abrirEstacion(idDetalle, categoria, estacion, destino) {
            if (!this.editable) return;
            const est = String(estacion || '');
            const dest = String(destino || '');
            const enCatalogo = est !== '' && this.estaciones.indexOf(est) !== -1;

            this.estacionForm = {
                id_detalle: parseInt(idDetalle) || 0,
                categoria: parseInt(categoria) === 2 ? 2 : 1,
                estacion: enCatalogo ? est : '',
                estacionOtro: (!enCatalogo && est !== '') ? est : '',
                destinoOtro: (!enCatalogo && est !== '') ? dest : ''
            };
            setTimeout(() => {
                new bootstrap.Modal(document.getElementById('modalEstacion')).show();
            }, 50);
        },

        async guardarEstacion() {
            if (!this.editable || !this.estacionForm.id_detalle) return;

            const usaLista = !!this.estacionForm.estacion;
            const estacion = usaLista
                ? this.estacionForm.estacion
                : String(this.estacionForm.estacionOtro || '').trim();
            const destino = usaLista
                ? ''
                : String(this.estacionForm.destinoOtro || '').trim();

            const usaOtra = !usaLista;
            const exigeDestino = Number(this.estacionForm.categoria) === 1;
            const destinoValido = !usaOtra || !exigeDestino || /^\d+$/.test(destino);
            this.erroresEstacion = {
                estacion: !estacion,
                nombre: usaOtra && !estacion,
                destino: usaOtra && exigeDestino && !destinoValido
            };
            if (!estacion || !destinoValido) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-detalle-estacion', {
                id_detalle: this.estacionForm.id_detalle,
                categoria: this.estacionForm.categoria,
                estacion: estacion,
                destino: destino
            }, { loader: false });
            if (!this.manejar(res, { alerta: false })) return;

            this.reemplazarFila(res.fila);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEstacion')).hide();
        },

        async guardarFechaDirecta(valor, elemento = null) {
            if (!this.puedeEditarFecha) return;
            if (!valor) {
                this.notify('error', 'La fecha es obligatoria.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-fecha', { id: this.id, fecha: valor }, { loader: false });
            if (!this.manejar(res, { alerta: false, notificar: false })) return;

            this.detalle.fecha = valor;
            this.mostrarIndicadorGuardado(elemento);
        },

        abrirGmail() {
            if (!this.puedeGmail) return;
            this.gmailForm.correo = this.correoDefault;
            this.gmailForm.asunto = 'Formato de Pivoteo';
            this.gmailForm.contenido = 'Envió formato de Pivoteo con número de folio: ' + this.nocontrol;
            new bootstrap.Modal(document.getElementById('modalGmail')).show();
        },

        async guardarGmail() {
            if (!this.puedeGmail) return;
            this.erroresCorreo = {
                correo: !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(this.gmailForm.correo || '').trim()),
                asunto: !String(this.gmailForm.asunto || '').trim()
            };
            if (Object.values(this.erroresCorreo).some(Boolean)) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/enviar-correo', {
                id: this.id,
                correo: this.gmailForm.correo,
                asunto: this.gmailForm.asunto,
                contenido: this.gmailForm.contenido
            });
            if (!this.manejar(res)) return;

            if (Array.isArray(res.historial)) this.historialCorreos = res.historial;
            this.gmailForm.correo = this.correoDefault;
            this.gmailForm.asunto = 'Formato de Pivoteo';
            this.gmailForm.contenido = 'Envió formato de Pivoteo con número de folio: ' + this.nocontrol;
        },

        async editarCampo(idDetalle, opcion, valor, elemento = null) {
            if (!this.editable) return;
            if (idDetalle <= 0) {
                this.notify('error', 'No se encontró la línea del pivoteo.');
                return;
            }
            const campoObligatorio = ({ 3: 'tad', 4: 'unidad', 5: 'chofer', 10: 'tanque_fc', 11: 'litros', 12: 'producto_fc' })[opcion];
            const falta = opcion === 11
                ? !(this.normalizarNumero(valor) > 0)
                : [3, 4, 5, 10, 12].includes(opcion) && !String(valor || '').trim();
            if (elemento && campoObligatorio) {
                elemento.classList.toggle('is-invalid', falta);
                elemento.closest('td')?.classList.toggle('border-danger', falta);
            }
            if (falta) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-detalle', {
                id_detalle: idDetalle,
                opcion: opcion,
                valor: opcion === 11 ? String(this.normalizarNumero(valor)) : String(valor || '')
            }, { loader: false });
            if (!this.manejar(res, { alerta: false, notificar: false })) return;

            if (elemento && campoObligatorio) {
                elemento.classList.remove('is-invalid');
                elemento.closest('td')?.classList.remove('border-danger');
            }

            this.reemplazarFila(res.fila);
            this.mostrarIndicadorGuardado(elemento);
        },

        async guardarCabecera(opcion, valor, elemento = null) {
            if (!this.puedeEditarCabecera) return;

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-detalle', {
                id_detalle: this.id,
                opcion: opcion,
                valor: String(valor || '')
            }, { loader: false });
            if (!this.manejar(res, { alerta: false, notificar: false })) return;

            if (opcion === 7) this.detalle.sucursal = valor;
            if (opcion === 9) this.detalle.causa = valor;
            this.mostrarIndicadorGuardado(elemento);
        },

        async confirmarFinalizar() {
            if (!this.puedeFinalizar) return;
            this.validacionFinalizacionActiva = true;
            if (this.validarFilasObligatorias()) {
                this.notify('error', 'Completa los campos obligatorios; revisa la lista en rojo.');
                return;
            }
            if (!this.firmaPad || this.firmaPad.isEmpty()) {
                this.firmaInvalida = true;
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }
            this.firmaInvalida = false;
            const firmaBase64 = this.firmaPad.toDataURL('image/png');

            const result = await Swal.fire({
                title: '¿Enviar a firma de Vo.Bo.?',
                text: 'La firma de quien elaboró cambiará el estado a En proceso y solicitará el Vo.Bo. mediante token.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });
            if (!result.isConfirmed) return;

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/finalizar', {
                id: this.id,
                firma_base64: firmaBase64
            });
            if (res && res.errors && Array.isArray(res.errors.campos)) {
                this.marcarCamposFaltantesServidor(res.errors.campos);
            }
            if (!this.manejar(res)) return;

            setTimeout(() => {
                window.location.href = '/departamento-operativo/importacion/pivoteo';
            }, 800);
        },

        async confirmarEliminarDetalle(idDetalle) {
            if (!this.puedeEliminarDetalle) return;

            const result = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: 'El registro será eliminado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });
            if (!result.isConfirmed) return;

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/eliminar-detalle', { id_detalle: idDetalle });
            if (!this.manejar(res)) return;

            this.quitarFila(res.id_detalle != null ? res.id_detalle : idDetalle);
        }
    }));
});
