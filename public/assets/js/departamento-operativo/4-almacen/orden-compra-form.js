document.addEventListener('alpine:init', () => {
    Alpine.data('ordenCompraForm', (detalleInicial) => ({
        BASE_URL: '/departamento-operativo/almacen/orden-compra',
        form: detalleInicial,
        estacionSeleccionada: detalleInicial.estacion?.id_localidad || '',
        guardandoFirma: false,
        signaturePad: null,

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

        async editarOC(num, valor) {
            const fd = new FormData();
            fd.append('id', this.form.id);
            fd.append('num', num);
            fd.append('valor', valor);

            try {
                const resp = await axios.post(`${this.BASE_URL}/editar-formato`, fd);
                if (resp.data?.success) {
                    this.notify('success', 'Campo actualizado.');
                }
            } catch (e) {
                this.notify('error', 'Error al actualizar el campo.');
            }
        },

        abrirModalEstacion() {
            this.estacionSeleccionada = this.form.estacion?.id_localidad || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEstacion')).show();
        },

        async guardarEstacion() {
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
                this.notify('error', 'Completa los campos obligatorios marcados en rojo.');
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

        async seleccionarProveedor(idProveedor) {
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('idProveedor', idProveedor);
            fd.append('valor', 1);

            try {
                const resp = await axios.post(`${this.BASE_URL}/seleccionar-proveedor`, fd);
                if (resp.data?.success) {
                    this.notify('success', 'Proveedor seleccionado como mejor oferta.');
                    window.location.reload();
                }
            } catch (e) {
                this.notify('error', 'Error al seleccionar proveedor.');
            }
        },

        async actualizarCostos(idProveedor, tipo, valor) {
            const fd = new FormData();
            fd.append('idProveedor', idProveedor);
            fd.append('tipo', tipo);
            fd.append('valor', valor || 0);

            try {
                const resp = await axios.post(`${this.BASE_URL}/actualizar-costos-proveedor`, fd);
                if (resp.data?.success) {
                    this.notify('success', tipo === 1 ? 'Descuento actualizado.' : 'Costo de envío actualizado.');
                    window.location.reload();
                }
            } catch (e) {
                this.notify('error', 'Error al actualizar costos.');
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
            if (!this.formArt.Unidades || this.formArt.Unidades <= 0) { this.errorsArt.Unidades = true; hasError = true; }
            if (!this.formArt.PrecioUnitario || this.formArt.PrecioUnitario < 0) { this.errorsArt.PrecioUnitario = true; hasError = true; }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoArticulo = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('id_proveedor', this.formArt.id_proveedor);
            fd.append('Concepto', this.formArt.Concepto);
            fd.append('Unidades', this.formArt.Unidades);
            fd.append('EstatusR', this.formArt.EstatusR);
            fd.append('PrecioUnitario', this.formArt.PrecioUnitario);

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
            if (!this.formRef.Cantidad || this.formRef.Cantidad <= 0) { this.errorsRef.Cantidad = true; hasError = true; }
            if (!this.formRef.Importe || this.formRef.Importe < 0) { this.errorsRef.Importe = true; hasError = true; }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoRefacturacion = true;
            const fd = new FormData();
            fd.append('idReporte', this.form.id);
            fd.append('Estacion', this.formRef.Estacion);
            fd.append('Descripcion', this.formRef.Descripcion);
            fd.append('Cantidad', this.formRef.Cantidad);
            fd.append('Importe', this.formRef.Importe);
            fd.append('Porcentaje', this.formRef.Porcentaje || 0);
            fd.append('CantidadES', this.formRef.CantidadES || 0);
            fd.append('CantidadAl', this.formRef.CantidadAl || 0);

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