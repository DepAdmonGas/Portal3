// ============================================================
// Maquinaria y Equipos — Almacén
// Componente Alpine: alta, edición, detalle, baja y comentarios.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('maquinariaEquiposComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/maquinaria-equipos',
        TIPO_DESCARGA: 'maquinaria-equipo',

        estacionFija: false,
        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,
        maquinariaOpciones: [],

        guardando: false,
        cargandoDetalle: false,
        cargandoRegistro: false,
        detalle: null,
        detalleRegistro: null,

        form: {
            maquinaria: '',
            descripcion: '',
            marca: '',
            modelo: '',
            no_serie: '',
            fecha_compra: '',
            fecha_instalacion: '',
            proveedor: '',
            costo_compra: '',
            garantia: '',
            factura_file: null,
            manual_file: null
        },

        errores: {
            maquinaria: false,
            descripcion: false,
            fecha_compra: false,
            fecha_instalacion: false,
            costo_compra: false,
            factura_file: false,
            manual_file: false
        },

        // Comentarios
        cargandoComentarios: false,
        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,
        comentarioId: 0,

        init() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.estacionFija = c.dataset.estacionFija === 'true';
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEditar = c.dataset.puedeEditar === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeDescargar = c.dataset.puedeDescargar === 'true';

                try {
                    this.maquinariaOpciones = JSON.parse(c.dataset.maquinariaOpciones || '[]');
                } catch (e) {
                    console.error(e);
                    this.maquinariaOpciones = [];
                }
            }

            document.addEventListener('mq:detalle', (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('mq:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('mq:eliminar', (e) => this.eliminar(e.detail.id, e.detail.nombre));
            document.addEventListener('mq:comentarios', (e) => this.abrirComentarios(e.detail.id));
            document.addEventListener('mq:bitacora', (e) => this.abrirBitacora(e.detail.id));
        },

        abrirBitacora(id) {
            window.location.href = this.BASE_URL + '-bitacora/' + id;
        },

        recargarTabla() {
            const tabla = $('#tabla-maquinaria-equipos');
            if (tabla.length && $.fn.DataTable.isDataTable(tabla)) {
                tabla.DataTable().ajax.reload(null, false);
            }
        },

        limpiarFormulario() {
            this.form = {
                maquinaria: '',
                descripcion: '',
                marca: '',
                modelo: '',
                no_serie: '',
                fecha_compra: '',
                fecha_instalacion: '',
                proveedor: '',
                costo_compra: '',
                garantia: '',
                factura_file: null,
                manual_file: null
            };
            this.errores = {
                maquinaria: false,
                descripcion: false,
                fecha_compra: false,
                fecha_instalacion: false,
                costo_compra: false,
                factura_file: false,
                manual_file: false
            };
            this.detalle = null;

            ['inputFacturaNuevo', 'inputManualNuevo', 'inputFacturaEditar', 'inputManualEditar']
                .forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
        },

        abrirNuevo() {
            this.limpiarFormulario();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevo')).show();
        },

        async abrirDetalle(id) {
            this.cargandoRegistro = true;
            this.detalleRegistro = null;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    this.detalleRegistro = resp.data.data;
                } else {
                    this.detalleRegistro = null;
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo obtener el registro.');
                }
            } catch (err) {
                console.error(err);
                this.detalleRegistro = null;
                this.notify('error', 'Error al consultar el registro.');
            } finally {
                this.cargandoRegistro = false;
            }
        },

        async abrirEditar(id) {
            this.limpiarFormulario();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    this.detalle = resp.data.data;
                    this.form.maquinaria = this.detalle.maquinaria || '';
                    this.form.descripcion = this.detalle.descripcion || '';
                    this.form.marca = this.detalle.marca || '';
                    this.form.modelo = this.detalle.modelo || '';
                    this.form.no_serie = this.detalle.no_serie || '';
                    this.form.fecha_compra = this.detalle.fecha_compra === '0000-00-00' ? '' : this.detalle.fecha_compra;
                    this.form.fecha_instalacion = this.detalle.fecha_instalacion === '0000-00-00' ? '' : this.detalle.fecha_instalacion;
                    this.form.proveedor = this.detalle.proveedor || '';
                    this.form.costo_compra = this.detalle.costo_compra;
                    this.form.garantia = this.detalle.garantia || '';
                } else {
                    this.detalle = null;
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo obtener el registro.');
                }
            } catch (err) {
                console.error(err);
                this.detalle = null;
                this.notify('error', 'Error al consultar el registro.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        validar() {
            this.errores = {
                maquinaria: false,
                descripcion: false,
                fecha_compra: false,
                fecha_instalacion: false,
                costo_compra: false,
                factura_file: false,
                manual_file: false
            };
            let hayError = false;

            if (!this.form.maquinaria) {
                this.errores.maquinaria = true;
                hayError = true;
            }

            if (!String(this.form.descripcion).trim()) {
                this.errores.descripcion = true;
                hayError = true;
            }

            if (!this.form.fecha_compra) {
                this.errores.fecha_compra = true;
                hayError = true;
            }

            if (!this.form.fecha_instalacion) {
                this.errores.fecha_instalacion = true;
                hayError = true;
            }

            if (this.form.costo_compra === '' || isNaN(parseFloat(this.form.costo_compra)) || parseFloat(this.form.costo_compra) < 0) {
                this.errores.costo_compra = true;
                hayError = true;
            }

            return hayError;
        },

        formDataBase() {
            const fd = new FormData();
            fd.append('Maquinaria', this.form.maquinaria);
            fd.append('Descripcion', String(this.form.descripcion).trim());
            fd.append('Marca', this.form.marca || '');
            fd.append('Modelo', this.form.modelo || '');
            fd.append('No_serie', this.form.no_serie || '');
            fd.append('Fecha_compra', this.form.fecha_compra);
            fd.append('Fecha_instalacion', this.form.fecha_instalacion);
            fd.append('Proveedor', this.form.proveedor || '');
            fd.append('Costo_compra', this.form.costo_compra);
            fd.append('Garantia', this.form.garantia || '');
            return fd;
        },

        async guardarNuevo() {
            if (!this.estacionFija) {
                this.notify('error', 'Selecciona una estación o departamento en el filtro superior.');
                return;
            }

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = this.formDataBase();
            if (this.form.factura_file) fd.append('Factura_doc_file', this.form.factura_file);
            if (this.form.manual_file) fd.append('Manual_doc_file', this.form.manual_file);

            await this.enviar(this.BASE_URL + '/guardar', fd, 'modalNuevo');
        },

        async guardarEdicion() {
            if (this.validar() || this.cargandoDetalle) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = this.formDataBase();
            fd.append('id', this.detalle.id);

            if (this.form.factura_file) fd.append('Factura_doc_file', this.form.factura_file);
            if (this.form.manual_file) fd.append('Manual_doc_file', this.form.manual_file);

            await this.enviar(this.BASE_URL + '/editar', fd, 'modalEditar');
        },

        async enviar(url, fd, modalId) {
            this.guardando = true;

            try {
                const resp = await axios.post(url, fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);

                    const modal = document.getElementById(modalId);
                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }

                    this.limpiarFormulario();
                    this.recargarTabla();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el registro.');
                }
            } catch (err) {
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error en el servidor al guardar el registro.';
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
            }
        },

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/eliminar',
                id: id,
                name: nombre || 'Maquinaria',
                table: '#tabla-maquinaria-equipos'
            });
        },

        // ---- Comentarios ----

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        async abrirComentarios(id) {
            this.comentarioId = id;
            this.nuevoComentario = '';
            this.comentarios = [];
            this.cargandoComentarios = true;

            const offcanvasEl = document.getElementById('modalComentarios');
            const oc = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

            oc.show();

            try {
                const resp = await axios.post(this.BASE_URL + '/comentarios', { id: id });

                if (resp.data && resp.data.success) {
                    this.comentarios = resp.data.comentarios || [];
                    this.scrollChatToBottom();
                }
            } catch (err) {
                console.error('Error cargando comentarios:', err);
            } finally {
                this.cargandoComentarios = false;
            }
        },

        async agregarComentario() {
            if (this.guardandoComentario) return;
            if (!this.nuevoComentario.trim()) return;
            if (!this.comentarioId) return;

            this.guardandoComentario = true;
            const id = this.comentarioId;

            try {
                const resp = await axios.post(this.BASE_URL + '/comentario-store', {
                    id: id,
                    comentario: this.nuevoComentario
                });

                if (resp.data && resp.data.success) {
                    this.nuevoComentario = '';

                    const resp2 = await axios.post(this.BASE_URL + '/comentarios', { id: id });
                    if (resp2.data && resp2.data.success) {
                        this.comentarios = resp2.data.comentarios || [];
                        this.scrollChatToBottom();
                    }

                    const dt = window.$('#tabla-maquinaria-equipos').DataTable();
                    dt.rows().every(function () {
                        const d = this.data();
                        if (d.id === id) {
                            d.comentarios = (d.comentarios || 0) + 1;
                            this.invalidate();
                            return false;
                        }
                    });
                    dt.draw(false);

                    this.notify('success', 'Comentario agregado correctamente.');
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'Error al agregar comentario.');
                }
            } catch (err) {
                console.error('Error al agregar comentario:', err);
                this.notify('error', 'Error al agregar comentario.');
            } finally {
                this.guardandoComentario = false;
            }
        },

        descargarFactura(archivo) {
            this.download(this.TIPO_DESCARGA, archivo);
        },

        descargarManual(archivo) {
            this.download(this.TIPO_DESCARGA, archivo);
        }

    }));
});