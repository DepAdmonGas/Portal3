document.addEventListener('alpine:init', () => {

    Alpine.data('mermaComponent', () => ({
        puedeCrear: false,
        moduleStationKey: 'formato-descarga-merma',
        idEstacion: 0,

        comentarioIdActual: 0,
        comentarioFolio: '',
        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.moduleStationKey = c.dataset.moduleStationKey || 'formato-descarga-merma';
                this.idEstacion = parseInt(c.dataset.idEstacion) || 0;
            }

            document.addEventListener('abrir-comentarios', (e) => {
                this.abrirComentarios(e.detail.id);
            });
            document.addEventListener('eliminar-merma', (e) => {
                this.confirmarEliminar(e.detail.id, e.detail.name);
            });
        },

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        async abrirComentarios(id) {
            this.comentarioIdActual = id;
            this.nuevoComentario = '';
            this.comentarios = [];

            const res = await this.getAction({
                url: '/departamento-operativo/importacion/formato-descarga-merma/comentarios/' + id
            });

            if (res && res.success) {
                this.comentarios = res.comentarios || [];
                this.comentarioFolio = res.folio || '';
            }

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasComentarios')).show();
            this.scrollChatToBottom();
        },

        async agregarComentario() {
            if (!this.nuevoComentario.trim() || !this.comentarioIdActual) return;

            this.guardandoComentario = true;

            const res = await this.createAction({
                url: '/departamento-operativo/importacion/formato-descarga-merma/agregar-comentario',
                data: {
                    id_descarga: this.comentarioIdActual,
                    comentario: this.nuevoComentario.trim()
                },
                notify: false
            });

            if (res && res.success) {
                this.comentarios = res.comentarios || [];
                this.nuevoComentario = '';
                this.scrollChatToBottom();
                if (window.tablaMerma) window.tablaMerma.ajax.reload(null, false);
            } else {
                this.notify('error', res?.message || 'Error al comentar.');
            }

            this.guardandoComentario = false;
        },

        async confirmarEliminar(id, name) {
            await this.deleteAction({
                url: '/departamento-operativo/importacion/formato-descarga-merma/delete',
                id: id,
                name: name || 'Formato #00' + id,
                table: '#tabla-merma'
            });
        }
    }));

    Alpine.data('mermaForm', () => ({
        guardando: false,
        firmaEncargado: null,
        firmaOperador: null,
        form: {
            fecha_llegada: '',
            hora_llegada: '',
            producto: '',
            sellos: 'No',
            detuvo_venta: 'No',
            litros: 0,
            cuenta_litros: 0,
            merma: 0,
            precio_litro: 0,
            unidad: '',
            no_factura_remision: '',
            operador: '',
            transportista: '',
            firma_encargado: '',
            firma_operador: '',
        },

        init() {
            const d = new Date();
            this.form.fecha_llegada = d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');

            this.iniciarSignaturePads();
            this.agregarLimpiezaDeErrores();
        },

        agregarLimpiezaDeErrores() {
            const limpiar = (e) => {
                const t = e.target;
                if (!t || !t.classList) return;
                t.classList.remove('is-invalid');
                const w = t.closest('.form-check') || t.closest('.signature-pad-wrapper');
                if (w) w.classList.remove('is-invalid');
            };
            this.$root.addEventListener('input', limpiar);
            this.$root.addEventListener('change', limpiar);
        },

        _esInvalido(def) {
            const v = this.form[def.key];
            if (def.numerico) {
                return !(parseFloat(v) > 0);
            }
            return v == null || String(v).trim() === '';
        },

        marcarInvalidos() {
            const defs = [
                { key: 'fecha_llegada' }, { key: 'hora_llegada' }, { key: 'producto' },
                { key: 'no_factura_remision' }, { key: 'litros', numerico: true },
                { key: 'precio_litro', numerico: true }, { key: 'cuenta_litros', numerico: true },
                { key: 'unidad' }, { key: 'operador' }, { key: 'transportista' },
            ];
            const files = ['no_factura', 'inventario_inicial', 'nice', 'inventario_final', 'metro_contador', 'metro_contador20'];
            const mal = [];

            defs.forEach((def) => {
                const invalido = this._esInvalido(def);
                this.$root.querySelectorAll('[x-model="form.' + def.key + '"]').forEach((el) => {
                    const target = (el.type === 'radio') ? (el.closest('.form-check') || el) : el;
                    target.classList.toggle('is-invalid', invalido);
                });
                if (invalido) mal.push(def.key);
            });

            files.forEach((f) => {
                const el = this.$refs['file_' + f];
                const invalido = !(el && el.files && el.files[0]);
                if (el) el.classList.toggle('is-invalid', invalido);
                if (invalido) mal.push('file_' + f);
            });

            ['encargado', 'operador'].forEach((tipo) => {
                const pad = this[tipo === 'encargado' ? 'firmaEncargado' : 'firmaOperador'];
                const invalido = !(pad && !pad.isEmpty());
                const cv = document.getElementById(tipo === 'encargado' ? 'canvasFirmaEncargado' : 'canvasFirmaOperador');
                const wrapper = cv && cv.closest('.signature-pad-wrapper');
                if (wrapper) wrapper.classList.toggle('is-invalid', invalido);
                if (invalido) mal.push('firma_' + tipo);
            });

            return mal;
        },

        focusPrimerInvalido(lista) {
            const self = this;
            for (let i = 0; i < lista.length; i++) {
                const k = lista[i];
                if (k.indexOf('file_') === 0) {
                    if (self.$refs[k]) { self.$refs[k].focus(); return; }
                } else if (k.indexOf('firma_') === 0) {
                    const cv = document.getElementById(k === 'firma_encargado' ? 'canvasFirmaEncargado' : 'canvasFirmaOperador');
                    if (cv) { cv.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
                } else {
                    const el = self.$root.querySelector('[x-model="form.' + k + '"]');
                    if (el) { el.focus(); return; }
                }
            }
        },

        iniciarSignaturePads() {
            const self = this;
            if (typeof SignaturePad === 'undefined') return;

            const pads = [
                { id: 'canvasFirmaEncargado', key: 'firmaEncargado' },
                { id: 'canvasFirmaOperador', key: 'firmaOperador' },
            ];

            const init = () => {
                pads.forEach(({ id, key }) => {
                    const cv = document.getElementById(id);
                    if (!cv || cv.offsetWidth <= 0 || cv.offsetHeight <= 0) return;

                    let data = null;
                    if (self[key]) {
                        try { data = self[key].toData(); } catch (e) { data = null; }
                    }

                    const r = Math.max(window.devicePixelRatio || 1, 1);
                    cv.width = cv.offsetWidth * r;
                    cv.height = cv.offsetHeight * r;
                    cv.getContext('2d').scale(r, r);

                    self[key] = new SignaturePad(cv, { backgroundColor: 'rgb(255, 255, 255)', penColor: '#000' });

                    if (data && data.length > 0) {
                        try { self[key].fromData(data); } catch (e) { }
                    }
                });
            };

            setTimeout(init, 300);
            window.addEventListener('resize', () => setTimeout(init, 200));
        },

        calcularMerma() {
            this.form.merma = parseFloat((this.form.litros - this.form.cuenta_litros).toFixed(2));
        },

        limpiarFirma(tipo) {
            if (tipo === 'encargado' && this.firmaEncargado) {
                this.firmaEncargado.clear();
                this.form.firma_encargado = '';
            } else if (tipo === 'operador' && this.firmaOperador) {
                this.firmaOperador.clear();
                this.form.firma_operador = '';
            }
        },

        _getFiles() {
            const files = {};
            const fileFields = ['no_factura', 'inventario_inicial', 'nice', 'inventario_final', 'metro_contador', 'metro_contador20'];
            fileFields.forEach(field => {
                const ref = this.$refs['file_' + field];
                if (ref && ref.files && ref.files[0]) {
                    files[field] = ref.files[0];
                }
            });
            return files;
        },

        _buildFormData(files) {
            const fd = new FormData();
            fd.append('fecha_llegada', this.form.fecha_llegada);
            fd.append('hora_llegada', this.form.hora_llegada);
            fd.append('producto', this.form.producto.trim());
            fd.append('sellos', this.form.sellos);
            fd.append('detuvo_venta', this.form.detuvo_venta);
            fd.append('litros', this.form.litros);
            fd.append('cuenta_litros', this.form.cuenta_litros);
            fd.append('merma', this.form.merma);
            fd.append('precio_litro', this.form.precio_litro);
            fd.append('unidad', this.form.unidad);
            fd.append('no_factura_remision', this.form.no_factura_remision);
            fd.append('operador', this.form.operador);
            fd.append('transportista', this.form.transportista);
            fd.append('firma_encargado', this.form.firma_encargado);
            fd.append('firma_operador', this.form.firma_operador);

            Object.keys(files).forEach(key => {
                fd.append(key, files[key]);
            });

            return fd;
        },

        async guardarNuevo() {
            this.focusPrimerInvalido(this.marcarInvalidos());

            if (!this.form.fecha_llegada) { this.notify('error', 'La fecha de llegada es obligatoria.'); return; }
            if (!this.form.hora_llegada) { this.notify('error', 'La hora de llegada es obligatoria.'); return; }
            if (!this.form.producto.trim()) { this.notify('error', 'El producto es obligatorio.'); return; }
            if (!this.form.litros || parseFloat(this.form.litros) <= 0) { this.notify('error', 'Los litros son obligatorios.'); return; }
            if (!this.form.precio_litro || parseFloat(this.form.precio_litro) <= 0) { this.notify('error', 'El precio por litro es obligatorio.'); return; }
            if (!this.form.cuenta_litros || parseFloat(this.form.cuenta_litros) <= 0) { this.notify('error', 'La cuenta de litros es obligatoria.'); return; }
            if (!this.form.unidad.trim()) { this.notify('error', 'La unidad es obligatoria.'); return; }
            if (!this.form.operador.trim()) { this.notify('error', 'El nombre del operador es obligatorio.'); return; }
            if (!this.form.transportista.trim()) { this.notify('error', 'La compañía del transportista es obligatoria.'); return; }
            if (!this.$refs.file_inventario_inicial?.files?.[0]) { this.notify('error', 'El reporte de inventario inicial es obligatorio.'); return; }
            if (!this.$refs.file_nice?.files?.[0]) { this.notify('error', 'La medida NICE es obligatoria.'); return; }
            if (!this.$refs.file_inventario_final?.files?.[0]) { this.notify('error', 'El reporte de inventario final es obligatorio.'); return; }
            if (!this.$refs.file_metro_contador?.files?.[0]) { this.notify('error', 'El metro contador temperatura normal es obligatorio.'); return; }
            if (!this.$refs.file_metro_contador20?.files?.[0]) { this.notify('error', 'El metro contador a 20 grados es obligatorio.'); return; }

            if (this.firmaEncargado && !this.firmaEncargado.isEmpty()) {
                this.form.firma_encargado = this.firmaEncargado.toDataURL('image/png');
            }
            if (this.firmaOperador && !this.firmaOperador.isEmpty()) {
                this.form.firma_operador = this.firmaOperador.toDataURL('image/png');
            }

            if (!this.form.firma_encargado) { this.notify('error', 'La firma del encargado es obligatoria.'); return; }
            if (!this.form.firma_operador) { this.notify('error', 'La firma del operador es obligatoria.'); return; }

            const files = this._getFiles();
            const fd = this._buildFormData(files);

            window.loader.show();
            this.guardando = true;

            const res = await this.createAction({
                url: '/departamento-operativo/importacion/formato-descarga-merma/store',
                data: fd,
                notify: false,
                onSuccess: (r) => {
                    window.loader.hide();
                    this.guardando = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Correcto',
                        text: r.message || 'Formato registrado exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/departamento-operativo/importacion/formato-descarga-merma-detalle/' + r.id;
                    });
                },
                onError: () => {
                    window.loader.hide();
                    this.guardando = false;
                }
            });

            window.loader.hide();
            this.guardando = false;
        }
    }));

    Alpine.data('mermaFormEditar', () => ({
        guardando: false,
        adjuntos: {},
        registro: null,
        form: {
            fecha_llegada: '',
            hora_llegada: '',
            producto: '',
            sellos: 'No',
            detuvo_venta: 'No',
            litros: 0,
            cuenta_litros: 0,
            merma: 0,
            precio_litro: 0,
            unidad: '',
            no_factura_remision: '',
            operador: '',
            transportista: '',
        },

        init() {
            const c = document.getElementById('container');
            if (!c) return;

            if (c.dataset.adjuntos) {
                try { this.adjuntos = JSON.parse(c.dataset.adjuntos); } catch (e) { this.adjuntos = {}; }
            }
            if (c.dataset.registro) {
                try { this.registro = JSON.parse(c.dataset.registro); } catch (e) { this.registro = null; }
            }

            const reg = this.registro || {};
            this.form = {
                fecha_llegada: reg.fecha_llegada || '',
                hora_llegada: reg.hora_llegada || '',
                producto: reg.producto || '',
                sellos: reg.sellos || 'No',
                detuvo_venta: reg.detuvo_venta || 'No',
                litros: parseFloat(reg.litros_raw) || 0,
                cuenta_litros: parseFloat(reg.cuenta_litros_raw) || 0,
                merma: parseFloat(reg.merma_raw) || 0,
                precio_litro: parseFloat(reg.precio_litro_raw) || 0,
                unidad: reg.unidad || '',
                no_factura_remision: reg.no_factura_remision || '',
                operador: reg.operador || '',
                transportista: reg.transportista || '',
            };

            this.agregarLimpiezaDeErrores();
        },

        agregarLimpiezaDeErrores() {
            const limpiar = (e) => {
                const t = e.target;
                if (!t || !t.classList) return;
                t.classList.remove('is-invalid');
                const w = t.closest('.form-check');
                if (w) w.classList.remove('is-invalid');
            };
            this.$root.addEventListener('input', limpiar);
            this.$root.addEventListener('change', limpiar);
        },

        _esInvalido(def) {
            const v = this.form[def.key];
            if (def.numerico) {
                return !(parseFloat(v) > 0);
            }
            return v == null || String(v).trim() === '';
        },

        marcarInvalidos() {
            const defs = [
                { key: 'fecha_llegada' }, { key: 'hora_llegada' }, { key: 'producto' },
                { key: 'no_factura_remision' }, { key: 'litros', numerico: true },
                { key: 'precio_litro', numerico: true }, { key: 'cuenta_litros', numerico: true },
                { key: 'unidad' }, { key: 'operador' }, { key: 'transportista' },
            ];
            const mal = [];

            defs.forEach((def) => {
                const invalido = this._esInvalido(def);
                this.$root.querySelectorAll('[x-model="form.' + def.key + '"]').forEach((el) => {
                    const target = (el.type === 'radio') ? (el.closest('.form-check') || el) : el;
                    target.classList.toggle('is-invalid', invalido);
                });
                if (invalido) mal.push(def.key);
            });

            return mal;
        },

        focusPrimerInvalido(lista) {
            const self = this;
            for (let i = 0; i < lista.length; i++) {
                const k = lista[i];
                const el = self.$root.querySelector('[x-model="form.' + k + '"]');
                if (el) { el.focus(); return; }
            }
        },

        calcularMerma() {
            this.form.merma = parseFloat((this.form.litros - this.form.cuenta_litros).toFixed(2));
        },

        _buildFormData() {
            const fd = new FormData();
            fd.append('id', (this.registro && this.registro.id) || 0);
            fd.append('fecha_llegada', this.form.fecha_llegada);
            fd.append('hora_llegada', this.form.hora_llegada);
            fd.append('producto', this.form.producto.trim());
            fd.append('sellos', this.form.sellos);
            fd.append('detuvo_venta', this.form.detuvo_venta);
            fd.append('litros', this.form.litros);
            fd.append('cuenta_litros', this.form.cuenta_litros);
            fd.append('merma', this.form.merma);
            fd.append('precio_litro', this.form.precio_litro);
            fd.append('unidad', this.form.unidad);
            fd.append('no_factura_remision', this.form.no_factura_remision);
            fd.append('operador', this.form.operador);
            fd.append('transportista', this.form.transportista);

            const fileFields = ['no_factura', 'inventario_inicial', 'nice', 'inventario_final', 'metro_contador', 'metro_contador20'];
            fileFields.forEach(field => {
                const ref = this.$refs['file_' + field];
                if (ref && ref.files && ref.files[0]) {
                    fd.append(field, ref.files[0]);
                }
            });

            return fd;
        },

        async guardarEditar() {
            this.focusPrimerInvalido(this.marcarInvalidos());

            if (!this.form.fecha_llegada) { this.notify('error', 'La fecha de llegada es obligatoria.'); return; }
            if (!this.form.hora_llegada) { this.notify('error', 'La hora de llegada es obligatoria.'); return; }
            if (!this.form.producto.trim()) { this.notify('error', 'El producto es obligatorio.'); return; }

            const fd = this._buildFormData();

            window.loader.show();
            this.guardando = true;

            const res = await this.createAction({
                url: '/departamento-operativo/importacion/formato-descarga-merma/update',
                data: fd,
                notify: false,
                onSuccess: (r) => {
                    window.loader.hide();
                    this.guardando = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Correcto',
                        text: r.message || 'Formato actualizado exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '/departamento-operativo/importacion/formato-descarga-merma-detalle/' + r.id;
                    });
                },
                onError: () => {
                    window.loader.hide();
                    this.guardando = false;
                }
            });

            window.loader.hide();
            this.guardando = false;
        }
    }));
});