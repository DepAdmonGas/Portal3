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
            this.puedeEditarFecha = !!d.puedeEditar && this.estatus !== 2;
            this.puedeFirmar = !!d.puedeFirmar;
            this.puedePdf = !!d.puedePDF;
            this.puedeGmail = !!d.puedeGmail;
            this.puedeEditarCabecera = !!d.puedeEditarCabecera;
            this.puedeEliminarDetalle = !!d.puedeEliminarDetalle;
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
            this.puedeFinalizar = this.filas.length > 0;
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
            return Number(valor || 0).toFixed(2);
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
                    this.editarCampo(idDetalle, opcion, valor);
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
            this.puedeFinalizar = this.filas.length > 0;
            this.$nextTick(() => this.refreshTableSelect2s());
        },

        quitarFila(idDetalle) {
            const id = parseInt(idDetalle) || 0;
            const idx = this.filas.findIndex(f => parseInt(f.id_detalle) === id);
            if (idx !== -1) this.filas.splice(idx, 1);
            this.puedeFinalizar = this.filas.length > 0;
            this.$nextTick(() => this.refreshTableSelect2s());
        },

        async ejecutar(url, data, opciones = {}) {
            if (this.guardando) return null;
            const usarLoader = opciones.loader !== false;
            this.guardando = true;
            if (usarLoader && window.loader) window.loader.show();
            try {
                const response = await axios.post(url, data);
                return response.data;
            } catch (err) {
                return {
                    success: false,
                    message: err.response?.data?.message || err.message || 'Error en la solicitud'
                };
            } finally {
                if (usarLoader && window.loader) window.loader.hide();
                this.guardando = false;
            }
        },

        manejar(res, opciones = {}) {
            if (!res) return false;
            if (res.success) {
                if (res.message) {
                    if (opciones.alerta !== false) {
                        this.showAlert('success', 'Correcto', res.message);
                    }
                    this.notify('success', res.message);
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
            if (!f.producto) { this.notify('error', 'El producto es obligatorio.'); return; }
            if (!f.tanque) { this.notify('error', 'El tanque es obligatorio.'); return; }
            if (f.litros === '' || f.litros === null || isNaN(parseFloat(f.litros))) { this.notify('error', 'Los litros son obligatorios.'); return; }
            if (!f.tad) { this.notify('error', 'El TAD es obligatorio.'); return; }
            if (!f.unidad) { this.notify('error', 'La unidad es obligatoria.'); return; }
            if (!f.chofer) { this.notify('error', 'El chofer es obligatorio.'); return; }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/agregar-detalle', {
                id: this.id,
                producto: f.producto,
                tanque: f.tanque,
                litros: f.litros,
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

            if (!estacion) {
                this.notify('error', 'La estación es obligatoria.');
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

        async guardarFechaDirecta(valor) {
            if (!this.puedeEditarFecha) return;
            if (!valor) {
                this.notify('error', 'La fecha es obligatoria.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-fecha', { id: this.id, fecha: valor }, { loader: false });
            if (!this.manejar(res, { alerta: false })) return;

            this.detalle.fecha = valor;
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
            if (!this.gmailForm.correo) {
                this.notify('error', 'El correo es obligatorio.');
                return;
            }
            if (!this.gmailForm.asunto) {
                this.notify('error', 'El asunto es obligatorio.');
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

        async editarCampo(idDetalle, opcion, valor) {
            if (!this.editable) return;
            if (idDetalle <= 0) {
                this.notify('error', 'No se encontró la línea del pivoteo.');
                return;
            }
            if (opcion === 11 && (valor === '' || isNaN(parseFloat(valor)))) {
                this.notify('error', 'El campo "Litros" debe ser numérico.');
                return;
            }

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-detalle', {
                id_detalle: idDetalle,
                opcion: opcion,
                valor: String(valor || '')
            }, { loader: false });
            if (!this.manejar(res, { alerta: false })) return;

            this.reemplazarFila(res.fila);
        },

        async guardarCabecera(opcion, valor) {
            if (!this.puedeEditarCabecera) return;

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/editar-detalle', {
                id_detalle: this.id,
                opcion: opcion,
                valor: String(valor || '')
            }, { loader: false });
            if (!this.manejar(res, { alerta: false })) return;

            if (opcion === 7) this.detalle.sucursal = valor;
            if (opcion === 9) this.detalle.causa = valor;
        },

        async confirmarFinalizar() {
            if (!this.puedeFinalizar) return;

            const result = await Swal.fire({
                title: '¿Finalizar pivoteo?',
                text: 'Una vez finalizado, solo podrá editarse el contenido hasta la firma.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });
            if (!result.isConfirmed) return;

            const res = await this.ejecutar('/departamento-operativo/importacion/pivoteo/finalizar', { id: this.id });
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
