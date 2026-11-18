document.addEventListener('alpine:init', () => {
    Alpine.data('ordenCompraForm', (detalleInicial) => ({
        BASE_URL: '/departamento-operativo/almacen/orden-compra',
        form: detalleInicial,
        estacionSeleccionada: detalleInicial.estacion?.id_localidad || '',
        errorEstacion: false,
        guardandoFirma: false,
        signaturePad: null,
        indicadorGuardadoTimers: new WeakMap(),

        // Modal Proveedor
        editandoProveedor: false,
        guardandoProveedor: false,
        formProv: { idProveedor: 0, RazonSocial: '', Direccion: '', Contacto: '', Email: '' },
        errorsProv: { RazonSocial: false, Direccion: false, Contacto: false, Email: false },

        // Modal Artículo
        guardandoArticulo: false,
        formArt: { id_proveedor: '', Concepto: '', Unidades: '', EstatusR: 'Nuevo', PrecioUnitario: '' },
        errorsArt: { id_proveedor: false, Concepto: false, Unidades: false, PrecioUnitario: false },

        // Modal Refacturación
        guardandoRefacturacion: false,
        formRef: { Estacion: '', Descripcion: '', Cantidad: '', Importe: '', Porcentaje: '', CantidadES: '', CantidadAl: '' },
        errorsRef: { Estacion: false, Descripcion: false, Cantidad: false, Importe: false },

        init() {
            this.$nextTick(() => {
                this.initSignaturePad();
            });
        },

        initSignaturePad() {
            const canvas = document.getElementById('canvas');
            if (canvas && typeof SignaturePad !== 'undefined') {
                this.signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
                this.resizeCanvas();
                window.addEventListener('resize', () => this.resizeCanvas());
            }
        },

        resizeCanvas() {
            const canvas = document.getElementById('canvas');
            if (!canvas || !this.signaturePad) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            this.signaturePad.clear();
        },

        limpiarFirma() {
            if (this.signaturePad) this.signaturePad.clear();
        },

        parseNumero(valor) {
            const numero = Number(String(valor ?? '').replace(/,/g, '').trim());
            return Number.isFinite(numero) ? numero : 0;
        },

        capturarNumero(event, modelo, campo) {
            const input = event.target;
            const caret = input.selectionStart ?? input.value.length;
            const digitosAntes = input.value.slice(0, caret).replace(/[^0-9.]/g, '').length;
            let limpio = input.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const punto = limpio.indexOf('.');
            if (punto !== -1) limpio = limpio.slice(0, punto + 1) + limpio.slice(punto + 1).replace(/\./g, '');
            const partes = limpio.split('.');
            const entero = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            const formateado = partes.length > 1 ? `${entero}.${partes[1]}` : entero;
            input.value = formateado;
            modelo[campo] = formateado;

            let nuevaPosicion = 0;
            let vistos = 0;
            while (nuevaPosicion < formateado.length && vistos < digitosAntes) {
                if (/[0-9.]/.test(formateado[nuevaPosicion])) vistos++;
                nuevaPosicion++;
            }
            input.setSelectionRange(nuevaPosicion, nuevaPosicion);
            return formateado;
        },

        formatoNumero(valor) {
            const numero = this.parseNumero(valor);
            return Number.isFinite(numero) ? numero.toLocaleString('en-US', { maximumFractionDigits: 20 }) : '';
        },

        indicarGuardado(input, estado) {
            const indicador = input?.parentElement?.querySelector('.orden-compra-save-indicator');
            if (!indicador) return;
            const timerAnterior = this.indicadorGuardadoTimers.get(indicador);
            if (timerAnterior) window.clearTimeout(timerAnterior);
            indicador.className = 'orden-compra-save-indicator position-absolute top-50 end-0 translate-middle-y me-2 small';
            indicador.dataset.estado = estado;
            if (estado === 'saving') {
                indicador.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" aria-label="Guardando"></span>';
            } else if (estado === 'saved') {
                indicador.innerHTML = '<i class="ti ti-check text-success" title="Cambio guardado"></i>';
                this.indicadorGuardadoTimers.set(indicador, window.setTimeout(() => {
                    if (indicador.dataset.estado === 'saved') indicador.classList.add('d-none');
                }, 1800));
            } else {
                indicador.innerHTML = '<i class="ti ti-alert-circle text-danger" title="No se pudo guardar el cambio"></i>';
            }
        },

        configSinLoader() {
            return { headers: { 'X-Portal3-Skip-Action-Loader': '1' } };
        },

        async editarOC(num, valor, input = null) {
            const fd = new FormData();
            fd.append('id', this.form.id);
            fd.append('num', num);
            fd.append('valor', num === 3 ? this.parseNumero(valor) : valor);
            this.indicarGuardado(input, 'saving');

            try {
                const resp = await axios.post(`${this.BASE_URL}/editar-formato`, fd, this.configSinLoader());
                if (resp.data?.success) {
                    this.indicarGuardado(input, 'saved');
                } else {
                    this.indicarGuardado(input, 'error');
                }
            } catch (e) {
                this.indicarGuardado(input, 'error');
            }
        },

        abrirModalEstacion() {
            this.estacionSeleccionada = this.form.estacion?.id_localidad || '';
            this.errorEstacion = false;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEstacion')).show();
        },

        async guardarEstacion() {
            if (!this.estacionSeleccionada) {
                this.errorEstacion = true;
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }
            this.errorEstacion = false;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('idEstacion', this.estacionSeleccionada);

            try {
                const resp = await axios.post(`${this.BASE_URL}/guardar-estacion`, fd);
                if (resp.data?.success) {
                    this.notify('success', 'Estación asignada.');
                    bootstrap.Modal.getInstance(document.getElementById('modalEstacion')).hide();
                    window.location.reload();
                }
            } catch (e) {
                this.notify('error', 'Error al guardar la estación.');
            }
        },

        // ---------- PROVEEDORES ---------- //
        abrirModalProveedor() {
            this.editandoProveedor = false;
            this.formProv = { idProveedor: 0, RazonSocial: '', Direccion: '', Contacto: '', Email: '' };
            this.errorsProv = { RazonSocial: false, Direccion: false, Contacto: false, Email: false };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
        },

        abrirModalEditarProveedor(prov) {
            this.editandoProveedor = true;
            this.formProv = {
                idProveedor: prov.id,
                RazonSocial: prov.razon_social,
                Direccion: prov.direccion,
                Contacto: prov.contacto,
                Email: prov.email
            };
            this.errorsProv = { RazonSocial: false, Direccion: false, Contacto: false, Email: false };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProveedor')).show();
        },

async guardarProveedor() {
            this.errorsProv = { RazonSocial: false, Direccion: false, Contacto: false, Email: false };
            let hasError = false;

            if (!this.formProv.RazonSocial.trim()) { this.errorsProv.RazonSocial = true; hasError = true; }
            if (!this.formProv.Direccion.trim()) { this.errorsProv.Direccion = true; hasError = true; }
            if (!this.formProv.Contacto.trim()) { this.errorsProv.Contacto = true; hasError = true; }
            if (!this.formProv.Email.trim()) { this.errorsProv.Email = true; hasError = true; }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoProveedor = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('RazonSocial', this.formProv.RazonSocial);
            fd.append('Direccion', this.formProv.Direccion);
            fd.append('Contacto', this.formProv.Contacto);
            fd.append('Email', this.formProv.Email);

            const url = this.editandoProveedor
                ? `${this.BASE_URL}/editar-proveedor`
                : `${this.BASE_URL}/agregar-proveedor`;

            if (this.editandoProveedor) {
                fd.append('idProveedor', this.formProv.idProveedor);
            }

            try {
                const resp = await axios.post(url, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalProveedor')).hide();
                    await this.recargarDatos();
                } else {
                    this.notify('error', resp.data?.message || 'Error al guardar.');
                }
            } catch (e) {
                this.notify('error', 'Error en el servidor al guardar proveedor.');
            } finally {
                this.guardandoProveedor = false;
            }
        },

async eliminarProveedor(id, nombre) {
            await this.deleteAction({
                url: `${this.BASE_URL}/eliminar-proveedor`,
                id: id,
                name: nombre ? `el proveedor "${nombre}"` : 'el proveedor'
            });

            // Se ejecuta inmediatamente tras confirmarse y procesarse la eliminación
            this.form.proveedores = this.form.proveedores.filter(p => p.id !== id);
            await this.recargarDatos();
        },

        async seleccionarProveedor(idProveedor, input = null) {
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('idProveedor', idProveedor);
            fd.append('valor', 1);
            this.indicarGuardado(input, 'saving');

            try {
                const resp = await axios.post(`${this.BASE_URL}/seleccionar-proveedor`, fd, this.configSinLoader());
                if (resp.data?.success) {
                    this.indicarGuardado(input, 'saved');
                    await this.recargarDatos();
                } else {
                    this.indicarGuardado(input, 'error');
                    this.notify('error', resp.data?.message || 'Error al seleccionar proveedor.');
                }
            } catch (e) {
                this.indicarGuardado(input, 'error');
                this.notify('error', 'Error al seleccionar proveedor.');
            }
        },

        async actualizarCostos(idProveedor, tipo, valor, input = null) {
            const fd = new FormData();
            fd.append('idProveedor', idProveedor);
            fd.append('tipo', tipo);
            fd.append('valor', this.parseNumero(valor));
            this.indicarGuardado(input, 'saving');

            try {
                const resp = await axios.post(`${this.BASE_URL}/actualizar-costos-proveedor`, fd, this.configSinLoader());
                if (resp.data?.success) {
                    this.indicarGuardado(input, 'saved');
                    await this.recargarDatos();
                } else {
                    this.indicarGuardado(input, 'error');
                }
            } catch (e) {
                this.indicarGuardado(input, 'error');
            }
        },

        // ---------- ARTÍCULOS ---------- //
        abrirModalArticulo() {
            this.formArt = { id_proveedor: '', Concepto: '', Unidades: '', EstatusR: 'Nuevo', PrecioUnitario: '' };
            this.errorsArt = { id_proveedor: false, Concepto: false, Unidades: false, PrecioUnitario: false };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalArticulo')).show();
        },

async guardarArticulo() {
            this.errorsArt = { id_proveedor: false, Concepto: false, Unidades: false, PrecioUnitario: false };
            let hasError = false;

            if (!this.formArt.id_proveedor) { this.errorsArt.id_proveedor = true; hasError = true; }
            if (!this.formArt.Concepto.trim()) { this.errorsArt.Concepto = true; hasError = true; }
            if (!this.formArt.Unidades || this.parseNumero(this.formArt.Unidades) <= 0) { this.errorsArt.Unidades = true; hasError = true; }
            if (!this.formArt.PrecioUnitario || this.parseNumero(this.formArt.PrecioUnitario) <= 0) { this.errorsArt.PrecioUnitario = true; hasError = true; }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoArticulo = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('id_proveedor', this.formArt.id_proveedor);
            fd.append('Concepto', this.formArt.Concepto);
            fd.append('Unidades', this.parseNumero(this.formArt.Unidades));
            fd.append('EstatusR', this.formArt.EstatusR);
            fd.append('PrecioUnitario', this.parseNumero(this.formArt.PrecioUnitario));

            try {
                const resp = await axios.post(`${this.BASE_URL}/agregar-articulo`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalArticulo')).hide();
                    this.formArt = { id_proveedor: '', Concepto: '', Unidades: '', EstatusR: 'Nuevo', PrecioUnitario: '' };
                    await this.recargarDatos();
                } else {
                    this.notify('error', resp.data?.message || 'Error al guardar el artículo.');
                }
            } catch (e) {
                this.notify('error', 'Error al procesar el artículo.');
            } finally {
                this.guardandoArticulo = false;
            }
        },

async eliminarArticulo(id, concepto) {
            await this.deleteAction({
                url: `${this.BASE_URL}/eliminar-articulo`,
                id: id,
                name: concepto ? `el artículo "${concepto}"` : 'el artículo'
            });

            // Se ejecuta inmediatamente tras confirmarse y procesarse la eliminación
            this.form.proveedores.forEach(prov => {
                if (prov.articulos) {
                    prov.articulos = prov.articulos.filter(a => a.id !== id);
                }
            });
            await this.recargarDatos();
        },

        // ---------- REFACTURACIÓN ---------- //
        abrirModalRefacturacion() {
            this.formRef = { Estacion: '', Descripcion: '', Cantidad: '', Importe: '', Porcentaje: '', CantidadES: '', CantidadAl: '' };
            this.errorsRef = { Estacion: false, Descripcion: false, Cantidad: false, Importe: false };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRefacturacion')).show();
        },

async guardarRefacturacion() {
            this.errorsRef = { Estacion: false, Descripcion: false, Cantidad: false, Importe: false };
            let hasError = false;

            if (!this.formRef.Estacion) { this.errorsRef.Estacion = true; hasError = true; }
            if (!this.formRef.Descripcion.trim()) { this.errorsRef.Descripcion = true; hasError = true; }
            if (!this.formRef.Cantidad || this.parseNumero(this.formRef.Cantidad) <= 0) { this.errorsRef.Cantidad = true; hasError = true; }
            if (!this.formRef.Importe || this.parseNumero(this.formRef.Importe) <= 0) { this.errorsRef.Importe = true; hasError = true; }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoRefacturacion = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('Estacion', this.formRef.Estacion);
            fd.append('Descripcion', this.formRef.Descripcion);
            fd.append('Cantidad', this.parseNumero(this.formRef.Cantidad));
            fd.append('Importe', this.parseNumero(this.formRef.Importe));
            fd.append('Porcentaje', this.parseNumero(this.formRef.Porcentaje));
            fd.append('CantidadES', this.parseNumero(this.formRef.CantidadES));
            fd.append('CantidadAl', this.parseNumero(this.formRef.CantidadAl));

            try {
                const resp = await axios.post(`${this.BASE_URL}/agregar-refacturacion`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getInstance(document.getElementById('modalRefacturacion')).hide();
                    this.formRef = { Estacion: '', Descripcion: '', Cantidad: '', Importe: '', Porcentaje: '', CantidadES: '', CantidadAl: '' };
                    await this.recargarDatos();
                } else {
                    this.notify('error', resp.data?.message || 'Error al guardar.');
                }
            } catch (e) {
                this.notify('error', 'Error al guardar refacturación.');
            } finally {
                this.guardandoRefacturacion = false;
            }
        },

async recargarDatos() {
            try {
                const resp = await axios.get(`${this.BASE_URL}/detalle-data/${this.form.id}`);
                if (resp.data?.success) {
                    this.form = resp.data.data;
                }
            } catch (e) {
                console.error('Error al sincronizar datos', e);
            }
        },

async eliminarRefacturacion(id, descripcion) {
            await this.deleteAction({
                url: `${this.BASE_URL}/eliminar-refacturacion`,
                id: id,
                name: descripcion ? `la refacturación "${descripcion}"` : 'la refacturación'
            });

            // Se ejecuta inmediatamente tras confirmarse y procesarse la eliminación
            if (this.form.refacturacion && this.form.refacturacion.filas) {
                this.form.refacturacion.filas = this.form.refacturacion.filas.filter(r => r.id !== id);
            }
            await this.recargarDatos();
        },

        // ---------- FINALIZAR Y FIRMAR ---------- //
        async finalizarOrden() {
            if (!this.signaturePad || this.signaturePad.isEmpty()) {
                this.notify('error', 'Por favor capture su firma en el recuadro antes de finalizar.');
                return;
            }

            this.guardandoFirma = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('base64', this.signaturePad.toDataURL());

            try {
                const resp = await axios.post(`${this.BASE_URL}/finalizar-pad`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    setTimeout(() => {
                        window.location.href = `/departamento-operativo/almacen/orden-compra/${this.form.fecha.split('-')[0]}/${parseInt(this.form.fecha.split('-')[1])}`;
                    }, 1200);
                } else {
                    this.notify('error', resp.data?.message || 'Error al finalizar.');
                    this.guardandoFirma = false;
                }
            } catch (e) {
                this.notify('error', 'Error al finalizar la orden.');
                this.guardandoFirma = false;
            }
        }
    }));
});
